<?php
/**
 * WordPress integration for individual public advertiser pages.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class AdvertiserPages implements Component {

    private const SYNC_HOOK = 'mds3_sync_advertiser_pages';
    private const SYNC_CURSOR_OPTION = 'mds3_advertiser_page_sync_cursor';
    private const PREVIEW_TRANSIENT = 'mds3_advertiser_slug_preview_';

    public function register() {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'old_base_rewrite_rules'], 20);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'protect_and_redirect'], 1);
        add_filter('template_include', [$this, 'theme_template']);
        add_filter('the_content', [$this, 'content']);
        add_filter('wp_robots', [$this, 'robots']);
        add_filter('wp_sitemaps_post_types', [$this, 'sitemap_post_types']);
        add_filter('get_canonical_url', [$this, 'canonical'], 10, 2);
        add_action('wp_head', [$this, 'social_metadata'], 5);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('million-dollar-script/placement/saved', [$this, 'placement_saved']);
        add_action('million-dollar-script/placements/order/saved', [$this, 'order_placements_saved']);
        add_action('million-dollar-script/admin/settings/saved', [$this, 'settings_saved'], 10, 3);
        add_action('million-dollar-script/admin/settings/imported', [$this, 'settings_imported'], 10, 3);
        add_filter('million-dollar-script/admin/validate/settings', [$this, 'validate_settings'], 10, 3);
        add_action(self::SYNC_HOOK, [$this, 'synchronize_batch']);
        add_action('million-dollar-script/admin/settings/after-form', [$this, 'settings_tools']);
        add_action('admin_post_mds3_preview_advertiser_slugs', [$this, 'preview_slugs']);
        add_action('admin_post_mds3_apply_advertiser_slugs', [$this, 'apply_slugs']);
        add_action('admin_post_mds3_sync_advertiser_pages', [$this, 'start_sync']);
    }

    public function register_post_type() {
        $settings = AdvertiserPageUrls::settings();
        $enabled = AdvertiserPageUrls::enabled($settings);
        register_post_type(AdvertiserPageManager::POST_TYPE, [
            'labels' => [
                'name' => __('Advertiser pages', 'million-dollar-script'),
                'singular_name' => __('Advertiser page', 'million-dollar-script'),
            ],
            'public' => $enabled,
            'publicly_queryable' => $enabled,
            'exclude_from_search' => 'yes' === SettingsSchema::sanitize('exclude-from-search', $settings['exclude-from-search'] ?? 'no'),
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => $enabled ? ['slug' => AdvertiserPageUrls::base($settings), 'with_front' => false] : false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'delete_with_user' => false,
        ]);
    }

    public function query_vars($vars) {
        $vars[] = 'mds_advertiser_old_slug';

        return $vars;
    }

    public function old_base_rewrite_rules() {
        if (!AdvertiserPageUrls::enabled()) {
            return;
        }
        $current = AdvertiserPageUrls::base();
        foreach (AdvertiserPageUrls::base_history() as $base) {
            if ($base !== $current) {
                add_rewrite_rule('^' . preg_quote($base, '/') . '/([^/]+)/?$', 'index.php?mds_advertiser_old_slug=$matches[1]', 'top');
            }
        }
    }

    public function protect_and_redirect() {
        $old_slug = sanitize_title((string) get_query_var('mds_advertiser_old_slug'));
        if ('' !== $old_slug && AdvertiserPageUrls::enabled()) {
            $post = get_page_by_path($old_slug, OBJECT, AdvertiserPageManager::POST_TYPE);
            if (!$post) {
                $posts = get_posts([
                    'post_type' => AdvertiserPageManager::POST_TYPE,
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'meta_key' => '_wp_old_slug',
                    'meta_value' => $old_slug,
                ]);
                $post = $posts ? $posts[0] : null;
            }
            if ($post && $this->post_is_public($post->ID)) {
                wp_safe_redirect(get_permalink($post), 301, 'Million Dollar Script');
                exit;
            }
        }

        if (is_singular(AdvertiserPageManager::POST_TYPE) && !$this->post_is_public(get_queried_object_id())) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    public function theme_template($template) {
        if (!is_singular(AdvertiserPageManager::POST_TYPE) || !$this->post_is_public(get_queried_object_id())) {
            return $template;
        }
        $override = locate_template('million-dollar-script/single-advertiser.php');

        return $override ?: $template;
    }

    public function content($content) {
        if (!is_singular(AdvertiserPageManager::POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $placement_id = absint(get_post_meta(get_the_ID(), '_million_dollar_script_placement_id', true));

        return (new AdvertiserPageView())->render($placement_id);
    }

    public function robots($robots) {
        if (!is_singular(AdvertiserPageManager::POST_TYPE)) {
            return $robots;
        }
        $settings = AdvertiserPageUrls::settings();
        if (!AdvertiserPageUrls::enabled($settings) || 'yes' === SettingsSchema::sanitize('exclude-from-search', $settings['exclude-from-search'] ?? 'no')) {
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow'] = true;
        }

        return $robots;
    }

    public function sitemap_post_types($post_types) {
        $settings = AdvertiserPageUrls::settings();
        if (!AdvertiserPageUrls::enabled($settings) || 'yes' === SettingsSchema::sanitize('exclude-from-search', $settings['exclude-from-search'] ?? 'no')) {
            unset($post_types[AdvertiserPageManager::POST_TYPE]);
        }

        return $post_types;
    }

    public function canonical($canonical, $post) {
        if ($post instanceof \WP_Post && AdvertiserPageManager::POST_TYPE === $post->post_type && $this->post_is_public($post->ID)) {
            return $canonical ?: get_permalink($post);
        }

        return $canonical;
    }

    public function social_metadata() {
        if (!is_singular(AdvertiserPageManager::POST_TYPE) || !$this->post_is_public(get_queried_object_id())) {
            return;
        }
        $placement_id = absint(get_post_meta(get_queried_object_id(), '_million_dollar_script_placement_id', true));
        $model = (new AdvertiserPageView())->model($placement_id);
        if (!is_array($model)) {
            return;
        }
        $url = get_permalink(get_queried_object_id());
        $description = sanitize_text_field(wp_trim_words(wp_strip_all_tags((string) ($model['popup_text_html'] ?? '')), 30, '…'));
        $seo = $GLOBALS['mds_seo_basic'] ?? null;
        $social_handled = is_object($seo) && is_callable([$seo, 'output_allowed']) && $seo->output_allowed('social');
        $schema_handled = is_object($seo) && is_callable([$seo, 'output_allowed']) && $seo->output_allowed('schema');
        if (!$social_handled) {
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr((string) ($model['title'] ?? '')) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
            if ('' !== $description) {
                echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
            }
            if (!empty($model['image']['url'])) {
                echo '<meta property="og:image" content="' . esc_url((string) $model['image']['url']) . '" />' . "\n";
            }
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string) ($model['title'] ?? ''),
            'url' => $url,
        ];
        $schema = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/page/schema', $schema, $model);
        if (!$schema_handled) {
            if (is_array($schema) && !empty($schema['@type'])) {
                echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }
    }

    public function assets() {
        if (is_singular(AdvertiserPageManager::POST_TYPE)) {
            wp_enqueue_style('mds3-advertiser-page', MILLION_DOLLAR_SCRIPT_URL . 'assets/mds3/css/advertiser-page.css', [], MILLION_DOLLAR_SCRIPT_VERSION);
        }
    }

    public function placement_saved($placement) {
        $placement_id = is_array($placement) ? absint($placement['id'] ?? 0) : absint($placement);
        if ($placement_id) {
            (new AdvertiserPageManager())->synchronize($placement_id);
        }
    }

    public function order_placements_saved($order_id) {
        foreach ((new PlacementRepository())->for_order(absint($order_id)) as $placement) {
            (new AdvertiserPageManager())->synchronize(absint($placement['id'] ?? 0));
        }
    }

    public function settings_saved($saved, $raw, $current) {
        $this->after_settings_change(is_array($saved) ? $saved : [], is_array($current) ? $current : []);
    }

    public function settings_imported($saved, $preview, $current) {
        $this->after_settings_change(is_array($saved) ? $saved : [], is_array($current) ? $current : []);
    }

    public function validate_settings($errors, $raw, $current) {
        $errors = is_wp_error($errors) ? $errors : new \WP_Error();
        $base = trim((string) ($raw['mds-pixel-base'] ?? ''));
        if ('' === sanitize_title($base) || false !== strpos($base, '/') || false !== strpos($base, '?') || false !== strpos($base, '#')) {
            $errors->add('million_dollar_script_advertiser_base_invalid', __('Advertiser Page URL Base must be one non-empty URL segment without slashes, query strings, or fragments.', 'million-dollar-script'));
        } elseif ($this->base_conflicts(sanitize_title($base))) {
            $errors->add('million_dollar_script_advertiser_base_conflict', __('Advertiser Page URL Base conflicts with a reserved WordPress route, an existing page, or another public post type.', 'million-dollar-script'));
        }
        $pattern = trim((string) ($raw['mds-pixel-slug-structure'] ?? ''));
        if ('' === $pattern || strlen($pattern) > 180 || false !== strpos($pattern, '/') || false !== strpos($pattern, '?') || false !== strpos($pattern, '#')) {
            $errors->add('million_dollar_script_advertiser_pattern_invalid', __('Advertiser Page Slug Pattern must be 180 characters or fewer and cannot contain slashes, query strings, or fragments.', 'million-dollar-script'));
        }

        return $errors;
    }

    public function synchronize_batch() {
        if (get_transient('mds3_advertiser_page_sync_lock')) {
            return;
        }
        set_transient('mds3_advertiser_page_sync_lock', '1', 2 * MINUTE_IN_SECONDS);
        $cursor = absint(get_option(self::SYNC_CURSOR_OPTION, 0));
        $result = (new AdvertiserPageManager())->synchronize_batch(100, $cursor);
        if (!empty($result['complete'])) {
            delete_option(self::SYNC_CURSOR_OPTION);
            delete_transient('mds3_advertiser_page_sync_lock');
            return;
        }
        update_option(self::SYNC_CURSOR_OPTION, absint($result['last_id'] ?? 0), false);
        wp_schedule_single_event(time() + 10, self::SYNC_HOOK);
        delete_transient('mds3_advertiser_page_sync_lock');
    }

    public function start_sync() {
        $this->admin_guard('mds3_sync_advertiser_pages');
        delete_option(self::SYNC_CURSOR_OPTION);
        $this->synchronize_batch();
        $this->settings_redirect(['advertiser_sync_started' => 1]);
    }

    public function preview_slugs() {
        $this->admin_guard('mds3_preview_advertiser_slugs');
        $preview = (new AdvertiserPageManager())->preview_slug_migration();
        set_transient(self::PREVIEW_TRANSIENT . get_current_user_id(), $preview, 30 * MINUTE_IN_SECONDS);
        $this->settings_redirect(['advertiser_slug_preview' => 1]);
    }

    public function apply_slugs() {
        $this->admin_guard('mds3_apply_advertiser_slugs');
        if ('yes' !== sanitize_key(wp_unslash($_POST['confirm_slug_migration'] ?? ''))) {
            $this->settings_redirect(['advertiser_slug_error' => rawurlencode(__('Confirm the permanent URL migration before applying it.', 'million-dollar-script'))]);
        }
        $result = (new AdvertiserPageManager())->migrate_slugs(250, absint($_POST['after_id'] ?? 0));
        if (empty($result['complete'])) {
            set_transient(self::PREVIEW_TRANSIENT . get_current_user_id(), [
                'checked' => 0,
                'changed' => 1,
                'truncated' => false,
            ], 30 * MINUTE_IN_SECONDS);
        }
        update_option('mds3_flush_rewrite_rules', 'yes', false);
        $this->settings_redirect([
            'advertiser_slugs_changed' => absint($result['changed'] ?? 0),
            'advertiser_slugs_after' => empty($result['complete']) ? absint($result['last_id'] ?? 0) : 0,
        ]);
    }

    public function settings_tools($settings) {
        $preview = get_transient(self::PREVIEW_TRANSIENT . get_current_user_id());
        $legacy_template = locate_template('mds-pixel/single-mds-pixel.php');
        $continue_after = absint($_GET['advertiser_slugs_after'] ?? 0);
        ?>
        <div class="mds3-settings-callout mds3-advertiser-page-tools">
            <h3><?php esc_html_e('Advertiser page maintenance', 'million-dollar-script'); ?></h3>
            <?php if (!empty($_GET['advertiser_sync_started'])) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e('Advertiser page synchronization started. Additional batches will continue through WordPress cron.', 'million-dollar-script'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['advertiser_slugs_changed'])) : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html(sprintf(__('Changed %d advertiser page URLs in this batch.', 'million-dollar-script'), absint($_GET['advertiser_slugs_changed']))); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['advertiser_slug_error'])) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['advertiser_slug_error']))); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e('Synchronize placement pages after enabling this feature. Preview slug changes before applying a permanent URL migration; previous exact URLs are retained as 301 redirects.', 'million-dollar-script'); ?></p>
            <p><strong><?php esc_html_e('URL preview:', 'million-dollar-script'); ?></strong> <code><?php echo esc_html(home_url('/' . AdvertiserPageUrls::base(is_array($settings) ? $settings : []) . '/example-placement/')); ?></code></p>
            <div class="mds3-advertiser-page-preview" aria-label="<?php esc_attr_e('Safe advertiser page preview', 'million-dollar-script'); ?>">
                <span><?php esc_html_e('Featured advertiser', 'million-dollar-script'); ?></span>
                <strong><?php esc_html_e('Example advertiser', 'million-dollar-script'); ?></strong>
                <p><?php esc_html_e('Approved placement image and public advertiser copy appear here with a sponsored destination and a link back to the grid.', 'million-dollar-script'); ?></p>
                <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Visit advertiser', 'million-dollar-script'); ?></span>
            </div>
            <?php if ($legacy_template) : ?>
                <p><strong><?php esc_html_e('Legacy theme template detected:', 'million-dollar-script'); ?></strong> <code>mds-pixel/single-mds-pixel.php</code>. <?php esc_html_e('It is not executed by MDS 3.0. Copy its presentation into million-dollar-script/single-advertiser.php if you still need that customization.', 'million-dollar-script'); ?></p>
            <?php endif; ?>
            <div class="mds3-inline-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds3_sync_advertiser_pages'); ?>
                    <input type="hidden" name="action" value="mds3_sync_advertiser_pages" />
                    <button type="submit" class="button"><?php esc_html_e('Synchronize advertiser pages', 'million-dollar-script'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds3_preview_advertiser_slugs'); ?>
                    <input type="hidden" name="action" value="mds3_preview_advertiser_slugs" />
                    <button type="submit" class="button"><?php esc_html_e('Preview slug migration', 'million-dollar-script'); ?></button>
                </form>
            </div>
            <?php if (is_array($preview)) : ?>
                <p><?php echo esc_html(sprintf(__('Preview checked %1$d pages; %2$d URLs would change.%3$s', 'million-dollar-script'), absint($preview['checked'] ?? 0), absint($preview['changed'] ?? 0), !empty($preview['truncated']) ? ' ' . __('The preview was capped at 5,000 pages.', 'million-dollar-script') : '')); ?></p>
                <?php if (!empty($preview['changed']) || $continue_after) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds3_apply_advertiser_slugs'); ?>
                        <input type="hidden" name="action" value="mds3_apply_advertiser_slugs" />
                        <input type="hidden" name="after_id" value="<?php echo esc_attr((string) $continue_after); ?>" />
                        <label><input type="checkbox" name="confirm_slug_migration" value="yes" required /> <?php esc_html_e('I understand these public URLs will change and exact previous slugs will redirect permanently.', 'million-dollar-script'); ?></label>
                        <p><button type="submit" class="button button-secondary"><?php echo esc_html($continue_after ? __('Apply next migration batch', 'million-dollar-script') : __('Apply first migration batch', 'million-dollar-script')); ?></button></p>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function after_settings_change(array $saved, array $current) {
        $old_base = AdvertiserPageUrls::base($current);
        $new_base = AdvertiserPageUrls::base($saved);
        if ($old_base !== $new_base) {
            AdvertiserPageUrls::remember_base($old_base);
        }
        if ($old_base !== $new_base || AdvertiserPageUrls::enabled($saved) !== AdvertiserPageUrls::enabled($current)) {
            update_option('mds3_flush_rewrite_rules', 'yes', false);
            delete_option(self::SYNC_CURSOR_OPTION);
            if (!wp_next_scheduled(self::SYNC_HOOK)) {
                wp_schedule_single_event(time() + 5, self::SYNC_HOOK);
            }
        }
    }

    private function post_is_public($post_id) {
        if (!AdvertiserPageUrls::enabled() || 'publish' !== get_post_status(absint($post_id))) {
            return false;
        }
        $placement_id = absint(get_post_meta(absint($post_id), '_million_dollar_script_placement_id', true));
        $placement = (new AdvertiserPageManager())->public_source($placement_id);

        return is_array($placement) && (new AdvertiserPageManager())->is_public_source($placement);
    }

    private function admin_guard($nonce_action) {
        check_admin_referer($nonce_action);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }
    }

    private function base_conflicts($base) {
        $reserved = ['wp-admin', 'wp-json', 'feed', 'author', 'category', 'tag', 'search', 'sitemap', 'sitemap-xml', 'robots-txt'];
        if (in_array($base, $reserved, true) || get_page_by_path($base, OBJECT, ['page', 'post'])) {
            return true;
        }
        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if (AdvertiserPageManager::POST_TYPE === $post_type->name || empty($post_type->rewrite) || !is_array($post_type->rewrite)) {
                continue;
            }
            if ($base === sanitize_title((string) ($post_type->rewrite['slug'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function settings_redirect(array $args = []) {
        wp_safe_redirect(add_query_arg(array_merge(['page' => 'mds3-settings', 'tab' => 'settings-urls-redirects'], $args), admin_url('admin.php')));
        exit;
    }
}
