<?php
/**
 * Dashboard service cards.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-dashboard-services">
    <?php foreach ($cards as $card) : ?>
        <?php
        if (!is_array($card) || empty($card['url'])) {
            continue;
        }
        $button_class = !empty($card['primary']) ? 'button button-primary' : 'button';
        ?>
        <article>
            <h3><?php echo esc_html((string) ($card['title'] ?? '')); ?></h3>
            <p><?php echo esc_html((string) ($card['description'] ?? '')); ?></p>
            <a class="<?php echo esc_attr($button_class); ?>" href="<?php echo esc_url((string) $card['url']); ?>"<?php echo !empty($card['external']) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <?php echo esc_html((string) ($card['label'] ?? __('Open', 'million-dollar-script'))); ?>
            </a>
        </article>
    <?php endforeach; ?>
</div>
