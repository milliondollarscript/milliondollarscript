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

namespace MillionDollarScript\Classes\Extension;

use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Data\DatabaseStatic;

defined( 'ABSPATH' ) or exit;

class MDS_License_Manager {

    /**
     * The table name for the extension licenses.
     *
     * @var string
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mds_extension_licenses';
    }

    /**
     * Check if the licenses table exists.
     */
    private function table_exists(): bool {
        return DatabaseStatic::table_exists( $this->table_name );
    }

    /**
     * Check if a specific column exists on the licenses table.
     */
    private function has_column( string $column ): bool {
        global $wpdb;
        $sql = $wpdb->prepare( "SHOW COLUMNS FROM {$this->table_name} LIKE %s", $column );
        $result = $wpdb->get_var( $sql );
        return ! empty( $result );
    }

    /**
     * Add a new license.
     *
     * @param string $extension_slug
     * @param string $license_key
     * @return int|false
     */
    public function add_license( string $extension_slug, string $license_key ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $name_col = $this->has_column( 'plugin_name' ) ? 'plugin_name' : ( $this->has_column( 'extension_slug' ) ? 'extension_slug' : null );
        if ( $name_col === null ) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                $name_col     => $extension_slug,
                'license_key' => $license_key,
                'status'      => 'inactive',
            ],
            [ '%s', '%s', '%s' ]
        );

        if ( ! $result ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get a license by extension slug.
     *
     * @param string $extension_slug
     * @return object|null
     */
    public function get_license( string $extension_slug ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return null;
        }

        if ( $this->has_column( 'plugin_name' ) ) {
            $sql = "SELECT * FROM {$this->table_name} WHERE plugin_name = %s LIMIT 1";
            return $wpdb->get_row( $wpdb->prepare( $sql, $extension_slug ) );
        }

        if ( $this->has_column( 'extension_slug' ) ) {
            $sql = "SELECT * FROM {$this->table_name} WHERE extension_slug = %s LIMIT 1";
            return $wpdb->get_row( $wpdb->prepare( $sql, $extension_slug ) );
        }

        return null;
    }

    /**
     * Update a license.
     *
     * @param int    $id
     * @param array $data
     * @return bool
     */
    public function update_license( int $id, array $data ): bool {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $allowed = [
            'plugin_name', 'extension_slug', 'license_key', 'status', 'seats', 'email',
            'external_id', 'customer_id', 'order_id', 'expires_at', 'metadata',
            'created_at', 'updated_at',
        ];
        $filtered = array_intersect_key( $data, array_flip( $allowed ) );
        if ( empty( $filtered ) ) {
            return false;
        }

        return (bool) $wpdb->update( $this->table_name, $filtered, [ 'id' => $id ] );
    }

    /**
     * Delete a license.
     *
     * @param int $id
     * @return bool
     */
    public function delete_license( int $id ): bool {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        return (bool) $wpdb->delete( $this->table_name, [ 'id' => $id ] );
    }

    /**
     * Check if an extension is licensed.
     *
     * @param string $extension_slug
     * @return bool
     */
    public function is_extension_licensed( string $extension_slug, bool $force_refresh = false ): bool {
        if ( ! $this->table_exists() ) {
            return false;
        }

        $license = $this->get_license( $extension_slug );

        if ( ! $license ) {
            return false;
        }

        $status = isset($license->status) ? strtolower((string) $license->status) : '';
        $transient_key = 'mds_license_check_' . $extension_slug;
        $cached = get_transient($transient_key);

        if (!$force_refresh) {
            if ($status === 'active' && $cached === 'valid') {
                return true;
            }

            if ($cached === 'invalid') {
                return false;
            }
        }

        $plaintext = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact( (string) $license->license_key );
        if ($plaintext === '') {
            $plaintext = (string) $license->license_key;
        }

        if ($plaintext === '') {
            $this->update_license( (int) $license->id, [ 'status' => 'inactive' ] );
            set_transient( $transient_key, 'invalid', HOUR_IN_SECONDS );
            return false;
        }

        $validation = API::validate_license( $plaintext, $extension_slug );

        if ( is_array( $validation ) && ! empty( $validation['success'] ) && ! empty( $validation['valid'] ) ) {
            set_transient( $transient_key, 'valid', DAY_IN_SECONDS );

            $update_data = [];

            $existing_metadata = self::decode_metadata_value( $license->metadata ?? null );

            if ( ! empty( $validation['license'] ) && is_array( $validation['license'] ) ) {
                $prepared = self::prepare_payload_metadata( $validation['license'], $existing_metadata );

                if ( ! empty( $prepared['metadata'] ) ) {
                    $encoded = wp_json_encode( $prepared['metadata'] );
                    if ( is_string( $encoded ) ) {
                        $update_data['metadata'] = $encoded;
                    }
                }

                if ( ! empty( $prepared['expires_at'] ) ) {
                    $update_data['expires_at'] = $prepared['expires_at'];
                }
            }

            if ( $status !== 'active' ) {
                $update_data['status'] = 'active';
            }

            if ( ! empty( $update_data ) ) {
                $this->update_license( (int) $license->id, $update_data );
            }

            return true;
        }

        $this->update_license( (int) $license->id, [ 'status' => 'inactive' ] );
        set_transient( $transient_key, 'invalid', HOUR_IN_SECONDS );

        return false;
    }

    /**
     * Get all licenses stored locally.
     * @return array<int, object> List of license rows with dynamic columns
     */
    public function get_all_licenses(): array {
        global $wpdb;
        if ( ! $this->table_exists() ) {
            return [];
        }
        $rows = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY id DESC" );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Decode a stored metadata value into an array.
     */
	public static function decode_metadata_value( $metadata ): array {
		if ( $metadata === null || $metadata === '' ) {
			return [];
		}

		if ( is_string( $metadata ) ) {
			$unserialized = Utility::safe_maybe_unserialize( $metadata );
			if ( $unserialized !== $metadata ) {
				return self::decode_metadata_value( $unserialized );
			}

			$decoded = json_decode( $metadata, true );
			return is_array( $decoded ) ? $decoded : [];
		}

        if ( $metadata instanceof \stdClass ) {
            return json_decode( wp_json_encode( $metadata ), true ) ?: [];
        }

        if ( is_array( $metadata ) ) {
            return $metadata;
        }

		return [];
	}


    /**
     * Merge metadata and date fields from a remote license payload.
     */
    public static function prepare_payload_metadata( array $payload, array $existing_metadata = [] ): array {
        $metadata = $existing_metadata;

        $renews_at = self::extract_datetime_from_payload(
            $payload,
            [
                ['renews_at'],
                ['renewsAt'],
                ['renewal_at'],
                ['renewalAt'],
                ['billing_period_end'],
                ['billingPeriodEnd'],
                ['next_billing_at'],
                ['nextBillingAt'],
                ['next_payment_attempt'],
                ['nextPaymentAttempt'],
                ['subscription', 'current_period_end'],
                ['subscription', 'currentPeriodEnd'],
                ['subscription', 'billing_period_end'],
                ['subscription', 'billingPeriodEnd'],
                ['subscription', 'next_billing_at'],
                ['subscription', 'nextBillingAt'],
                ['stripe_subscription', 'current_period_end'],
                ['stripeSubscription', 'currentPeriodEnd'],
                ['stripe', 'subscription', 'current_period_end'],
                ['stripe', 'subscription', 'currentPeriodEnd'],
                ['stripe', 'subscription', 'billing_period_end'],
                ['stripe', 'upcoming_invoice', 'period_end'],
            ],
            ['renew', 'billingperiod', 'nextbilling', 'nextpayment', 'periodend']
        );

        if ( $renews_at !== null ) {
            $metadata['renews_at'] = $renews_at;
        }

        $expires_at = self::extract_datetime_from_payload(
            $payload,
            [
                ['expires_at'],
                ['expiresAt'],
                ['expiration'],
                ['expirationDate'],
                ['subscription', 'expires_at'],
                ['subscription', 'expiresAt'],
                ['subscription', 'current_period_end'],
                ['subscription', 'currentPeriodEnd'],
                ['stripe_subscription', 'cancel_at'],
                ['stripeSubscription', 'cancelAt'],
                ['stripe', 'subscription', 'cancel_at'],
                ['stripe', 'subscription', 'cancelAt'],
            ],
            ['expire', 'expiration', 'periodend', 'cancelat']
        );

        if ( $expires_at !== null ) {
            $metadata['expires_at'] = $expires_at;
        }

        $plan = self::find_scalar_value_by_keys( $payload, ['plan', 'planKey', 'plan_key', 'planSlug'] );
        if ( $plan !== null ) {
            $metadata['plan'] = $plan;
        }

        $price_plan = self::find_scalar_value_by_keys( $payload, ['price_plan', 'pricePlan', 'planPrice', 'plan_price'] );
        if ( $price_plan !== null ) {
            $metadata['price_plan'] = $price_plan;
        }

        $recurring = self::find_scalar_value_by_keys( $payload, ['recurring', 'interval', 'billing_interval', 'billingInterval'] );
        if ( $recurring !== null ) {
            $metadata['recurring'] = $recurring;
        }

        $billing_period = self::find_scalar_value_by_keys( $payload, ['billing_period', 'billingPeriod'] );
        if ( $billing_period !== null ) {
            $metadata['billing_period'] = $billing_period;
        }

        $subscription_id = self::find_scalar_value_by_keys( $payload, ['subscription_id', 'subscriptionId'] );
        if ( $subscription_id !== null ) {
            $metadata['subscription_id'] = $subscription_id;
        }

        $stripe_subscription_id = self::find_scalar_value_by_keys( $payload, ['stripe_subscription_id', 'stripeSubscriptionId'] );
        if ( $stripe_subscription_id !== null ) {
            $metadata['stripe_subscription_id'] = $stripe_subscription_id;
        }

        $stripe_price_id = self::find_scalar_value_by_keys( $payload, ['stripe_price_id', 'stripePriceId', 'price_id', 'priceId'] );
        if ( $stripe_price_id !== null ) {
            $metadata['stripe_price_id'] = $stripe_price_id;
        }

        $metadata = self::cleanup_metadata_array( $metadata );

        $result = [ 'metadata' => $metadata ];

        if ( $renews_at !== null ) {
            $result['renews_at'] = $renews_at;
        }

        if ( $expires_at !== null ) {
            $result['expires_at'] = $expires_at;
        }

        return $result;
    }

    private static function cleanup_metadata_array( array $metadata ): array {
        $clean = [];
        foreach ( $metadata as $key => $value ) {
            if ( $value === null ) {
                continue;
            }
            if ( is_string( $value ) ) {
                $trimmed = trim( $value );
                if ( $trimmed === '' ) {
                    continue;
                }
                $clean[ $key ] = $trimmed;
                continue;
            }
            if ( is_array( $value ) ) {
                $nested = self::cleanup_metadata_array( $value );
                if ( ! empty( $nested ) ) {
                    $clean[ $key ] = $nested;
                }
                continue;
            }
            if ( $value instanceof \DateTimeInterface ) {
                $clean[ $key ] = $value->format( 'c' );
                continue;
            }
            if ( is_scalar( $value ) ) {
                $clean[ $key ] = $value;
            }
        }

        return $clean;
    }

    private static function find_scalar_value_by_keys( array $payload, array $keys ): ?string {
        $normalized_keys = array_map(
            static function ( $key ) {
                return strtolower( preg_replace( '/[^a-z0-9]/', '', $key ) );
            },
            $keys
        );

        return self::recursive_find_scalar( $payload, $normalized_keys );
    }

    private static function recursive_find_scalar( $value, array $normalized_keys ): ?string {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                if ( is_string( $key ) ) {
                    $normalized = strtolower( preg_replace( '/[^a-z0-9]/', '', $key ) );
                    if ( in_array( $normalized, $normalized_keys, true ) ) {
                        if ( is_scalar( $child ) && $child !== '' ) {
                            return trim( (string) $child );
                        }
                        if ( $child instanceof \DateTimeInterface ) {
                            return $child->format( 'c' );
                        }
                    }
                }

                $found = self::recursive_find_scalar( $child, $normalized_keys );
                if ( $found !== null ) {
                    return $found;
                }
            }
        }

        return null;
    }

    private static function extract_datetime_from_payload( array $payload, array $preferred_paths, array $hints ): ?string {
        foreach ( $preferred_paths as $path ) {
            $value = self::value_by_path( $payload, $path );
            $normalized = self::normalize_datetime_value( $value );
            if ( $normalized !== null ) {
                return $normalized;
            }
        }

        return self::find_datetime_by_hints( $payload, $hints );
    }

    private static function value_by_path( array $payload, array $path ) {
        $cursor = $payload;
        foreach ( $path as $segment ) {
            if ( ! is_array( $cursor ) ) {
                return null;
            }

            if ( array_key_exists( $segment, $cursor ) ) {
                $cursor = $cursor[ $segment ];
                continue;
            }

            $found = null;
            $needle = strtolower( $segment );
            foreach ( $cursor as $key => $value ) {
                if ( is_string( $key ) && strtolower( $key ) === $needle ) {
                    $found = $value;
                    break;
                }
            }

            if ( $found === null ) {
                return null;
            }

            $cursor = $found;
        }

        return $cursor;
    }

    private static function find_datetime_by_hints( $payload, array $hints ): ?string {
        if ( is_array( $payload ) ) {
            foreach ( $payload as $key => $value ) {
                if ( is_string( $key ) ) {
                    $normalized_key = strtolower( preg_replace( '/[^a-z0-9]/', '', $key ) );
                    foreach ( $hints as $hint ) {
                        if ( strpos( $normalized_key, $hint ) !== false ) {
                            $normalized = self::normalize_datetime_value( $value );
                            if ( $normalized !== null ) {
                                return $normalized;
                            }
                        }
                    }
                }

                $nested = self::find_datetime_by_hints( $value, $hints );
                if ( $nested !== null ) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private static function normalize_datetime_value( $value ): ?string {
        if ( $value === null ) {
            return null;
        }

        if ( is_numeric( $value ) ) {
            $timestamp = (int) $value;
            if ( $timestamp <= 0 ) {
                return null;
            }
            if ( $timestamp > 9999999999 ) { // Likely milliseconds
                $timestamp = (int) round( $timestamp / 1000 );
            }
            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        if ( is_string( $value ) ) {
            $trimmed = trim( $value );
            if ( $trimmed === '' || $trimmed === '0000-00-00 00:00:00' ) {
                return null;
            }
            $timestamp = strtotime( $trimmed );
            if ( $timestamp && $timestamp > 0 ) {
                return gmdate( 'Y-m-d H:i:s', $timestamp );
            }
            return null;
        }

        if ( $value instanceof \DateTimeInterface ) {
            return gmdate( 'Y-m-d H:i:s', $value->getTimestamp() );
        }

        return null;
    }
}
