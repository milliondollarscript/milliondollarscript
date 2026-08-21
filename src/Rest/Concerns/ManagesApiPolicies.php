<?php
/**
 * REST API policy persistence and security-level helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait ManagesApiPolicies {

    public function effective_manifest($include_extensions = true) {
        $policies = $this->stored_policies();
        $effective = [];

        foreach ($this->endpoint_manifest($include_extensions) as $endpoint) {
            $id = sanitize_key((string) ($endpoint['id'] ?? ''));
            if (!$id) {
                continue;
            }

            $minimum = $this->security_level((string) ($endpoint['minimum_security_level'] ?? 'api_key_write'));
            $override = $this->security_level((string) ($policies[$id] ?? $minimum));
            $endpoint['minimum_security_level'] = $minimum;
            $endpoint['security_level'] = $this->max_level($minimum, $override);
            $endpoint['id'] = $id;
            $effective[] = $endpoint;
        }

        return $effective;
    }

    public function save_policies(array $raw_policies) {
        $manifest = [];
        foreach ($this->endpoint_manifest() as $endpoint) {
            $id = sanitize_key((string) ($endpoint['id'] ?? ''));
            if ($id) {
                $manifest[$id] = $this->security_level((string) ($endpoint['minimum_security_level'] ?? 'api_key_write'));
            }
        }

        $next = [];
        foreach ($raw_policies as $id => $level) {
            $id = sanitize_key((string) $id);
            if (!isset($manifest[$id])) {
                continue;
            }

            $next[$id] = $this->max_level($manifest[$id], $this->security_level((string) $level));
        }

        update_option(self::POLICIES_OPTION, $next, false);
    }

    public function security_levels() {
        return array_keys(self::LEVELS);
    }

    private function stored_policies() {
        $policies = get_option(self::POLICIES_OPTION, []);

        return is_array($policies) ? $policies : [];
    }

    private function security_level($level) {
        $level = sanitize_key((string) $level);

        return isset(self::LEVELS[$level]) ? $level : 'api_key_write';
    }

    private function max_level($minimum, $override) {
        $minimum = $this->security_level($minimum);
        $override = $this->security_level($override);

        return self::LEVELS[$override] >= self::LEVELS[$minimum] ? $override : $minimum;
    }
}
