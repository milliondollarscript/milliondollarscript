<?php
/**
 * Building the export a person receives for the records this plugin holds.
 *
 * @package MillionDollarScript\V3\Privacy
 */

namespace MillionDollarScript\V3\Privacy\Concerns;

use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementDraftRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * What an export answers with: the order itself, the artwork that was uploaded for
 * it, and the placement that ran. Values are handed over as they are stored and
 * escaped once by WordPress when it renders the file, which is what WordPress'
 * own exporters do.
 *
 * Anything an extension collected through its own fields is exported by that
 * extension; core reports what core wrote, plus the custom-field answers it was
 * handed to store (they live in the order, so a person asking for their data
 * should see them here too).
 */
trait PersonalRecordExport
{
    /**
     * @param string $email
     * @param int $page
     * @return array
     */
    public function export($email, $page = 1)
    {
        $page = max(1, absint($page));
        $orders = $this->matched_orders((string) $email, $page);

        $data = [];
        foreach ($orders as $order) {
            $data[] = $this->export_order($order);
            foreach ($this->order_placements($order) as $placement) {
                $data[] = $this->export_placement($order, $placement);
            }
        }

        return [
            'data' => $data,
            'done' => count($orders) < self::PAGE_SIZE,
        ];
    }

    /**
     * One order: what was ordered, what it cost, how it was paid for, and what the
     * person typed into the extension fields of the form.
     *
     * @param array $order
     * @return array
     */
    private function export_order(array $order)
    {
        $order_id = absint($order['id'] ?? 0);
        $values = [
            ['name' => __('Order reference', 'million-dollar-script'), 'value' => (string) $order_id],
            ['name' => __('Placed on', 'million-dollar-script'), 'value' => (string) ($order['created_at'] ?? '')],
            ['name' => __('Last changed', 'million-dollar-script'), 'value' => (string) ($order['updated_at'] ?? '')],
            ['name' => __('Status', 'million-dollar-script'), 'value' => (string) ($order['status'] ?? '')],
            ['name' => __('Email on file', 'million-dollar-script'), 'value' => (string) ($order['email'] ?? '')],
        ];

        if (isset($order['total'])) {
            $values[] = [
                'name' => __('Total', 'million-dollar-script'),
                'value' => trim((string) $order['total'] . ' ' . (string) ($order['currency'] ?? '')),
            ];
        }
        if ('' !== (string) ($order['commerce_provider'] ?? '')) {
            $values[] = ['name' => __('Payment provider', 'million-dollar-script'), 'value' => (string) $order['commerce_provider']];
        }
        if ('' !== (string) ($order['commerce_order_id'] ?? '')) {
            $values[] = ['name' => __('Payment reference', 'million-dollar-script'), 'value' => (string) $order['commerce_order_id']];
        }

        foreach ((new OrderRepository())->items($order_id) as $item) {
            $grid_title = get_the_title(absint($item['grid_id'] ?? 0));
            $values[] = [
                'name' => __('Ordered', 'million-dollar-script'),
                'value' => sprintf(
                    '%s × %d — %s %s',
                    '' !== $grid_title ? $grid_title : ('#' . absint($item['grid_id'] ?? 0)),
                    max(1, absint($item['quantity'] ?? 1)),
                    (string) ($item['unit_price'] ?? ''),
                    (string) ($item['currency'] ?? ($order['currency'] ?? ''))
                ),
            ];
        }

        $metadata = $this->order_metadata($order);
        foreach ($this->submitted_field_answers($metadata) as $question => $answer) {
            $values[] = ['name' => $question, 'value' => $answer];
        }

        $drafts = new PlacementDraftRepository();
        $draft = $drafts->current($order);
        if (is_array($draft)) {
            $payload = $drafts->payload($draft);
            $draft_url = (string) ($payload['source']['url'] ?? '');
            if ('' !== $draft_url) {
                $values[] = ['name' => __('Uploaded artwork not yet placed', 'million-dollar-script'), 'value' => $draft_url];
            }
        }

        return $this->export_item('order-' . $order_id, $values);
    }

