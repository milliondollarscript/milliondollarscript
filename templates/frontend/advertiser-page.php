<?php
/** @var array<string,mixed> $advertiser_page */
if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="mds-advertiser-page" data-mds-placement-id="<?php echo esc_attr((string) ($advertiser_page['placement_id'] ?? 0)); ?>">
    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/before', $advertiser_page); ?>
    <header class="mds-advertiser-page__header">
        <p class="mds-advertiser-page__eyebrow"><?php esc_html_e('Featured advertiser', 'million-dollar-script'); ?></p>
    </header>

    <?php if (!empty($advertiser_page['image']['url'])) : ?>
        <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/before-image', $advertiser_page); ?>
        <figure class="mds-advertiser-page__image">
            <img src="<?php echo esc_url((string) $advertiser_page['image']['url']); ?>" width="<?php echo esc_attr((string) absint($advertiser_page['image']['width'] ?? 0)); ?>" height="<?php echo esc_attr((string) absint($advertiser_page['image']['height'] ?? 0)); ?>" alt="<?php echo esc_attr((string) ($advertiser_page['alt_text'] ?? '')); ?>" />
        </figure>
        <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/after-image', $advertiser_page); ?>
    <?php endif; ?>

    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/before-content', $advertiser_page); ?>
    <?php if (!empty($advertiser_page['popup_text_html'])) : ?>
        <div class="mds-advertiser-page__content"><?php echo wp_kses_post((string) $advertiser_page['popup_text_html']); ?></div>
    <?php endif; ?>
    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/after-content', $advertiser_page); ?>

    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/before-actions', $advertiser_page); ?>
    <div class="mds-advertiser-page__actions">
        <?php if (!empty($advertiser_page['advertiser_url'])) : ?>
            <a class="mds-advertiser-page__button" href="<?php echo esc_url((string) $advertiser_page['advertiser_url']); ?>" target="<?php echo esc_attr((string) ($advertiser_page['advertiser_link_target'] ?? '_blank')); ?>"<?php echo '_blank' === ($advertiser_page['advertiser_link_target'] ?? '') ? ' rel="noopener noreferrer sponsored"' : ' rel="sponsored"'; ?>><?php esc_html_e('Visit advertiser', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if (!empty($advertiser_page['grid']['url'])) : ?>
            <a class="mds-advertiser-page__grid-link" href="<?php echo esc_url((string) $advertiser_page['grid']['url']); ?>"><?php echo esc_html(sprintf(__('View %s', 'million-dollar-script'), (string) ($advertiser_page['grid']['title'] ?? __('grid', 'million-dollar-script')))); ?></a>
        <?php endif; ?>
    </div>
    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/after-actions', $advertiser_page); ?>
    <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/advertiser/page/after', $advertiser_page); ?>
</article>
