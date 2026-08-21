<?php
/**
 * Extension cleanup-policy controls.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$cleanup_extensions = is_array($cleanup_extensions ?? null) ? $cleanup_extensions : [];
?>
<details class="mds3-extension-cleanup" data-mds3-extension-cleanup>
    <summary>
        <span><?php esc_html_e('Choose extension data to delete', 'million-dollar-script'); ?></span>
        <small>
            <?php
            printf(
                /* translators: %d: registered extension count. */
                esc_html(_n('%d extension registered', '%d extensions registered', count($cleanup_extensions), 'million-dollar-script')),
                count($cleanup_extensions)
            );
            ?>
        </small>
    </summary>
    <div class="mds3-extension-cleanup-body">
        <p class="mds3-extension-cleanup-warning">
            <strong><?php esc_html_e('Permanent deletion warning', 'million-dollar-script'); ?></strong>
            <?php esc_html_e('These selections apply only when Delete Data On Uninstall is set to Yes. Uninstalling a selected extension can permanently remove its settings, records, and generated data.', 'million-dollar-script'); ?>
        </p>
        <?php if ($cleanup_extensions) : ?>
            <div class="mds3-extension-cleanup-actions">
                <button type="button" class="button button-small" data-mds3-cleanup-select="all"><?php esc_html_e('Select all', 'million-dollar-script'); ?></button>
                <button type="button" class="button button-small" data-mds3-cleanup-select="none"><?php esc_html_e('Select none', 'million-dollar-script'); ?></button>
            </div>
            <fieldset class="mds3-extension-cleanup-list">
                <legend class="screen-reader-text"><?php esc_html_e('Extension uninstall cleanup selection', 'million-dollar-script'); ?></legend>
                <?php foreach ($cleanup_extensions as $extension) : ?>
                    <?php $extension_id = sanitize_key((string) ($extension['id'] ?? '')); ?>
                    <?php if ('' === $extension_id) : continue; endif; ?>
                    <label>
                        <input
                            type="checkbox"
                            name="mds3_extension_cleanup_included[]"
                            value="<?php echo esc_attr($extension_id); ?>"
                            <?php checked(\MillionDollarScript\Extensions\CleanupPolicy::is_included($extension_id)); ?>
                        />
                        <span>
                            <strong><?php echo esc_html((string) ($extension['label'] ?? $extension_id)); ?></strong>
                            <?php if (!empty($extension['description'])) : ?>
                                <small><?php echo esc_html((string) $extension['description']); ?></small>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php else : ?>
            <p><?php esc_html_e('No installed extensions have registered uninstall cleanup yet. Unknown extensions always keep their data.', 'million-dollar-script'); ?></p>
        <?php endif; ?>
    </div>
</details>