    /**
     * One placement: the content that was published and the image it was built
     * from. This is the part of the record a person is most likely to recognise.
     *
     * @param array $order
     * @param array $placement
     * @return array
     */
    private function export_placement(array $order, array $placement)
    {
        $placement_id = absint($placement['id'] ?? 0);
        $values = [
            ['name' => __('Order reference', 'million-dollar-script'), 'value' => (string) absint($order['id'] ?? 0)],
            ['name' => __('Grid', 'million-dollar-script'), 'value' => $this->grid_label(absint($placement['grid_id'] ?? 0))],
            ['name' => __('Position', 'million-dollar-script'), 'value' => $this->placement_position($placement)],
            ['name' => __('Placement status', 'million-dollar-script'), 'value' => (string) ($placement['status'] ?? '')],
            ['name' => __('Published on', 'million-dollar-script'), 'value' => (string) ($placement['created_at'] ?? '')],
        ];

        foreach (['popup_text' => __('Ad text', 'million-dollar-script'), 'alt_text' => __('Image description', 'million-dollar-script'), 'link_url' => __('Ad link', 'million-dollar-script')] as $field => $label) {
            $value = (string) ($placement[$field] ?? '');
            if ('' !== $value) {
                $values[] = ['name' => $label, 'value' => $value];
            }
        }

        $image = wp_get_attachment_url(absint($placement['attachment_id'] ?? 0));
        if ($image) {
            $values[] = ['name' => __('Image', 'million-dollar-script'), 'value' => $image];
        }

        $page_url = (new AdvertiserPageManager())->public_url($placement_id);
        if (is_string($page_url) && '' !== $page_url) {
            $values[] = ['name' => __('Advertiser page', 'million-dollar-script'), 'value' => $page_url];
        }

        return $this->export_item('placement-' . $placement_id, $values);
    }

    /**
     * @param string $item_id
     * @param array $values
     * @return array
     */
    private function export_item($item_id, array $values)
    {
        return [
            'group_id' => self::GROUP_ID,
            'group_label' => self::GROUP_LABEL,
            'group_description' => __('Orders placed through this site and the advertising they produced.', 'million-dollar-script'),
            'item_id' => 'mds-' . $item_id,
            'data' => $values,
        ];
    }

    /**
     * The answers a person typed into extension-provided fields. They are stored
     * with the order, so they belong in the answer to a request about that person.
     *
     * @return array<string,string>
     */
    private function submitted_field_answers(array $metadata): array
    {
        $fields = isset($metadata['mds_fields']) && is_array($metadata['mds_fields']) ? $metadata['mds_fields'] : [];

        $answers = [];
        foreach ($fields as $key => $value) {
            if (!is_scalar($value) || '' === trim((string) $value)) {
                continue;
            }
            $answers[sanitize_text_field((string) $key)] = sanitize_text_field((string) $value);
        }

        return $answers;
    }

    /**
     * @param array $order
     * @return array
     */
    private function order_metadata(array $order): array
    {
        $metadata = $order['metadata'] ?? null;
        if (is_string($metadata) && '' !== $metadata) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param array $order
     * @return array<int,array>
     */
    private function order_placements(array $order): array
    {
        return (new PlacementRepository())->for_order(absint($order['id'] ?? 0));
    }

    /**
     * A grid name a person can read, falling back to the identifier when the grid
     * is gone.
     */
    private function grid_label(int $grid_id): string
    {
        if ($grid_id <= 0) {
            return '';
        }
        $title = get_the_title($grid_id);

        return '' !== $title ? $title : ('#' . $grid_id);
    }

    /**
     * Where the ad sat, in the terms the site uses for its grid.
     */
    private function placement_position(array $placement): string
    {
        $width = max(1, absint($placement['width'] ?? 1));
        $height = max(1, absint($placement['height'] ?? 1));

        return sprintf(
            __('column %1$d, row %2$d, %3$d x %4$d', 'million-dollar-script'),
            absint($placement['x'] ?? 0) + 1,
            absint($placement['y'] ?? 0) + 1,
            $width,
            $height
        );
    }
}
