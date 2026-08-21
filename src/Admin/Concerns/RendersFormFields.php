<?php
/**
 * Shared admin form fields and inline form controls.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Admin\FieldHelp;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersFormFields {

    private function field($name, $label, $type, $value, $step = '', array $options = [], array $args = []) {
        static $field_index = 0;

        $field_index++;
        $id = 'mds3-field-' . sanitize_key((string) $name) . '-' . $field_index;
        $help = array_key_exists('help', $args) ? (string) $args['help'] : $this->field_help((string) $name);
        $description = array_key_exists('description', $args) ? (string) $args['description'] : '';
        $description_id = '' !== trim($description) ? $id . '-description' : '';
        $docs_link = '';
        if (!empty($args['docs'])) {
            $docs_args = is_array($args['docs_args'] ?? null) ? $args['docs_args'] : [];
            $docs_link = FieldHelp::docs_link((string) $args['docs'], (string) ($args['docs_label'] ?? ''), $docs_args);
        }
        $disabled = !empty($args['disabled']);
        $readonly = !empty($args['readonly']);
        $hidden_value = array_key_exists('hidden_value', $args) ? $args['hidden_value'] : null;
        $option_values = is_array($args['option_values'] ?? null) ? $args['option_values'] : [];
        $field_class = 'mds3-field mds3-field-' . sanitize_key((string) $type);
        if (!empty($args['wide'])) {
            $field_class .= ' mds3-field-wide';
        }
        $common = ($disabled ? ' disabled="disabled" aria-disabled="true"' : '') . ($readonly ? ' readonly="readonly"' : '');
        $common .= $description_id ? ' aria-describedby="' . esc_attr($description_id) . '"' : '';
        $autocomplete = array_key_exists('autocomplete', $args) && is_scalar($args['autocomplete']) ? sanitize_key((string) $args['autocomplete']) : '';
        $autocomplete_attr = $autocomplete ? ' autocomplete="' . esc_attr($autocomplete) . '"' : '';
        $number_attrs = '';
        if ('number' === $type) {
            foreach (['min', 'max'] as $attribute) {
                if (array_key_exists($attribute, $args) && is_numeric($args[$attribute])) {
                    $number_attrs .= ' ' . $attribute . '="' . esc_attr((string) $args[$attribute]) . '"';
                }
            }
        }
        $editor_id = 'mds3_editor_' . str_replace('-', '_', sanitize_key((string) $name)) . '_' . $field_index;
        $editor_settings = [
            'textarea_name' => (string) $name,
            'textarea_rows' => absint($args['textarea_rows'] ?? 10) ?: 10,
            'media_buttons' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
                'toolbar2' => '',
            ],
        ];

        if (null !== $hidden_value) {
            $hidden_value = (string) $hidden_value;
        }

        $custom_markup = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/form/field/markup', '', $name, $label, $type, $value, $step, $options, $args);
        if (is_string($custom_markup) && '' !== trim($custom_markup)) {
            echo $custom_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return false;
        }

        Template::display('admin/partials/form-field.php', [
            'autocomplete_attr' => $autocomplete_attr,
            'common' => $common,
            'description' => $description,
            'description_markup' => FieldHelp::description($description, $description_id),
            'disabled' => $disabled,
            'docs_link' => $docs_link,
            'editor_id' => $editor_id,
            'editor_settings' => $editor_settings,
            'field_class' => $field_class,
            'help' => $help,
            'help_markup' => $this->help_markup($help),
            'hidden_value' => $hidden_value,
            'id' => $id,
            'label' => $label,
            'name' => $name,
            'number_attrs' => $number_attrs,
            'option_values' => $option_values,
            'options' => $options,
            'step' => $step,
            'type' => $type,
            'value' => $value,
        ], $this);

        return true;
    }

    private function select_option_label($value, $label) {
        $raw_label = trim((string) $label);
        $raw_value = trim((string) $value);

        if ('' !== $raw_label && strcasecmp($raw_label, $raw_value) !== 0) {
            return $raw_label;
        }

        $labels = [
            '1' => __('Yes', 'million-dollar-script'),
            '0' => __('No', 'million-dollar-script'),
            'yes' => __('Yes', 'million-dollar-script'),
            'no' => __('No', 'million-dollar-script'),
            'y' => __('Yes', 'million-dollar-script'),
            'n' => __('No', 'million-dollar-script'),
            'system' => __('System', 'million-dollar-script'),
            'light' => __('Light', 'million-dollar-script'),
            'dark' => __('Dark', 'million-dollar-script'),
            '_blank' => __('New tab', 'million-dollar-script'),
            '_self' => __('Same tab', 'million-dollar-script'),
            'none' => __('None', 'million-dollar-script'),
            'basic' => __('Basic', 'million-dollar-script'),
            'full' => __('Full', 'million-dollar-script'),
            'blocks' => __('Blocks', 'million-dollar-script'),
            'pixels' => __('Pixels', 'million-dollar-script'),
            'mouseenter' => __('Hover', 'million-dollar-script'),
            'click' => __('Click', 'million-dollar-script'),
            'main' => __('Main', 'million-dollar-script'),
            'stable' => __('Main', 'million-dollar-script'),
            'beta' => __('Beta', 'million-dollar-script'),
            'alpha' => __('Alpha', 'million-dollar-script'),
            'development' => __('Alpha', 'million-dollar-script'),
            'active' => __('Active', 'million-dollar-script'),
            'paused' => __('Paused', 'million-dollar-script'),
            'archived' => __('Archived', 'million-dollar-script'),
            'available' => __('Available', 'million-dollar-script'),
            'unavailable' => __('Unavailable', 'million-dollar-script'),
            'auto' => __('Automatic', 'million-dollar-script'),
            'classic' => __('Classic', 'million-dollar-script'),
            'canvas' => __('Canvas', 'million-dollar-script'),
        ];

        $key = strtolower($raw_value);

        return $labels[$key] ?? $raw_label;
    }

    private function image_field($id, $name, $value, $disabled = false) {
        $image = $value && function_exists('wp_get_attachment_image') ? wp_get_attachment_image($value, 'thumbnail', false, ['class' => 'mds3-image-field-preview-image']) : '';

        Template::display('admin/partials/image-field.php', [
            'disabled' => $disabled,
            'id' => $id,
            'image' => $image,
            'name' => $name,
            'value' => $value,
        ], $this);
    }

    private function help_markup($help) {
        if ('' === trim((string) $help)) {
            return '';
        }

        return FieldHelp::info($help);
    }

    private function field_help($name) {
        $help = [
            'title' => __('Administrative title shown in Million Dollar Script grid lists and generated page labels.', 'million-dollar-script'),
            'description' => __('Internal or frontend description for this item.', 'million-dollar-script'),
            'width' => __('Total grid width in pixels.', 'million-dollar-script'),
            'height' => __('Total grid height in pixels.', 'million-dollar-script'),
            'block_width' => __('Width of each sellable block in pixels.', 'million-dollar-script'),
            'block_height' => __('Height of each sellable block in pixels.', 'million-dollar-script'),
            'price_per_block' => __('Default price for one block before packages or price zones apply.', 'million-dollar-script'),
            'currency' => __('Three-letter currency code for prices saved from this form.', 'million-dollar-script'),
            'renderer_mode' => __('Renderer used for the public grid. Auto chooses the best available renderer.', 'million-dollar-script'),
            'min_blocks' => __('Minimum blocks a customer must select in one order.', 'million-dollar-script'),
            'max_blocks' => __('Maximum blocks a customer may select in one order. Use 0 for no limit.', 'million-dollar-script'),
            'max_orders' => __('Maximum active orders allowed for this item. Use 0 for no limit.', 'million-dollar-script'),
            'days_expire' => __('Number of days before pending orders expire. Use 0 to disable day-based expiry.', 'million-dollar-script'),
            'auto_publish' => __('Publishes customer uploads immediately after payment when enabled.', 'million-dollar-script'),
            'auto_approve' => __('Approves orders automatically instead of requiring manual review.', 'million-dollar-script'),
            'nfs_covered' => __('Controls whether unavailable blocks count as covered grid area.', 'million-dollar-script'),
            'background_color' => __('Background color shown behind this grid.', 'million-dollar-script'),
            'background_image_id' => __('Optional image shown behind available grid space. Paid placements, availability states, selections, controls, and popovers remain above it.', 'million-dollar-script'),
            'status' => __('Controls whether this item is active, paused, or archived.', 'million-dollar-script'),
            'duration_days' => __('Number of days this package keeps a placement active. Use 0 for no fixed duration.', 'million-dollar-script'),
            'price' => __('Price charged for this package or zone.', 'million-dollar-script'),
            'row_from' => __('First grid row in the selected region.', 'million-dollar-script'),
            'row_to' => __('Last grid row in the selected region.', 'million-dollar-script'),
            'col_from' => __('First grid column in the selected region.', 'million-dollar-script'),
            'col_to' => __('Last grid column in the selected region.', 'million-dollar-script'),
            'block_id_from' => __('First legacy block ID covered by this zone.', 'million-dollar-script'),
            'block_id_to' => __('Last legacy block ID covered by this zone.', 'million-dollar-script'),
            'region_status' => __('Availability status to apply to the selected region.', 'million-dollar-script'),
            'note' => __('Optional internal note for this region change.', 'million-dollar-script'),
            'source_prefix' => __('Database table prefix for the Million Dollar Script 2 installation being inspected.', 'million-dollar-script'),
        ];

        return $help[$name] ?? '';
    }

    private function inline_post_button($action, $nonce_action, array $data, $label, $class = '', $confirm = '') {
        Template::display('admin/partials/inline-post-button.php', [
            'action' => $action,
            'class' => $class,
            'confirm_message' => (string) $confirm,
            'data' => $data,
            'label' => $label,
            'nonce_action' => $nonce_action,
        ], $this);
    }
}
