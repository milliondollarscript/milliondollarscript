<?php
/**
 * Recent API audit log table.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!$logs) : ?>
    <p><?php esc_html_e('No API requests have been logged yet.', 'million-dollar-script'); ?></p>
<?php else : ?>
    <table class="widefat striped mds3-api-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Time', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Actor', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Route', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Scope', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Decision', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Message', 'million-dollar-script'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html((string) ($log['created_at'] ?? '')); ?></td>
                    <td>
                        <?php
                        $auth_type = sanitize_key((string) ($log['auth_type'] ?? 'api_key'));
                        if ('api_key' === $auth_type && absint($log['key_id'] ?? 0)) {
                            echo esc_html(sprintf(__('API key #%d', 'million-dollar-script'), absint($log['key_id'])));
                        } elseif (!empty($log['actor_ref'])) {
                            echo esc_html($auth_type . ' ' . substr((string) $log['actor_ref'], 0, 12));
                        } else {
                            echo esc_html($auth_type ?: '-');
                        }
                        ?>
                    </td>
                    <td><code><?php echo esc_html(trim((string) ($log['method'] ?? '') . ' ' . (string) ($log['route'] ?? ''))); ?></code></td>
                    <td><code><?php echo esc_html((string) ($log['scope'] ?? '')); ?></code></td>
                    <td><?php echo esc_html((string) ($log['decision'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) (($log['reason_code'] ?? '') ?: ($log['message'] ?? ''))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
