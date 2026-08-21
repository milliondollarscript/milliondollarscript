<?php
/**
 * Provider-neutral commerce source registry.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce;

if (!defined('ABSPATH')) {
    exit;
}

final class Sources {

    private const OPERATIONS = ['quote', 'reserve', 'activate', 'renew', 'suspend', 'end', 'release'];

    public static function all() {
        $sources = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/commerce/sources', []);
        if (!is_array($sources)) {
            return [];
        }

        $normalized = [];
        foreach ($sources as $id => $source) {
            if (!is_array($source)) {
                continue;
            }

            $source_id = sanitize_key((string) ($source['id'] ?? $id));
            if (!$source_id) {
                continue;
            }

            $modes = is_array($source['modes'] ?? null)
                ? array_values(array_unique(array_filter(array_map('sanitize_key', $source['modes']))))
                : [];
            $source['id'] = $source_id;
            $source['label'] = sanitize_text_field((string) ($source['label'] ?? $source_id));
            $source['modes'] = array_values(array_intersect(
                $modes,
                ['resource_term', 'allowance', 'feature_access', 'recurring_contribution']
            ));
            $normalized[$source_id] = $source;
        }

        return $normalized;
    }

    public static function get($source_id) {
        $source_id = sanitize_key((string) $source_id);

        return self::all()[$source_id] ?? null;
    }

    public static function supports($source_id, $mode) {
        $source = self::get($source_id);
        $mode = sanitize_key((string) $mode);

        return is_array($source) && in_array($mode, (array) ($source['modes'] ?? []), true);
    }

    public static function invoke($source_id, $operation, $resource_id, array $context = []) {
        $source = self::get($source_id);
        $operation = sanitize_key((string) $operation);
        if (!$source || !in_array($operation, self::OPERATIONS, true)) {
            return new \WP_Error(
                'mds_commerce_source_operation_unsupported',
                __('This commerce source does not support the requested operation.', 'million-dollar-script'),
                ['status' => 409]
            );
        }

        $callback = $source[$operation] ?? null;
        if (!is_callable($callback)) {
            return new \WP_Error(
                'mds_commerce_source_operation_unsupported',
                __('This commerce source does not support the requested operation.', 'million-dollar-script'),
                ['status' => 409]
            );
        }

        $result = call_user_func($callback, $resource_id, $context, $source);
        if (!is_wp_error($result)) {
            \MillionDollarScript\Core\Hooks::do(
                'million-dollar-script/commerce/source/' . $operation,
                sanitize_key((string) $source_id),
                $resource_id,
                $context,
                $result
            );
        }

        return $result;
    }
}
