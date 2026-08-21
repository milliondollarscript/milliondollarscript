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
