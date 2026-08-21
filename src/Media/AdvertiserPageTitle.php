<?php
/**
 * Deterministic advertiser-page title selection and legacy normalization.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class AdvertiserPageTitle {

    private const MAX_CONCISE_CHARACTERS = 100;
    private const MAX_CONCISE_WORDS = 16;
    private const FALLBACK_WORDS = 12;

    /**
     * @return array{title:string,normalized:bool,source:string}
     */
    public static function resolve(array $placement, $legacy_post = null): array {
        $legacy_raw = is_object($legacy_post) && 'mds-pixel' === (string) ($legacy_post->post_type ?? '')
            ? (string) ($legacy_post->post_title ?? '')
            : '';
        $legacy_title = self::text($legacy_raw);
        if ('' !== $legacy_title && self::is_concise($legacy_raw, $legacy_title)) {
            return ['title' => $legacy_title, 'normalized' => false, 'source' => 'legacy_title'];
        }

        $alt_text = self::text((string) ($placement['alt_text'] ?? ''));
        if ('' !== $legacy_title) {
            if ('' !== $alt_text && self::is_concise($alt_text, $alt_text)) {
                return ['title' => $alt_text, 'normalized' => true, 'source' => 'alt_text'];
            }

            $popup_text = self::text((string) ($placement['popup_text'] ?? ''));
            $fallback = self::bounded($popup_text ?: $legacy_title);

            return [
                'title' => '' !== $fallback ? $fallback : self::default_title($placement),
                'normalized' => true,
                'source' => '' !== $popup_text ? 'popup_text' : 'legacy_title_excerpt',
            ];
        }

        if ('' !== $alt_text) {
            return ['title' => $alt_text, 'normalized' => false, 'source' => 'alt_text'];
        }

        $popup_title = self::bounded(self::text((string) ($placement['popup_text'] ?? '')));

        return [
            'title' => '' !== $popup_title ? $popup_title : self::default_title($placement),
            'normalized' => false,
            'source' => '' !== $popup_title ? 'popup_text' : 'default',
        ];
    }

    private static function is_concise(string $raw, string $clean): bool {
        if (preg_match('/[\r\n]/', $raw)) {
            return false;
        }

        return self::length($clean) <= self::MAX_CONCISE_CHARACTERS
            && self::word_count($clean) <= self::MAX_CONCISE_WORDS;
    }

    private static function bounded(string $text): string {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = is_array($words) ? array_slice($words, 0, self::FALLBACK_WORDS) : [];
        $title = implode(' ', $words);

        return self::length($title) > self::MAX_CONCISE_CHARACTERS
            ? rtrim(self::substring($title, 0, self::MAX_CONCISE_CHARACTERS))
            : $title;
    }

    private static function text(string $value): string {
        $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return sanitize_text_field(trim($value));
    }

    private static function word_count(string $value): int {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’.-][\p{L}\p{N}]+)*/u', $value, $matches);

        return count($matches[0] ?? []);
    }

    private static function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function substring(string $value, int $start, int $length): string {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
    }

    private static function default_title(array $placement): string {
        return sprintf(__('Advertiser placement %d', 'million-dollar-script'), absint($placement['id'] ?? 0));
    }
}
