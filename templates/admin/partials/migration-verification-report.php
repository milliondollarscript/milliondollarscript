<?php
/**
 * Migration verification report.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<h2><?php esc_html_e('Latest Verification Report', 'million-dollar-script'); ?></h2>
<p>
    <?php
    echo esc_html(sprintf(
        /* translators: 1: status, 2: source prefix */
        __('Status: %1$s. Source prefix: %2$s. Million Dollar Script 2 tables dropped: no.', 'million-dollar-script'),
        (string) ($run['status'] ?? ''),
        (string) ($run['source_prefix'] ?? '')
    ));
    ?>
</p>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Entity', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Imported This Run', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Mapped Total', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Recovery Status', 'million-dollar-script'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo esc_html($row['label']); ?></td>
                <td><?php echo esc_html($row['imported']); ?></td>
                <td><?php echo esc_html($row['mapped_total']); ?></td>
                <td><?php echo esc_html($row['status']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($coverage)) : ?>
    <h3><?php esc_html_e('What the import left behind', 'million-dollar-script'); ?></h3>
    <?php if ('' !== (string) ($coverage_note ?? '')) : ?>
        <div class="notice notice-warning inline">
            <p><?php echo esc_html((string) $coverage_note); ?></p>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('Every ad from the older site holds a place here or is named in the report, every ad image that could be read is attached, and no placement falls outside the grid.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
    <?php if (absint($coverage['images']['cell_size_only'] ?? 0) > 0) : ?>
        <p>
            <?php
            echo esc_html(sprintf(
                /* translators: %d: number of ads whose only remaining copy is one cell in size. */
                _n(
                    '%d ad kept its picture, but the only copy the older site held was one cell in size. It shows correctly and will look soft when the ad is larger than a cell; open the placement and upload a larger image to replace it.',
                    '%d ads kept their picture, but the only copy the older site held was one cell in size. They show correctly and will look soft when an ad is larger than a cell; open the placement and upload a larger image to replace it.',
                    (int) $coverage['images']['cell_size_only'],
                    'million-dollar-script'
                ),
                number_format_i18n((int) $coverage['images']['cell_size_only'])
            ));
            ?>
        </p>
    <?php endif; ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Check', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('In Million Dollar Script 2', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('In this site', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Explained in the report', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Result', 'million-dollar-script'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $coverage_rows = [
                [
                    'label' => __('Ads holding a place on the grid, and ads with a place here', 'million-dollar-script'),
                    'source' => isset($coverage['orders']['source']) ? number_format_i18n((int) $coverage['orders']['source']) : __('n/a', 'million-dollar-script'),
                    'target' => isset($coverage['orders']['placed']) ? number_format_i18n((int) $coverage['orders']['placed']) : __('n/a', 'million-dollar-script'),
                    'explained' => isset($coverage['orders']['reported']) ? number_format_i18n((int) $coverage['orders']['reported']) : __('n/a', 'million-dollar-script'),
                    'short' => absint($coverage['orders']['unexplained'] ?? 0),
                ],
                [
                    'label' => __('Ads with an image in the old data that reached this grid, and ads showing an image', 'million-dollar-script'),
                    'source' => isset($coverage['images']['expected']) ? number_format_i18n((int) $coverage['images']['expected']) : __('n/a', 'million-dollar-script'),
                    'target' => isset($coverage['images']['attached']) ? number_format_i18n((int) $coverage['images']['attached']) : __('n/a', 'million-dollar-script'),
                    'short' => absint($coverage['images']['short'] ?? 0),
                ],
                [
                    'label' => __('Placements inside the grid picture', 'million-dollar-script'),
                    'source' => isset($coverage['canvas']['placements']) ? number_format_i18n((int) $coverage['canvas']['placements']) : __('n/a', 'million-dollar-script'),
                    'target' => isset($coverage['canvas']['outside']) ? (string) (absint($coverage['canvas']['placements']) - absint($coverage['canvas']['outside'])) : __('n/a', 'million-dollar-script'),
                    'short' => absint($coverage['canvas']['outside'] ?? 0),
                ],
            ];
            ?>
            <?php foreach ($coverage_rows as $coverage_row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $coverage_row['label']); ?></td>
                    <td><?php echo esc_html((string) $coverage_row['source']); ?></td>
                    <td><?php echo esc_html((string) $coverage_row['target']); ?></td>
                    <td><?php echo esc_html((string) ($coverage_row['explained'] ?? __('n/a', 'million-dollar-script'))); ?></td>
                    <td>
                        <?php
                        echo esc_html(
                            $coverage_row['short'] > 0
                                ? sprintf(
                                    /* translators: %d: number of records without their counterpart. */
                                    _n('%d missing', '%d missing', (int) $coverage_row['short'], 'million-dollar-script'),
                                    number_format_i18n((int) $coverage_row['short'])
                                )
                                : __('Complete', 'million-dollar-script')
                        );
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!empty($coverage['canvas']['examples'])) : ?>
        <p>
            <?php esc_html_e('Placements outside the grid:', 'million-dollar-script'); ?>
            <?php
            $examples = [];
            foreach ((array) $coverage['canvas']['examples'] as $example) {
                $examples[] = sprintf(
                    /* translators: 1: placement id, 2: horizontal position, 3: vertical position, 4: grid width, 5: grid height. */
                    __('#%1$d at %2$d,%3$d in a grid %4$d wide and %5$d tall', 'million-dollar-script'),
                    (int) ($example['placement_id'] ?? 0),
                    (int) ($example['x'] ?? 0),
                    (int) ($example['y'] ?? 0),
                    (int) ($example['grid_width'] ?? 0),
                    (int) ($example['grid_height'] ?? 0)
                );
            }
            echo esc_html(implode('. ', $examples) . '.');
            ?>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php
