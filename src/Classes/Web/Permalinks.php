<?php

/*
 * Million Dollar Script Two
 * Permalinks helper for MDS Pixels
 */

namespace MillionDollarScript\Classes\Web;

use MillionDollarScript\Classes\Data\Options;

defined('ABSPATH') or exit;

class Permalinks {
	const DEFAULT_BASE = 'mds-pixel';
	const DEFAULT_PATTERN = '%username%-%order_id%';

	/**
	 * Initialize rewrite helpers for old base redirects.
	 */
	public static function init(): void {
		add_filter('query_vars', [__CLASS__, 'query_vars']);
		add_action('init', [__CLASS__, 'add_old_base_rules']);
		add_action('template_redirect', [__CLASS__, 'maybe_redirect_old_base']);
	}

	public static function get_base(): string {
		$base = Options::get_option('mds-pixel-base', self::DEFAULT_BASE);
		$base = sanitize_title($base);
		return $base ?: self::DEFAULT_BASE;
	}

	public static function get_pattern(): string {
		$pattern = Options::get_option('mds-pixel-slug-structure', self::DEFAULT_PATTERN);
		return is_string($pattern) && $pattern !== '' ? $pattern : self::DEFAULT_PATTERN;
	}

	/**
	 * Build a slug for a given mds-pixel post using the configured pattern.
	 */
	public static function build_slug_for_post(int $post_id): string {
		$post = get_post($post_id);
		if (!$post) {
			return '';
		}

		$pattern = self::get_pattern();

		// Built-in token values
		$author = get_userdata((int) $post->post_author);
		$username = $author ? $author->user_login : '';
		$display_name = $author ? $author->display_name : '';

		$order_id = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'order');
		$grid_id  = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'grid');
		$text     = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'text');

		$builtins = [
			'%username%'     => (string) $username,
			'%display_name%' => (string) $display_name,
			'%order_id%'     => (string) $order_id,
			'%grid%'         => (string) $grid_id,
			'%pixel_id%'     => (string) $post_id,
			'%text%'         => (string) $text,
		];

		// Allow developers to add/modify token values.
		$builtins = apply_filters('mds_permalink_tokens', $builtins, $post_id, $post);

		$slug_raw = strtr($pattern, $builtins);

		// Resolve generic %meta:key% tokens (supports Carbon Fields and regular post meta)
		if (preg_match_all('/%meta:([a-zA-Z0-9_\-]+)%/', $slug_raw, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$key = $match[1];
				$value = self::get_meta_value_flexible($post_id, $key);
				$slug_raw = str_replace($match[0], (string) $value, $slug_raw);
			}
		}

		// Transliterate if enabled in Options
		$transliterate = Options::get_option('transliterate-slugs');
		if ($transliterate === 'on' && method_exists('MillionDollarScript\\Classes\\System\\Functions', 'transliterate_cyrillic_to_latin')) {
			$slug_raw = \MillionDollarScript\Classes\System\Functions::transliterate_cyrillic_to_latin($slug_raw);
		}

		$slug = sanitize_title($slug_raw);
		$slug = preg_replace('/-+/', '-', $slug); // collapse multiple dashes
		$slug = trim((string) $slug, '-');

		return $slug ?: sanitize_title($post->post_title);
	}

	private static function get_meta_value_flexible(int $post_id, string $key): string {
		// Try Carbon Fields with and without plugin prefix
		$value = '';
		if (function_exists('carbon_get_post_meta')) {
			$value = (string) carbon_get_post_meta($post_id, MDS_PREFIX . $key);
			if ($value === '' || $value === null) {
				$value = (string) carbon_get_post_meta($post_id, $key);
			}
		}
		if ($value !== '' && $value !== null) {
			return (string) $value;
		}
		// Try raw post meta with various key forms (Carbon stores with leading underscore)
		$candidates = [ '_' . MDS_PREFIX . $key, '_' . $key, MDS_PREFIX . $key, $key ];
		foreach ($candidates as $meta_key) {
			$mv = get_post_meta($post_id, $meta_key, true);
			if (!empty($mv)) {
				return (string) $mv;
			}
		}
		return '';
	}

	public static function query_vars(array $vars): array {
		$vars[] = 'mds_old_pixel_slug';
		return $vars;
	}

	public static function add_old_base_rules(): void {
		$history = (array) get_option('_' . MDS_PREFIX . 'mds-pixel-base-history', []);
		$current = self::get_base();
		$history = array_unique(array_filter($history));
		foreach ($history as $old_base) {
			if ($old_base === $current) { continue; }
			add_rewrite_rule('^' . $old_base . '/([^/]+)/?$', 'index.php?mds_old_pixel_slug=$matches[1]', 'top');
		}
	}

	public static function maybe_redirect_old_base(): void {
		$slug = get_query_var('mds_old_pixel_slug');
		if (empty($slug)) {
			return;
		}
		// Try to find by current slug
		$post = get_page_by_path($slug, OBJECT, 'mds-pixel');
		if (!$post) {
			// Fallback: find by old slug meta
			$q = new \WP_Query([
				'post_type' => 'mds-pixel',
				'post_status' => 'any',
				'posts_per_page' => 1,
				'meta_query' => [[
					'key' => '_wp_old_slug',
					'value' => $slug,
					'compare' => '='
				]]
			]);
			if ($q->have_posts()) {
				$post = $q->posts[0];
			}
			wp_reset_postdata();
		}
		if ($post) {
			$dest = get_permalink($post);
			if ($dest && !self::is_current_request($dest)) {
				header('X-Redirect-By: MDS Pixels');
				wp_redirect($dest, 301);
				exit;
			}
		}
	}

	private static function is_current_request(string $url): bool {
		$requested = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		return rtrim($requested, '/') === rtrim($url, '/');
	}
}
