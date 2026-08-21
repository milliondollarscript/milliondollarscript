<?php
/**
 * Active API keys table.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!$keys) : ?>
    <p><?php esc_html_e('No active API keys yet.', 'million-dollar-script'); ?></p>
<?php else : ?>
    <table class="widefat striped mds3-api-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Prefix', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Scopes', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Rate limit', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Last used', 'million-dollar-script'); ?></th>
                <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($keys as $key) : ?>
                <?php $key_id = absint($key['id'] ?? 0); ?>
                <tr>
                    <td><?php echo esc_html((string) ($key['name'] ?? '')); ?></td>
                    <td><code><?php echo esc_html((string) ($key['key_prefix'] ?? '')); ?></code></td>
                    <td><?php echo esc_html(implode(', ', (array) ($key['scopes'] ?? []))); ?></td>
                    <td><?php echo esc_html(number_format_i18n(absint($key['rate_limit_per_hour'] ?? 0))); ?></td>
                    <td><?php echo esc_html((string) (($key['last_used_at'] ?? '') ?: '-')); ?></td>
                    <td>
                        <?php $this->inline_post_button('mds3_rotate_api_key', 'mds3_rotate_api_key_' . $key_id, ['key_id' => $key_id], __('Rotate', 'million-dollar-script'), 'button-small', __('Rotate this API key? The old secret will stop working immediately.', 'million-dollar-script')); ?>
                        <?php $this->inline_post_button('mds3_revoke_api_key', 'mds3_revoke_api_key_' . $key_id, ['key_id' => $key_id], __('Revoke', 'million-dollar-script'), 'button-small', __('Revoke this API key? Apps and automations using it will lose access immediately.', 'million-dollar-script')); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
