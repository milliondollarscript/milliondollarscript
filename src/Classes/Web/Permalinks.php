<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

namespace MillionDollarScript\Classes\Web;

use MillionDollarScript\Classes\Data\Options;
use WP_Post;

defined('ABSPATH') or exit;

class Permalinks {
	const DEFAULT_BASE = 'mds-pixel';
	const DEFAULT_PATTERN = '%username%-%order_id%';
	const DEFAULT_MAX_URL_LENGTH = 2048;

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

		[$max_url_length, $max_slug_length] = self::calculate_slug_limits($post_id, $post);

		// Built-in token values (raw).
		$author = get_userdata((int) $post->post_author);
		$username = $author ? $author->user_login : '';
		$display_name = $author ? $author->display_name : '';

		$order_id = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'order');
		$grid_id  = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'grid');
		$text     = (string) carbon_get_post_meta($post_id, MDS_PREFIX . 'text');

		$builtins_raw = [
			'%username%'     => (string) $username,
			'%display_name%' => (string) $display_name,
			'%order_id%'     => (string) $order_id,
			'%grid%'         => (string) $grid_id,
			'%pixel_id%'     => (string) $post_id,
			'%text%'         => (string) $text,
		];

		// Allow developers to add/modify token values.
		$builtins_filtered = apply_filters('mds_permalink_tokens', $builtins_raw, $post_id, $post);
		if (!is_array($builtins_filtered)) {
			$builtins_filtered = (array) $builtins_filtered;
		}

		$builtins = [];
		foreach ($builtins_filtered as $token => $value) {
			if (!is_string($token)) {
				continue;
			}
			$builtins[$token] = self::prepare_token_value($token, (string) $value, $max_slug_length, $post_id, $post);
		}

		$slug_raw = strtr($pattern, $builtins);

		// Resolve generic %meta:key% tokens (supports Carbon Fields and regular post meta)
		if (preg_match_all('/%meta:([a-zA-Z0-9_\-]+)%/', $slug_raw, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$key = $match[1];
				$value = self::prepare_token_value($match[0], (string) self::get_meta_value_flexible($post_id, $key), $max_slug_length, $post_id, $post);
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
		$slug = self::truncate_slug($slug, $max_slug_length);

		$slug = apply_filters('mds_permalink_final_slug', $slug, $post_id, $post, $max_slug_length, $max_url_length);
		$slug = self::truncate_slug((string) $slug, $max_slug_length);

		if ($slug === '') {
			$fallback = sanitize_title($post->post_title);
			return self::truncate_slug($fallback, $max_slug_length);
		}

		return $slug;
	}

	/**
	 * Ensure the stored post slug matches the configured pattern.
	 */
	public static function sync_post_slug(int $post_id): void {
		$post = get_post($post_id);
		if (!$post) {
			return;
		}

		$slug = self::build_slug_for_post($post_id);
		if ($slug === '') {
			return;
		}

		$unique_slug = wp_unique_post_slug($slug, $post_id, $post->post_status, $post->post_type, (int) $post->post_parent);
		if ($unique_slug === $post->post_name) {
			return;
		}

		wp_update_post([
			'ID'        => $post_id,
			'post_name' => $unique_slug,
		]);
	}

	private static function calculate_slug_limits(int $post_id, WP_Post $post): array {
		$max_url_length = (int) apply_filters('mds_pixels_max_url_length', self::DEFAULT_MAX_URL_LENGTH, $post_id, $post);
		if ($max_url_length < 1) {
			$max_url_length = self::DEFAULT_MAX_URL_LENGTH;
		}

		$prefix_length = self::get_permalink_prefix_length();
		$max_slug_length = (int) ($max_url_length - $prefix_length);
		if ($max_slug_length < 1) {
			$max_slug_length = 1;
		}

		$max_slug_length = (int) apply_filters('mds_pixels_max_slug_length', $max_slug_length, $post_id, $post, $max_url_length, $prefix_length);
		if ($max_slug_length < 1) {
			$max_slug_length = 1;
		}

		return [ $max_url_length, $max_slug_length ];
	}

	private static function get_permalink_prefix_length(): int {
		$placeholder = '__mds_slug__';

		if ('' === get_option('permalink_structure')) {
			$query_var = 'mds-pixel';
			$post_type = get_post_type_object('mds-pixel');
			if ($post_type && !empty($post_type->query_var)) {
				$query_var = $post_type->query_var;
			}
			$url = add_query_arg($query_var, $placeholder, home_url('/'));
			return max(0, (int) strlen($url) - (int) strlen($placeholder));
		}

		$path = '/' . self::get_base() . '/' . $placeholder;
		$url = user_trailingslashit(home_url($path));
		return max(0, (int) strlen($url) - (int) strlen($placeholder));
	}

	private static function prepare_token_value(string $token, string $value, int $max_slug_length, int $post_id, WP_Post $post): string {
		$value = apply_filters('mds_permalink_token_value', $value, $token, $post_id, $post, $max_slug_length);
		$token_key = self::sanitize_token_filter_key($token);
		$value = apply_filters('mds_permalink_token_value_' . $token_key, $value, $token, $post_id, $post, $max_slug_length);

		if ($max_slug_length > 0 && self::mb_strlen($value) > $max_slug_length) {
			$value = self::mb_substr($value, $max_slug_length);
		}

		return $value;
	}

	private static function sanitize_token_filter_key(string $token): string {
		$trimmed = trim($token, '%');
		if ($trimmed === '') {
			return 'token';
		}
		return preg_replace('/[^a-z0-9_]+/i', '_', $trimmed) ?: 'token';
	}

	private static function truncate_slug(string $slug, int $max_length): string {
		if ($max_length <= 0) {
			return $slug;
		}
		if ((int) strlen($slug) <= $max_length) {
			return $slug;
		}

		$truncated = substr($slug, 0, $max_length);
		$trimmed = rtrim($truncated, '-');
		if ($trimmed === '') {
			return $truncated;
		}

		return $trimmed;
	}

	private static function mb_strlen(string $value): int {
		if (function_exists('mb_strlen')) {
			return mb_strlen($value, 'UTF-8');
		}
		return strlen($value);
	}

	private static function mb_substr(string $value, int $length): string {
		if ($length <= 0) {
			return '';
		}
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, $length, 'UTF-8');
		}
		return substr($value, 0, $length);
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
