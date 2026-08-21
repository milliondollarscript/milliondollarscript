<?php
/**
 * Setup capability selector.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-setup-extension-selector" data-mds3-setup-selector>
    <h3><?php esc_html_e('Site capabilities', 'million-dollar-script'); ?></h3>
    <p class="description"><?php esc_html_e('Choose the Million Dollar Script capabilities this site should use. The classic grid is selected by default, while paid extensions and extensions that use external services are never installed silently.', 'million-dollar-script'); ?></p>
    <div class="mds3-setup-extension-live-notice" data-mds3-setup-notice hidden></div>
    <div class="mds3-setup-extension-grid">
        <?php foreach ($choices as $choice) : ?>
            <?php $item = $choice['item']; ?>
            <article class="<?php echo esc_attr($choice['classes']); ?>" data-mds3-setup-choice data-slug="<?php echo esc_attr($choice['slug']); ?>" data-name="<?php echo esc_attr((string) ($item['name'] ?? $choice['slug'])); ?>" data-provides="<?php echo esc_attr($choice['provides_json']); ?>" data-requires="<?php echo esc_attr($choice['requires_json']); ?>" data-conflicts="<?php echo esc_attr($choice['conflicts_json']); ?>" data-base-locked="<?php echo esc_attr($choice['base_locked'] ? '1' : '0'); ?>">
                <label>
                    <?php if ($choice['selected'] && $choice['locked']) : ?>
                        <span class="mds3-setup-extension-selected-mark dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php endif; ?>
                    <input class="<?php echo $choice['selected'] && $choice['locked'] ? 'screen-reader-text' : ''; ?>" type="checkbox" name="mds3_setup_extensions[]" value="<?php echo esc_attr($choice['slug']); ?>"<?php checked($choice['selected'] || $choice['locked'], true); ?><?php disabled($choice['locked'], true); ?> data-mds3-setup-checkbox />
                    <?php if ($choice['locked']) : ?>
                        <input type="hidden" name="mds3_setup_extensions[]" value="<?php echo esc_attr($choice['slug']); ?>" data-mds3-setup-hidden />
                    <?php else : ?>
                        <input type="hidden" name="mds3_setup_extensions[]" value="<?php echo esc_attr($choice['slug']); ?>" data-mds3-setup-hidden disabled />
                    <?php endif; ?>
                    <span>
                        <strong><?php echo esc_html((string) ($item['name'] ?? $choice['slug'])); ?></strong>
                        <small><?php echo esc_html($choice['status_label']); ?></small>
                    </span>
                </label>
                <?php if ($choice['lock_reason']) : ?>
                    <p class="mds3-setup-extension-lock-reason" data-mds3-lock-reason><?php echo esc_html($choice['lock_reason']); ?></p>
                <?php else : ?>
                    <p class="mds3-setup-extension-lock-reason" data-mds3-lock-reason hidden></p>
                <?php endif; ?>
                <?php if (!empty($item['description'])) : ?>
                    <p><?php echo esc_html((string) $item['description']); ?></p>
                <?php endif; ?>
                <?php if (!empty($choice['meta_rows'])) : ?>
                    <dl>
                        <?php foreach ($choice['meta_rows'] as $row) : ?>
                            <dt><?php echo esc_html($row[0]); ?></dt>
                            <dd><?php echo esc_html($row[1]); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
