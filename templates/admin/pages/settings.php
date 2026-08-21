<?php
/**
 * MDS3 settings admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings_transfer_tab = 'settings-transfer';
$settings_transfer_active = $active_tab === $settings_transfer_tab
    || !empty($_GET['settings_import_preview'])
    || !empty($_GET['settings_imported'])
    || !empty($_GET['settings_import_error'])
    || (!empty($settings_import_preview) && is_array($settings_import_preview));

if ($settings_transfer_active) {
    $active_tab = $settings_transfer_tab;
}
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Settings', 'million-dollar-script'); ?></h1>
    <section class="mds3-card" data-mds3-tab-container>
        <?php if (!empty($_GET['updated'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Settings saved.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['settings_error'])) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['settings_error']))); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['settings_imported'])) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Settings imported. A backup of the previous settings was saved.', 'million-dollar-script'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($_GET['settings_import_error'])) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['settings_import_error']))); ?></p></div>
        <?php endif; ?>

        <?php if (!$grid_enabled) : ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Classic Pixel Grid is disabled. Grid sales, order, upload, display, and rendering settings are hidden until you enable it from Setup.', 'million-dollar-script'); ?></p>
                <p><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Open setup', 'million-dollar-script'); ?></a></p>
            </div>
        <?php endif; ?>

            <div class="mds3-settings-tabs" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'million-dollar-script'); ?>">
                <?php foreach ($tabs as $group) : ?>
                    <?php
                    $tab_id = 'settings-' . sanitize_title($group);
                    $is_active = $active_tab === $tab_id;
                    ?>
                    <button type="button" role="tab" class="<?php echo esc_attr($is_active ? 'is-active' : ''); ?>" aria-selected="<?php echo esc_attr($is_active ? 'true' : 'false'); ?>" data-settings-tab="<?php echo esc_attr($tab_id); ?>">
                        <?php echo esc_html($group); ?>
                    </button>
                <?php endforeach; ?>

                <?php foreach ($extra_tabs as $tab_id => $label) : ?>
                    <?php
                    $tab_id = sanitize_key($tab_id);
                    if (!$tab_id) {
                        continue;
                    }
                    $is_active = $active_tab === $tab_id;
                    ?>
                    <button type="button" role="tab" class="<?php echo esc_attr($is_active ? 'is-active' : ''); ?>" aria-selected="<?php echo esc_attr($is_active ? 'true' : 'false'); ?>" data-settings-tab="<?php echo esc_attr($tab_id); ?>">
                        <?php echo esc_html($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mds3-settings-panels">
                <form method="post" class="mds3-settings-save-form <?php echo esc_attr($settings_transfer_active ? 'is-transfer-active' : ''); ?>" data-settings-save-form action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds3_save_settings'); ?>
                    <input type="hidden" name="action" value="mds3_save_settings" />
                <?php foreach ($groups as $group => $fields) : ?>
                    <?php
                    $tab_id = 'settings-' . sanitize_title($group);
                    $group_hook = sanitize_key(sanitize_title((string) $group));
                    ?>
                    <section class="mds3-settings-panel <?php echo esc_attr($active_tab === $tab_id ? 'is-active' : ''); ?>" data-settings-panel="<?php echo esc_attr($tab_id); ?>" data-settings-group="<?php echo esc_attr($group_hook); ?>">
                        <h2><?php echo esc_html($group); ?></h2>
                        <?php if ('order-emails' === $group_hook) : ?>
                            <div class="mds3-settings-intro">
                                <p><?php esc_html_e('Order emails are sent through WordPress mail. Use an SMTP or mail logging plugin if this site needs delivery routing, retries, or message logs.', 'million-dollar-script'); ?></p>
                                <p><?php esc_html_e('Common placeholders:', 'million-dollar-script'); ?></p>
                                <div class="mds3-placeholder-list" aria-label="<?php esc_attr_e('Common email placeholders', 'million-dollar-script'); ?>">
                                    <?php foreach (['%ORDER_ID%', '%PIXEL_COUNT%', '%PRICE%', '%STATUS%', '%MANAGE_URL%', '%SITE_NAME%', '%SITE_URL%', '%SITE_CONTACT_EMAIL%', '%DAYS_LEFT%', '%EXPIRES_AT%'] as $placeholder) : ?>
                                        <code><?php echo esc_html($placeholder); ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mds3-settings-form">
                            <?php $open_section = false; ?>
                            <?php $current_section = ''; ?>
                            <?php foreach ($fields as $field) : ?>
                                <?php
                                $field_section = trim((string) ($field['section'] ?? ''));
                                if ($field_section !== $current_section) {
                                    if ($open_section) {
                                        echo '</div>';
                                        $open_section = false;
                                    }
                                    $current_section = $field_section;
                                    if ('' !== $field_section) {
                                        echo '<div class="mds3-settings-field-section">';
                                        echo '<h3>' . esc_html($field_section) . '</h3>';
                                        $open_section = true;
                                    }
                                }
                                $value = $settings[$field['key']] ?? ($field['default'] ?? '');
                                if ('select' === ($field['type'] ?? '')) {
                                    $value = \MillionDollarScript\V3\Settings\SettingsSchema::sanitize($field['key'], $value);
                                }
                                $locked_currency_field = $provider_currency_locked && in_array($field['key'], ['currency', 'currency-symbol'], true);
                                $rendered_default_field = $this->field($field['key'], $field['label'], $field['type'], $value, 'number' === ($field['type'] ?? '') ? '1' : '', $field['options'] ?? [], [
                                    'help' => $field['help'] ?? \MillionDollarScript\V3\Settings\SettingsSchema::help($field['key']),
                                    'description' => $field['description'] ?? '',
                                    'disabled' => $locked_currency_field,
                                    'docs' => $field['docs'] ?? '',
                                    'hidden_value' => $locked_currency_field ? $value : null,
                                    'wide' => !empty($field['wide']),
                                ]);
                                if ('delete_data_on_uninstall' === $field['key']) {
                                    \MillionDollarScript\V3\Support\Template::display('admin/partials/extension-cleanup-policy.php', [
                                        'cleanup_extensions' => $cleanup_extensions ?? [],
                                    ]);
                                }
                                if ('popup-template' === $field['key'] && $rendered_default_field) {
                                    ?>
                                    <div class="mds3-popup-layout-preview" data-mds3-popup-layout-preview data-built-in-label="<?php echo esc_attr__('Built-in layout', 'million-dollar-script'); ?>" data-custom-label="<?php echo esc_attr__('Custom layout', 'million-dollar-script'); ?>">
                                        <div class="mds3-popup-layout-preview-heading">
                                            <strong><?php esc_html_e('Popup layout preview', 'million-dollar-script'); ?></strong>
                                            <span data-mds3-popup-preview-mode></span>
                                        </div>
                                        <div class="mds3-popup-layout-preview-card" aria-live="polite">
                                            <span data-mds3-popup-preview-part="image"><?php esc_html_e('Placement image', 'million-dollar-script'); ?></span>
                                            <strong data-mds3-popup-preview-part="alt_text"><?php esc_html_e('Advertiser title', 'million-dollar-script'); ?></strong>
                                            <span data-mds3-popup-preview-part="url"><?php esc_html_e('example.com', 'million-dollar-script'); ?></span>
                                            <span data-mds3-popup-preview-part="text"><?php esc_html_e('Popup text', 'million-dollar-script'); ?></span>
                                            <span data-mds3-popup-preview-part="custom"><?php esc_html_e('Custom template content', 'million-dollar-script'); ?></span>
                                            <span class="mds3-popup-layout-preview-fallback" data-mds3-popup-preview-fallback hidden><?php esc_html_e('The built-in accessible popup will be used when a custom layout has no displayable content.', 'million-dollar-script'); ?></span>
                                        </div>
                                        <p><?php esc_html_e('This setting changes popup presentation only. Configure the advertiser URL and popup text inputs under Orders & Uploads.', 'million-dollar-script'); ?></p>
                                    </div>
                                    <?php
                                }
                                ?>
                            <?php endforeach; ?>
                            <?php if ($open_section) : ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/group/fields', $group_hook, $group, $settings);
                            \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/group/' . $group_hook, $settings, $group);
                            if ('rendering' === $group_hook && !$this->imagegrid_extension_active()) {
                                $this->imagegrid_settings_prompt();
                            }
                            ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="mds3-settings-panel <?php echo esc_attr('upgrade' === $active_tab ? 'is-active' : ''); ?>" data-settings-panel="upgrade">
                    <h2><?php esc_html_e('Upgrade Compatibility', 'million-dollar-script'); ?></h2>
                    <div class="mds3-settings-form">
                        <?php
                        $this->field('legacy_mds2_source_prefix', __('Last Million Dollar Script 2 Source Prefix', 'million-dollar-script'), 'text', $settings['legacy_mds2_source_prefix'] ?? '', '', [], [
                            'help' => __('Stores the source table prefix used by the most recent Million Dollar Script 2 migration.', 'million-dollar-script'),
                            'docs' => 'mds2-migration',
                        ]);
                        ?>
                    </div>
                    <?php if (!empty($hidden_compatibility_fields) && is_array($hidden_compatibility_fields)) : ?>
                        <div class="mds3-settings-callout mds3-settings-compatibility-summary">
                            <h3><?php esc_html_e('Compatibility settings preserved', 'million-dollar-script'); ?></h3>
                            <p><?php esc_html_e('These values are still recognized for Million Dollar Script 2 migration, settings import/export, or planned parity, but they are not shown as active controls until they affect the current runtime.', 'million-dollar-script'); ?></p>
                            <ul>
                                <?php foreach ($hidden_compatibility_fields as $field) : ?>
                                    <li>
                                        <strong><?php echo esc_html((string) ($field['label'] ?? $field['key'] ?? '')); ?></strong>
                                        <span><?php echo esc_html((string) ($field['classification_label'] ?? '')); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php $this->settings_page_map(); ?>
                </section>

                <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/panels', $settings); ?>
                <?php submit_button(__('Save settings', 'million-dollar-script')); ?>
                </form>
                <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/settings/after-form', $settings); ?>

                <section class="mds3-settings-panel mds3-settings-transfer <?php echo esc_attr($settings_transfer_active ? 'is-active' : ''); ?>" data-settings-panel="<?php echo esc_attr($settings_transfer_tab); ?>">
        <h2><?php esc_html_e('Import / Export Settings', 'million-dollar-script'); ?></h2>
        <p><?php esc_html_e('Export a portable JSON backup of the settings on this page, or preview an import before applying changes.', 'million-dollar-script'); ?></p>

        <div class="mds3-settings-transfer-grid">
            <div>
                <h3><?php esc_html_e('Export', 'million-dollar-script'); ?></h3>
                <p><?php esc_html_e('Use this before changing a site, moving settings to another install, or testing a migration.', 'million-dollar-script'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds3_export_settings'); ?>
                    <input type="hidden" name="action" value="mds3_export_settings" />
                    <?php submit_button(__('Download settings JSON', 'million-dollar-script'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div>
                <h3><?php esc_html_e('Import', 'million-dollar-script'); ?></h3>
                <p><?php esc_html_e('Upload a settings JSON file to preview sanitized changes. Nothing is changed until you apply the preview.', 'million-dollar-script'); ?></p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds3_preview_settings_import'); ?>
                    <input type="hidden" name="action" value="mds3_preview_settings_import" />
                    <label class="screen-reader-text" for="mds3-settings-import-file"><?php esc_html_e('Settings import file', 'million-dollar-script'); ?></label>
                    <input id="mds3-settings-import-file" type="file" name="settings_file" accept="application/json,.json" required />
                    <?php submit_button(__('Preview import', 'million-dollar-script'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <?php if (!empty($settings_import_preview) && is_array($settings_import_preview)) : ?>
            <div class="mds3-settings-import-preview">
                <h3><?php esc_html_e('Import Preview', 'million-dollar-script'); ?></h3>
                <p>
                    <?php
                    printf(
                        /* translators: 1: importable setting count, 2: submitted setting count. */
                        esc_html__('%1$d of %2$d submitted settings can be imported.', 'million-dollar-script'),
                        absint($settings_import_preview['importable_count'] ?? 0),
                        absint($settings_import_preview['submitted_count'] ?? 0)
                    );
                    ?>
                </p>

                <?php if (!empty($settings_import_preview['changes']) && is_array($settings_import_preview['changes'])) : ?>
                    <table class="widefat striped mds3-settings-import-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Setting', 'million-dollar-script'); ?></th>
                                <th><?php esc_html_e('Current', 'million-dollar-script'); ?></th>
                                <th><?php esc_html_e('Imported', 'million-dollar-script'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings_import_preview['changes'] as $change) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($change['label'] ?? $change['key'] ?? ''); ?></strong><br><code><?php echo esc_html($change['key'] ?? ''); ?></code></td>
                                    <td><?php echo esc_html($change['current'] ?? ''); ?></td>
                                    <td><?php echo esc_html($change['next'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e('The import does not change any current settings.', 'million-dollar-script'); ?></p>
                <?php endif; ?>

                <?php if (!empty($settings_import_preview['unknown']) && is_array($settings_import_preview['unknown'])) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Some settings are not recognized on this site and will be skipped:', 'million-dollar-script'); ?></p>
                        <p><code><?php echo esc_html(implode(', ', $settings_import_preview['unknown'])); ?></code></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($settings_import_preview['rejected']) && is_array($settings_import_preview['rejected'])) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Some settings were rejected because their values are not safe for this site.', 'million-dollar-script'); ?></p>
                        <ul class="mds3-settings-import-rejections">
                            <?php foreach ($settings_import_preview['rejected'] as $rejected) : ?>
                                <li><strong><?php echo esc_html($rejected['label'] ?? $rejected['key'] ?? ''); ?>:</strong> <?php echo esc_html($rejected['message'] ?? ''); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="mds3-button-row">
                    <form method="post" class="mds3-inline-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds3_apply_settings_import'); ?>
                        <input type="hidden" name="action" value="mds3_apply_settings_import" />
                        <?php submit_button(__('Apply imported settings', 'million-dollar-script'), 'primary', 'submit', false); ?>
                    </form>
                    <form method="post" class="mds3-inline-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds3_clear_settings_import_preview'); ?>
                        <input type="hidden" name="action" value="mds3_clear_settings_import_preview" />
                        <?php submit_button(__('Discard preview', 'million-dollar-script'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
                </section>
            </div>
    </section>
</div>
