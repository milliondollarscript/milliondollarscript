<?php
/**
 * API endpoint policy table.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<table class="widefat striped mds3-api-table">
    <thead>
        <tr>
            <th><?php esc_html_e('Endpoint', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Scope', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Minimum', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Policy', 'million-dollar-script'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($endpoints as $endpoint) : ?>
            <?php
            $id = sanitize_key((string) ($endpoint['id'] ?? ''));
            if (!$id) {
                continue;
            }

            $methods = implode(', ', array_map('strtoupper', array_map('strval', (array) ($endpoint['methods'] ?? []))));
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html((string) ($endpoint['id'] ?? '')); ?></strong><br>
                    <code><?php echo esc_html(trim($methods . ' ' . $this->api_route_label((string) ($endpoint['route'] ?? '')))); ?></code>
                    <p class="description"><?php echo esc_html((string) ($endpoint['description'] ?? '')); ?></p>
                </td>
                <td><code><?php echo esc_html((string) ($endpoint['scope'] ?? '')); ?></code></td>
                <td><code><?php echo esc_html((string) ($endpoint['minimum_security_level'] ?? '')); ?></code></td>
                <td>
                    <select name="policies[<?php echo esc_attr($id); ?>]">
                        <?php foreach ($levels as $level) : ?>
                            <?php $is_weaker = $this->api_security_level_is_weaker($level, (string) ($endpoint['minimum_security_level'] ?? 'api_key_write')); ?>
                            <option value="<?php echo esc_attr($level); ?>"<?php selected((string) ($endpoint['security_level'] ?? ''), $level); ?><?php disabled($is_weaker); ?>>
                                <?php echo esc_html($this->api_security_level_label($level)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