$database_rejections = is_array($database_rejections ?? null) ? $database_rejections : [];
?>
<?php if ([] !== $database_rejections) : ?>
    <h3><?php esc_html_e('What the database could not store', 'million-dollar-script'); ?></h3>
    <?php
    if ([] !== $database_rejections) :
        $kind_labels = [
            'shortened' => __('Too long for the column: the database kept the beginning of the value', 'million-dollar-script'),
            'dropped characters' => __('The column character set cannot hold some characters, so the database removed them', 'million-dollar-script'),
            'out of range' => __('A number was outside the range the column allows', 'million-dollar-script'),
            'not stored' => __('The value did not fit the column, so the row was not written', 'million-dollar-script'),
        ];
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Where', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('What happened', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Values', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($database_rejections as $rejection) : ?>
                    <?php
                    $rejection_kind = (string) ($rejection['kind'] ?? '');
                    $rejection_column = (string) ($rejection['column'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <?php
                            echo esc_html(
                                (string) ($rejection['table'] ?? '') . ('' !== $rejection_column ? '.' . $rejection_column : '')
                            );
                            ?>
                        </td>
                        <td><?php echo esc_html($kind_labels[$rejection_kind] ?? $rejection_kind); ?></td>
                        <td><?php echo esc_html(number_format_i18n(absint($rejection['count'] ?? 0))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><?php esc_html_e('Each record is named under Reconciliation Details with the message the database gave — under Repaired when the row was written with text missing, under Skipped when it was not written at all.', 'million-dollar-script'); ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($order_status_rows)) : ?>
    <h3><?php esc_html_e('Orders by State', 'million-dollar-script'); ?></h3>
    <p><?php esc_html_e('What the migrated orders look like on this site now.', 'million-dollar-script'); ?></p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('State', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Orders', 'million-dollar-script'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ((array) $order_status_rows as $order_status_row) : ?>
                <tr>
                    <td><?php echo esc_html((string) ($order_status_row['label'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) ($order_status_row['count'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ('' !== (string) ($order_status_note ?? '')) : ?>
        <p><?php echo esc_html((string) $order_status_note); ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($settings_outcomes)) : ?>
    <h3><?php esc_html_e('How This Site Handles Orders', 'million-dollar-script'); ?></h3>
    <?php foreach ((array) $settings_outcomes as $settings_outcome) : ?>
        <?php
        $settings_note = is_array($settings_outcome) ? (string) ($settings_outcome['note'] ?? '') : (string) $settings_outcome;
        if ('' === $settings_note) {
            continue;
        }
        ?>
        <p><?php echo esc_html($settings_note); ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($page_outcomes)) : ?>
    <h3><?php esc_html_e('Page Outcomes', 'million-dollar-script'); ?></h3>
    <p><?php esc_html_e('How each detected Million Dollar Script 2 page was handled during the import.', 'million-dollar-script'); ?></p>
    <?php
    $outcome_labels = [
        'updated_in_place' => __('Updated in place', 'million-dollar-script'),
        'replaced' => __('Replaced (content had been modified)', 'million-dollar-script'),
        'created_new' => __('Created a new page (original kept)', 'million-dollar-script'),
        'left_unchanged' => __('Left unchanged (content had been modified)', 'million-dollar-script'),
    ];
    $page_type_labels = \MillionDollarScript\V3\Pages\PageRepository::labels();
    ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Page', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Role', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Outcome', 'million-dollar-script'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($page_outcomes as $outcome) : ?>
                <?php
                $outcome_key = (string) ($outcome['outcome'] ?? '');
                $outcome_type = (string) ($outcome['type'] ?? '');
                ?>
                <tr>
                    <td><?php echo esc_html((string) ($outcome['title'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) ($page_type_labels[$outcome_type] ?? $outcome_type)); ?></td>
                    <td><?php echo esc_html((string) ($outcome_labels[$outcome_key] ?? $outcome_key)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($repairs) || !empty($skipped)) : ?>
    <h3><?php esc_html_e('Reconciliation Details', 'million-dollar-script'); ?></h3>
    <p><?php esc_html_e('Source identifiers are shown without customer or advertiser details so incomplete imports can be diagnosed safely.', 'million-dollar-script'); ?></p>

    <?php if (!empty($repairs)) : ?>
        <h4><?php esc_html_e('Repaired', 'million-dollar-script'); ?></h4>
        <ul>
            <?php foreach ($repairs as $repair) : ?>
                <li>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: entity type, 2: source identifier, 3: repair summary. */
                        __('%1$s %2$s: %3$s', 'million-dollar-script'),
                        (string) ($repair['entity'] ?? __('record', 'million-dollar-script')),
                        (string) ($repair['source_id'] ?? ''),
                        (string) ($repair['reason'] ?? '')
                    ));
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($skipped)) : ?>
        <h4><?php esc_html_e('Skipped', 'million-dollar-script'); ?></h4>
        <ul>
            <?php foreach ($skipped as $skip) : ?>
                <li>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: entity type, 2: source identifier, 3: skip reason. */
                        __('%1$s %2$s: %3$s', 'million-dollar-script'),
                        (string) ($skip['entity'] ?? __('record', 'million-dollar-script')),
                        (string) ($skip['source_id'] ?? ''),
                        (string) ($skip['reason'] ?? '')
                    ));
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
