<?php
/**
 * Settings page-role map.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<table class="widefat striped mds3-settings-map">
    <thead>
        <tr>
            <th><?php esc_html_e('Page Role', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Current Page', 'million-dollar-script'); ?></th>
            <th><?php esc_html_e('Shortcode', 'million-dollar-script'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo esc_html($row['label']); ?></td>
                <td>
                    <?php if ($row['post_id'] && $row['title']) : ?>
                        <a href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title']); ?></a>
                        <code>#<?php echo esc_html($row['post_id']); ?></code>
                        <a href="<?php echo esc_url($row['permalink']); ?>"><?php esc_html_e('View', 'million-dollar-script'); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Not assigned', 'million-dollar-script'); ?>
                    <?php endif; ?>
                </td>
                <td><code><?php echo esc_html($row['shortcode']); ?></code></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
