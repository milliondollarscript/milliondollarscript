<?php
/**
 * MDS3 API access admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$scope_options = is_array($scope_options ?? null) ? $scope_options : [];
$default_scopes = ['core.grid.read', 'core.extension.read'];
$core_scope_options = [];
$extension_scope_options = [];
foreach ($scope_options as $scope_option) {
    $scope = (string) ($scope_option['scope'] ?? '');
    if (0 === strpos($scope, 'core.')) {
        $core_scope_options[] = $scope_option;
    } else {
        $extension_scope_options[] = $scope_option;
    }
}
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('API Access', 'million-dollar-script'); ?></h1>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('API settings saved.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['revoked'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('API key revoked.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['rotated'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('API key rotated.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['api_error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['api_error']))); ?></p></div>
    <?php endif; ?>

    <?php if ($created_key) : ?>
        <div class="notice notice-success inline mds3-api-created-key">
            <p>
                <strong>
                    <?php echo esc_html(!empty($_GET['rotated']) ? __('Rotated API key', 'million-dollar-script') : __('New API key', 'million-dollar-script')); ?>
                </strong>
            </p>
            <p><code><?php echo esc_html((string) $created_key); ?></code></p>
            <p><?php esc_html_e('This key is shown once. Store it in the app, automation, or LLM tool that will use it.', 'million-dollar-script'); ?></p>
        </div>
    <?php endif; ?>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Create scoped key', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Use scoped keys for apps, automations, and LLM tools that need controlled access to Million Dollar Script APIs.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <p class="description"><?php esc_html_e('API clients can send the key as a Bearer token or with the X-Million-Dollar-Script-API-Key header.', 'million-dollar-script'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-api-key-form">
            <?php wp_nonce_field('mds3_create_api_key'); ?>
            <input type="hidden" name="action" value="mds3_create_api_key" />
            <?php
            $this->field('name', __('Key name', 'million-dollar-script'), 'text', '', '', [], [
                'autocomplete' => 'off',
            ]);
            ?>
            <fieldset class="mds3-api-scope-fieldset">
                <legend><?php esc_html_e('Access scopes', 'million-dollar-script'); ?></legend>
                <p class="description"><?php esc_html_e('Select only the access this key needs. Read-only grid and extension access is selected by default.', 'million-dollar-script'); ?></p>
                <div class="mds3-api-scope-grid">
                    <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/api-scope-options.php', [
                        'default_scopes' => $default_scopes,
                        'scope_options' => $core_scope_options,
                    ], $this); ?>
                </div>
                <?php if ($extension_scope_options) : ?>
                    <details class="mds3-api-extension-scopes">
                        <summary>
                            <?php esc_html_e('Extension scopes', 'million-dollar-script'); ?>
                            <span><?php echo esc_html(sprintf(
                                /* translators: %d: number of extension API scopes. */
                                _n('%d scope', '%d scopes', count($extension_scope_options), 'million-dollar-script'),
                                count($extension_scope_options)
                            )); ?></span>
                        </summary>
                        <p class="description"><?php esc_html_e('These scopes were registered by active extensions. Select them only when the connected client uses those extension APIs.', 'million-dollar-script'); ?></p>
                        <div class="mds3-api-scope-grid">
                            <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/api-scope-options.php', [
                                'default_scopes' => $default_scopes,
                                'scope_options' => $extension_scope_options,
                            ], $this); ?>
                        </div>
                    </details>
                <?php endif; ?>
                <details class="mds3-api-custom-scopes">
                    <summary><?php esc_html_e('Advanced or extension scopes', 'million-dollar-script'); ?></summary>
                    <label for="mds3_api_custom_scopes"><?php esc_html_e('Additional scopes', 'million-dollar-script'); ?></label>
                    <input id="mds3_api_custom_scopes" type="text" name="custom_scopes" value="" class="regular-text" aria-describedby="mds3-api-custom-scopes-description" autocomplete="off" />
                    <p id="mds3-api-custom-scopes-description" class="description"><?php esc_html_e('Enter extension-provided scopes separated by spaces or commas. Wildcards such as core.* grant broad current and future access and should be avoided.', 'million-dollar-script'); ?></p>
                </details>
            </fieldset>
            <?php $this->field('rate_limit_per_hour', __('Hourly rate limit', 'million-dollar-script'), 'number', 120); ?>
            <?php submit_button(__('Create API key', 'million-dollar-script'), 'primary', '', false); ?>
        </form>
    </section>

    <section class="mds3-card">
        <h2><?php esc_html_e('Active keys', 'million-dollar-script'); ?></h2>
        <?php $this->api_keys_table($active_keys); ?>
    </section>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Endpoint policies', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Policies can be tightened per endpoint. Million Dollar Script will not save a policy that is weaker than the endpoint minimum.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_save_api_policies'); ?>
            <input type="hidden" name="action" value="mds3_save_api_policies" />
            <?php $this->api_policies_table($endpoints, $levels); ?>
            <?php submit_button(__('Save endpoint policies', 'million-dollar-script'), 'secondary', '', false); ?>
        </form>
    </section>

    <section class="mds3-card">
        <h2><?php esc_html_e('Recent API audit log', 'million-dollar-script'); ?></h2>
        <?php $this->api_audit_table($audit_logs); ?>
    </section>
</div>
