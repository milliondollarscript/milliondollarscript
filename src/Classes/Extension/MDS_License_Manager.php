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

        if ($status !== 'active' && !$force_refresh) {
            return false;
        }

        if ($status === 'active' && !$force_refresh && get_transient($transient_key)) {
            return true;
        }

        $plaintext = \MillionDollarScript\Classes\Extension\LicenseCrypto::decryptFromCompact( (string) $license->license_key );
        if ($plaintext === '') {
            $plaintext = (string) $license->license_key;
        }

        if ($plaintext === '') {
            $this->update_license( (int) $license->id, [ 'status' => 'inactive' ] );
            delete_transient( $transient_key );
            return false;
        }

        $validation = API::validate_license( $plaintext, $extension_slug );

        if ( is_array( $validation ) && ! empty( $validation['success'] ) && ! empty( $validation['valid'] ) ) {
            set_transient( $transient_key, 'valid', DAY_IN_SECONDS );

            if ( $status !== 'active' ) {
                $this->update_license( (int) $license->id, [ 'status' => 'active' ] );
            }

            return true;
        }

        $this->update_license( (int) $license->id, [ 'status' => 'inactive' ] );
        delete_transient( $transient_key );

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
}
