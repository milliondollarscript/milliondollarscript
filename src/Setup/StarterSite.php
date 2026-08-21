<?php
/**
 * Optional starter pages and navigation for new Million Dollar Script sites.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class StarterSite implements Component {

    public const OPTION = 'mds3_starter_site';

    public const CONTACT_SHORTCODE = 'million_dollar_script_contact';

    /**
     * Register the stable contact-page wrapper used by starter content.
     *
     * @return void
     */
    public function register() {
        add_shortcode(self::CONTACT_SHORTCODE, [$this, 'render_contact']);
    }

    /**
     * Create or repair the opt-in starter site without replacing user content.
     *
     * @return array<string,mixed>
     */
    public function create() {
        $result = [
            'created' => [],
            'errors' => [],
            'reused' => [],
        ];

        $required = [
            'grid' => absint(get_option('mds3_page_grid_id', 0)),
            'order' => absint(get_option('mds3_page_order_id', 0)),
            'manage' => absint(get_option('mds3_page_manage_id', 0)),
        ];
        foreach ($required as $key => $post_id) {
            if (!$this->valid_page($post_id)) {
                $result['errors'][] = sprintf(
                    /* translators: %s: missing starter page role */
                    __('Create the standard %s page before building the starter site.', 'million-dollar-script'),
                    $key
                );
            }
        }
        if ($result['errors']) {
            return $result;
        }

        $state = $this->state();
        $pages = is_array($state['pages'] ?? null) ? $state['pages'] : [];

        $front_page_id = absint(get_option('page_on_front', 0));
        if ('page' !== get_option('show_on_front') || !$this->valid_page($front_page_id)) {
            $front_page_id = $required['grid'];
            update_option('show_on_front', 'page');
            update_option('page_on_front', $front_page_id);
        }
        $pages['home'] = $front_page_id;

        $pages['blog'] = $this->ensure_page(
            'blog',
            __('Blog', 'million-dollar-script'),
            'blog',
            '',
            $pages,
            $result
        );
        if ($pages['blog'] && !$this->valid_page(absint(get_option('page_for_posts', 0)))) {
            update_option('page_for_posts', $pages['blog']);
        }

        $pages['order'] = $required['order'];
        $pages['manage'] = $required['manage'];
        $pages['contact'] = $this->ensure_page(
            'contact',
            __('Contact', 'million-dollar-script'),
            'contact',
            '[' . self::CONTACT_SHORTCODE . ']',
            $pages,
            $result,
            '[' . self::CONTACT_SHORTCODE
        );
        $pages['about'] = $this->ensure_page(
            'about',
            __('About', 'million-dollar-script'),
            'about',
            $this->about_content(),
            $pages,
            $result
        );

        $navigation = (new StarterNavigation())->ensure($pages, $state, $result);
        $state = [
            'pages' => array_map('absint', array_filter($pages)),
            'navigation' => $navigation,
            'updated_at' => current_time('mysql', true),
            'version' => 1,
        ];
        update_option(self::OPTION, $state, false);

        $result['status'] = $this->status();
        return $result;
    }

    /**
     * Describe whether the starter resources created by this workflow remain.
     *
     * @return array<string,mixed>
     */
    public function status() {
        $state = $this->state();
        $pages = is_array($state['pages'] ?? null) ? $state['pages'] : [];
        $required_pages = ['home', 'blog', 'order', 'manage', 'contact', 'about'];
        $pages_ready = true;
        foreach ($required_pages as $key) {
            if (!$this->valid_page(absint($pages[$key] ?? 0))) {
                $pages_ready = false;
                break;
            }
        }

        $navigation = is_array($state['navigation'] ?? null) ? $state['navigation'] : [];
        $navigation_ready = (new StarterNavigation())->exists($navigation);

        return [
            'configured' => $pages_ready && $navigation_ready,
            'navigation' => $navigation,
            'navigation_needs_review' => $navigation_ready && (
                !empty($navigation['preserved'])
                || ('classic' === ($navigation['type'] ?? '') && empty($navigation['location']))
            ),
            'pages' => $pages,
        ];
    }

    /**
     * Render the free Contact Form extension when present, otherwise a useful
     * public fallback that never exposes an inactive shortcode.
     *
     * @return string
     */
    public function render_contact() {
        if (shortcode_exists('mds_contact_form')) {
            return do_shortcode('[mds_contact_form]');
        }

        $message = '<div class="mds-contact-page-fallback">';
        $message .= '<p>' . esc_html__('This site has not enabled its contact form yet. Please use another contact method published on this website.', 'million-dollar-script') . '</p>';
        if (current_user_can('manage_options')) {
            $message .= '<p><a href="' . esc_url(admin_url('admin.php?page=mds3-extensions')) . '">' . esc_html__('Activate the free Contact Form extension', 'million-dollar-script') . '</a></p>';
        }
        $message .= '</div>';

        return $message;
    }

    /**
     * @return array<string,mixed>
     */
    private function state() {
        $state = get_option(self::OPTION, []);
        return is_array($state) ? $state : [];
    }

    private function valid_page($post_id) {
        return $post_id > 0 && 'page' === get_post_type($post_id) && false !== get_post_status($post_id) && 'trash' !== get_post_status($post_id);
    }

    private function ensure_page($key, $title, $slug, $content, array $pages, array &$result, $required_content = '') {
        $saved_id = absint($pages[$key] ?? 0);
        if ($this->valid_page($saved_id)) {
            $result['reused'][$key] = $saved_id;
            return $saved_id;
        }

        $existing = get_page_by_path($slug, OBJECT, 'page');
        $existing_matches = $existing instanceof \WP_Post
            && 'publish' === $existing->post_status
            && ('' === $required_content || false !== strpos((string) $existing->post_content, $required_content));
        if ($existing_matches) {
            $result['reused'][$key] = absint($existing->ID);
            return absint($existing->ID);
        }

        $post_id = wp_insert_post([
            'post_content' => $content,
            'post_name' => sanitize_title($slug),
            'post_status' => 'publish',
            'post_title' => $title,
            'post_type' => 'page',
        ], true);
        if (is_wp_error($post_id)) {
            $result['errors'][] = $post_id->get_error_message();
            return 0;
        }

        $post_id = absint($post_id);
        update_post_meta($post_id, '_million_dollar_script_starter_page', $key);
        $result['created'][$key] = $post_id;
        return $post_id;
    }

    private function about_content() {
        return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__('A visual campaign powered by Million Dollar Script', 'million-dollar-script') . '</h2><!-- /wp:heading -->'
            . "\n\n<!-- wp:paragraph --><p>" . esc_html__('This website uses Million Dollar Script to publish an interactive sponsor, advertising, or fundraising grid.', 'million-dollar-script') . '</p><!-- /wp:paragraph -->'
            . "\n\n<!-- wp:paragraph --><p>" . esc_html__('Use the Order Pixels page to choose an available placement. Existing customers can use Manage Pixels to review their order and update eligible placement details.', 'million-dollar-script') . '</p><!-- /wp:paragraph -->';
    }

}
