<?php
/**
 * Order detail panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $inline ? 'div' : 'section';
$class = $inline ? 'mds3-order-detail-inline' : 'mds3-card';
$placement_maps = is_array($placement_maps ?? null) ? $placement_maps : [];
$placement_events = is_array($placement_events ?? null) ? $placement_events : [];
$status_events = is_array($status_events ?? null) ? $status_events : [];
$move_notice = is_array($move_notice ?? null) ? $move_notice : [];
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2>
        <?php
        echo esc_html(sprintf(
            /* translators: %d: order ID */
            __('Order #%d', 'million-dollar-script'),
            absint($order['id'] ?? 0)
        ));
        ?>
    </h2>
    <?php if (!empty($move_notice['message'])) : ?>
        <div class="notice notice-<?php echo esc_attr('success' === ($move_notice['type'] ?? '') ? 'success' : 'error'); ?> inline"><p><?php echo esc_html($move_notice['message']); ?></p></div>
    <?php endif; ?>
    <div class="mds3-order-summary">
        <p><strong><?php esc_html_e('Status', 'million-dollar-script'); ?>:</strong> <?php echo esc_html($order['status'] ?? ''); ?></p>
        <p><strong><?php esc_html_e('Total', 'million-dollar-script'); ?>:</strong> <?php echo esc_html(\MillionDollarScript\V3\Commerce\Currency::format((float) ($order['total'] ?? 0), $order['currency'] ?? '')); ?></p>
        <p><strong><?php esc_html_e('Customer', 'million-dollar-script'); ?>:</strong> <?php echo esc_html(($order['email'] ?? '') ?: '-'); ?></p>
        <p><strong><?php esc_html_e('Commerce', 'million-dollar-script'); ?>:</strong> <?php echo esc_html((($order['commerce_provider'] ?? '') ?: 'standalone') . (!empty($order['commerce_order_id']) ? ' #' . $order['commerce_order_id'] : '')); ?></p>
        <?php
        // What the store paid and what the order asked for are both on the record. The
        // difference is only ever shown here - it does not change the order's status.
        $amount_evidence = \MillionDollarScript\V3\Commerce\PaymentAmountEvidence::describe(is_array($metadata ?? null) ? $metadata : array());
        if ($amount_evidence) : ?>
            <div class="notice notice-warning inline mds3-payment-amount-mismatch"><p><?php echo esc_html($amount_evidence['text']); ?></p></div>
        <?php endif; ?>
        <?php if ($manage_url) : ?>
            <p><strong><?php esc_html_e('Customer Manage Link', 'million-dollar-script'); ?>:</strong> <a href="<?php echo esc_url($manage_url); ?>"><?php esc_html_e('Open upload manager', 'million-dollar-script'); ?></a></p>
        <?php endif; ?>
        <?php if (!empty($advertiser_page_url)) : ?>
            <p><strong><?php esc_html_e('Advertiser Page', 'million-dollar-script'); ?>:</strong> <a href="<?php echo esc_url($advertiser_page_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open advertiser page', 'million-dollar-script'); ?></a></p>
        <?php endif; ?>
        <?php if (!empty($renewal_available)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-inline-form">
                <input type="hidden" name="action" value="mds3_start_order_renewal" />
                <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($renewal_nonce ?? ''); ?>" />
                <button type="submit" class="button button-small"><?php esc_html_e('Create renewal checkout', 'million-dollar-script'); ?></button>
            </form>
        <?php endif; ?>
        <?php if (!empty($metadata['package']['title'])) : ?>
            <p><strong><?php esc_html_e('Package', 'million-dollar-script'); ?>:</strong> <?php echo esc_html($metadata['package']['title']); ?></p>
        <?php endif; ?>
        <?php if ($rect) : ?>
            <p><strong><?php esc_html_e('Placement Rectangle', 'million-dollar-script'); ?>:</strong> <?php echo esc_html($rect['x'] . ',' . $rect['y'] . ' ' . $rect['width'] . 'x' . $rect['height']); ?></p>
        <?php endif; ?>
    </div>

    <?php
    // What the customer typed alongside the artwork. The form questions come from the
    // Fields extension; the answers are part of the order record.
    $advertiser_answers = is_array($advertiser_answers ?? null) ? $advertiser_answers : array();
    if ($advertiser_answers) : ?>
        <div class="mds3-order-advertiser-answers">
            <h3><?php esc_html_e('Advertiser answers', 'million-dollar-script'); ?></h3>
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($advertiser_answers as $answer) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($answer['label'] ?? ''); ?></th>
                            <td>
                                <?php if (!empty($answer['url'])) : ?>
                                    <a href="<?php echo esc_url($answer['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($answer['value'] ?? ''); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($answer['value'] ?? ''); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php
    // Payment and publication are different facts. This block answers only the second one,
    // and only once an order has paid - before that there is nothing to show.
    $publication = is_array($publication ?? null) ? $publication : [];
    if ($publication) :
        $is_live = !empty($publication['published']);
    ?>
        <div class="mds3-order-publication <?php echo esc_attr($is_live ? 'is-live' : 'is-held'); ?>">
            <div class="mds3-order-publication-copy">
                <h3><?php esc_html_e('Ad visibility', 'million-dollar-script'); ?></h3>
                <p>
                    <?php if ($is_live) : ?>
                        <?php esc_html_e('Published — this artwork is showing on the grid.', 'million-dollar-script'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Not published — this artwork is not showing on the grid.', 'million-dollar-script'); ?>
                    <?php endif; ?>
                </p>
                <?php $reason = (string) ($publication['reason'] ?? ''); ?>
                <?php if ('' !== $reason) : ?>
                    <p class="mds3-order-publication-reason">
                        <?php
                        if ('reviews' === $reason) {
                            esc_html_e('This grid reviews paid uploads before they appear.', 'million-dollar-script');
                        } else {
                            esc_html_e('This artwork stopped showing when its listing ended, and will not come back on its own.', 'million-dollar-script');
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="mds3-order-publication-actions">
                <?php if (!empty($publication['can_publish'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-inline-form">
                        <input type="hidden" name="action" value="mds3_publish_placement" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" />
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr((string) ($publication['nonce_publish'] ?? '')); ?>" />
                        <button type="submit" class="button"><?php esc_html_e('Publish', 'million-dollar-script'); ?></button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($publication['can_unpublish'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-inline-form">
                        <input type="hidden" name="action" value="mds3_unpublish_placement" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" />
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr((string) ($publication['nonce_unpublish'] ?? '')); ?>" />
                        <button type="submit" class="button"><?php esc_html_e('Unpublish', 'million-dollar-script'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($placement_maps) : ?>
        <div class="mds3-order-placement-maps">
            <?php $legend_rendered = false; ?>
            <?php foreach ($placement_maps as $map) : ?>
                <div class="mds3-order-placement-map-shell">
                    <h3><?php echo esc_html($map['title'] ?? __('Grid placement', 'million-dollar-script')); ?></h3>
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: width in pixels, 2: height in pixels. */
                            __('%1$s x %2$s pixels', 'million-dollar-script'),
                            number_format_i18n(absint($map['width'] ?? 0)),
                            number_format_i18n(absint($map['height'] ?? 0))
                        ));
                        ?>
                    </p>
                    <?php if (!$legend_rendered) : ?>
                        <?php $legend_rendered = true; ?>
                        <div class="mds3-order-placement-legend" role="group" aria-label="<?php esc_attr_e('Placement preview legend', 'million-dollar-script'); ?>">
                            <span class="mds3-order-placement-legend-title"><?php esc_html_e('Preview legend', 'million-dollar-script'); ?></span>
                            <ul>
                                <li><span class="mds3-order-placement-legend-swatch is-available" aria-hidden="true"></span><?php esc_html_e('Available', 'million-dollar-script'); ?></li>
                                <li><span class="mds3-order-placement-legend-swatch is-sold" aria-hidden="true"></span><?php esc_html_e('Sold', 'million-dollar-script'); ?></li>
                                <li><span class="mds3-order-placement-legend-swatch is-reserved" aria-hidden="true"></span><?php esc_html_e('Reserved', 'million-dollar-script'); ?></li>
                                <li><span class="mds3-order-placement-legend-swatch is-unavailable" aria-hidden="true"></span><?php esc_html_e('Unavailable', 'million-dollar-script'); ?></li>
                                <li><span class="mds3-order-placement-legend-swatch is-selected" aria-hidden="true"></span><?php esc_html_e('Current placement', 'million-dollar-script'); ?></li>
                                <?php if (!empty($map['move_allowed'])) : ?>
                                    <li><span class="mds3-order-placement-legend-swatch is-proposed" aria-hidden="true"></span><?php esc_html_e('Proposed placement', 'million-dollar-script'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php
                    \MillionDollarScript\V3\Support\Template::display('admin/partials/region-editor.php', [
                        'blocks_json' => wp_json_encode($map['blocks'] ?? []),
                        'canvas_label' => sprintf(
                            /* translators: %s: grid title. */
                            __('Placement preview for %s', 'million-dollar-script'),
                            $map['title'] ?? __('grid', 'million-dollar-script')
                        ),
                        'cols' => absint($map['cols'] ?? 1),
                        'extra_class' => 'mds3-order-placement-map',
                        'mode' => !empty($map['move_allowed']) ? 'move' : 'inspect',
                        'move_col_span' => absint($map['move_col_span'] ?? 0),
                        'move_row_span' => absint($map['move_row_span'] ?? 0),
                        'move_shape_json' => wp_json_encode($map['move_shape'] ?? []),
                        'readonly' => empty($map['move_allowed']),
                        'regions_json' => wp_json_encode($map['regions'] ?? []),
                        'rows' => absint($map['rows'] ?? 1),
                        'rules_json' => '[]',
                        'selections_json' => wp_json_encode($map['selections'] ?? []),
                        'status_text' => $map['status_text'] ?? '',
                        'total_blocks' => absint($map['total_blocks'] ?? 1),
                        'zoom_label' => __('Placement preview zoom controls', 'million-dollar-script'),
                    ], $this);
                    ?>
                    <?php if (!empty($map['move_allowed'])) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-order-move-form" data-mds3-order-move-form>
                            <input type="hidden" name="action" value="mds3_move_order_placement" />
                            <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($map['order_id'] ?? 0)); ?>" />
                            <input type="hidden" name="grid_id" value="<?php echo esc_attr(absint($map['grid_id'] ?? 0)); ?>" />
                            <input type="hidden" name="row_to" value="" />
                            <input type="hidden" name="col_to" value="" />
                            <input type="hidden" name="preview_nonce" value="<?php echo esc_attr($map['move_nonce'] ?? ''); ?>" />
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($map['move_post_nonce'] ?? ''); ?>" />
                            <div class="mds3-order-move-heading">
                                <div>
                                    <h4><?php esc_html_e('Move placement', 'million-dollar-script'); ?></h4>
                                    <p><?php esc_html_e('Click the new top-left block on the map, or enter zero-based coordinates. Preview validates availability and price-zone impact before the move is enabled.', 'million-dollar-script'); ?></p>
                                </div>
                                <span><?php echo esc_html(sprintf(
                                    /* translators: 1: placement rows, 2: placement columns. */
                                    __('Footprint: %1$s rows x %2$s columns', 'million-dollar-script'),
                                    number_format_i18n(absint($map['move_row_span'] ?? 0)),
                                    number_format_i18n(absint($map['move_col_span'] ?? 0))
                                )); ?></span>
                            </div>
                            <div class="mds3-order-move-controls">
                                <label>
                                    <span><?php esc_html_e('Top row', 'million-dollar-script'); ?></span>
                                    <input type="number" name="row_from" min="0" max="<?php echo esc_attr(max(0, absint($map['rows'] ?? 1) - absint($map['move_row_span'] ?? 1))); ?>" step="1" inputmode="numeric" required />
                                </label>
                                <label>
                                    <span><?php esc_html_e('Left column', 'million-dollar-script'); ?></span>
                                    <input type="number" name="col_from" min="0" max="<?php echo esc_attr(max(0, absint($map['cols'] ?? 1) - absint($map['move_col_span'] ?? 1))); ?>" step="1" inputmode="numeric" required />
                                </label>
                                <button type="button" class="button" data-mds3-order-move-preview><?php esc_html_e('Preview move', 'million-dollar-script'); ?></button>
                                <button type="submit" class="button button-primary" data-mds3-order-move-submit disabled><?php esc_html_e('Move placement', 'million-dollar-script'); ?></button>
                            </div>
                            <div class="mds3-order-move-preview" data-mds3-order-move-preview-status aria-live="polite"></div>
                        </form>
                    <?php elseif (!empty($map['selections'])) : ?>
                        <p class="mds3-order-move-unavailable"><?php esc_html_e('Moving is available for reserved, awaiting-payment, paid, and renewable expired orders that still own their blocks.', 'million-dollar-script'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($status_events) : ?>
        <div class="mds3-order-events">
            <h3><?php esc_html_e('Status history', 'million-dollar-script'); ?></h3>
            <ol>
                <?php foreach ($status_events as $event) : ?>
                    <li>
                        <strong><?php echo esc_html($event['status'] ?? ''); ?></strong>
                        <span><?php echo esc_html($event['created_at'] ?? ''); ?></span>
                        <span><?php echo esc_html(($event['source'] ?? '') . ' - ' . ($event['user'] ?? '')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <?php if ($placement_events) : ?>
        <div class="mds3-order-events">
            <h3><?php esc_html_e('Placement history', 'million-dollar-script'); ?></h3>
            <ol>
                <?php foreach ($placement_events as $event) : ?>
                    <li>
                        <strong><?php esc_html_e('Placement moved', 'million-dollar-script'); ?></strong>
                        <span>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: source row, 2: source column, 3: target row, 4: target column, 5: block count, 6: grid ID. */
                                _n(
                                    'Row %1$d, column %2$d to row %3$d, column %4$d; %5$d block on grid #%6$d',
                                    'Row %1$d, column %2$d to row %3$d, column %4$d; %5$d blocks on grid #%6$d',
                                    absint($event['block_count'] ?? 0),
                                    'million-dollar-script'
                                ),
                                absint($event['from_row'] ?? 0),
                                absint($event['from_col'] ?? 0),
                                absint($event['to_row'] ?? 0),
                                absint($event['to_col'] ?? 0),
                                absint($event['block_count'] ?? 0),
                                absint($event['grid_id'] ?? 0)
                            ));
                            ?>
                        </span>
                        <span><?php echo esc_html($event['created_at'] ?? ''); ?></span>
                        <span><?php echo esc_html(($event['source'] ?? '') . ' - ' . ($event['user'] ?? '')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <?php if ($item_rows) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Item', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Grid', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Block', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Rectangle', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Price Source', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Total', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item_rows as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item['item_type']); ?></td>
                        <td><?php echo esc_html($item['grid_id']); ?></td>
                        <td><?php echo esc_html($item['block_id']); ?></td>
                        <td><?php echo esc_html($item['rectangle']); ?></td>
                        <td><?php echo esc_html($item['price_source']); ?></td>
                        <td><?php echo esc_html($item['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
