<?php
/**
 * Dashboard navigation groups.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="mds3-dashboard-menu" aria-label="<?php esc_attr_e('Million Dollar Script dashboard menu', 'million-dollar-script'); ?>">
    <?php foreach ($groups as $group) : ?>
        <?php if (empty($group['items']) || !is_array($group['items'])) : ?>
            <?php continue; ?>
        <?php endif; ?>
        <section>
            <h2><?php echo esc_html($group['label'] ?? ''); ?></h2>
            <div>
                <?php foreach ($group['items'] as $item) : ?>
                    <?php
                    $item = is_array($item) ? $item : [];
                    $url = esc_url((string) ($item['url'] ?? ''));
                    if (!$url) {
                        continue;
                    }
                    $icon = sanitize_html_class((string) ($item['icon'] ?? 'dashicons-arrow-right-alt2'));
                    ?>
                    <a href="<?php echo esc_url($url); ?>"<?php echo '_blank' === ($item['target'] ?? '') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span><?php echo esc_html((string) ($item['label'] ?? '')); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</nav>
