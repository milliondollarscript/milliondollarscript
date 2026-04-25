<?php

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

class MigrationReadinessReport {

	public const PAGE_OPTIONS = [
		'grid'          => [
			'option'     => 'grid-page',
			'label'      => 'Grid',
			'shortcodes' => [ 'milliondollarscript' ],
		],
		'order'         => [
			'option'     => 'users-order-page',
			'label'      => 'Order',
			'shortcodes' => [ 'milliondollarscript type="order"', "milliondollarscript type='order'" ],
		],
		'write-ad'      => [
			'option'     => 'users-write-ad-page',
			'label'      => 'Write Ad',
			'shortcodes' => [ 'milliondollarscript type="write-ad"', "milliondollarscript type='write-ad'" ],
		],
		'confirm-order' => [
			'option'     => 'users-confirm-order-page',
			'label'      => 'Confirm Order',
			'shortcodes' => [ 'milliondollarscript type="confirm-order"', "milliondollarscript type='confirm-order'" ],
		],
		'payment'       => [
			'option'     => 'users-payment-page',
			'label'      => 'Payment',
			'shortcodes' => [ 'milliondollarscript type="payment"', "milliondollarscript type='payment'" ],
		],
		'manage'        => [
			'option'     => 'users-manage-page',
			'label'      => 'Manage',
			'shortcodes' => [ 'milliondollarscript type="manage"', "milliondollarscript type='manage'" ],
		],
		'thank-you'     => [
			'option'     => 'users-thank-you-page',
			'label'      => 'Thank You',
			'shortcodes' => [ 'milliondollarscript type="thank-you"', "milliondollarscript type='thank-you'" ],
		],
		'list'          => [
			'option'     => 'users-list-page',
			'label'      => 'List',
			'shortcodes' => [ 'milliondollarscript type="list"', "milliondollarscript type='list'" ],
		],
		'upload'        => [
			'option'     => 'users-upload-page',
			'label'      => 'Upload',
			'shortcodes' => [ 'milliondollarscript type="upload"', "milliondollarscript type='upload'" ],
		],
		'no-orders'     => [
			'option'     => 'users-no-orders-page',
			'label'      => 'No Orders',
			'shortcodes' => [ 'milliondollarscript type="no-orders"', "milliondollarscript type='no-orders'" ],
		],
	];

	public static function build(): array {
		global $wpdb;

		$counts = [
			'banners'                    => self::count_table( MDS_DB_PREFIX . 'banners' ),
			'block_statuses'             => self::count_grouped( MDS_DB_PREFIX . 'blocks', 'status' ),
			'packages'                   => self::count_table( MDS_DB_PREFIX . 'packages' ),
			'price_zones'                => self::count_table( MDS_DB_PREFIX . 'prices' ),
			'order_statuses'             => self::count_grouped( MDS_DB_PREFIX . 'orders', 'status' ),
			'woocommerce_linked_orders'  => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
					'mds_order_id'
				)
			),
			'woocommerce_enabled'        => self::woocommerce_enabled(),
			'nfs_blocks'                 => self::count_where( MDS_DB_PREFIX . 'blocks', "status = 'nfs'" ),
			'unavailable_blocks'         => self::count_where( MDS_DB_PREFIX . 'blocks', "status IN ('reserved','ordered','cancelled','nfs')" ),
			'account_manage_renew_orders' => self::count_where( MDS_DB_PREFIX . 'orders', "status IN ('renew_wait','renew_paid')" ),
			'account_page_configured'    => absint( Options::get_option( 'account-page', 0 ) ) > 0,
			'mds_shortcode_pages'        => self::count_mds_shortcode_pages(),
		];

		$pages = self::collect_pages();
		$media = self::collect_media_health();

		return self::aggregate( $counts, $pages, $media );
	}

	public static function aggregate( array $counts, array $pages, array $media ): array {
		$block_statuses = self::normalize_counts( $counts['block_statuses'] ?? [] );
		$order_statuses = self::normalize_counts( $counts['order_statuses'] ?? [] );
		$page_counts    = [
			'configured'      => 0,
			'existing'        => 0,
			'missing'         => 0,
			'with_shortcode'  => 0,
			'without_shortcode' => 0,
		];

		foreach ( $pages as $page ) {
			if ( ! empty( $page['page_id'] ) ) {
				$page_counts['configured']++;
			}
			if ( ! empty( $page['exists'] ) ) {
				$page_counts['existing']++;
			} else {
				$page_counts['missing']++;
			}
			if ( ! empty( $page['has_shortcode'] ) ) {
				$page_counts['with_shortcode']++;
			} else if ( ! empty( $page['exists'] ) ) {
				$page_counts['without_shortcode']++;
			}
		}

		$flags = [
			'packages_price_zones' => [
				'label'   => 'Packages / price zones',
				'active'  => (int) ( $counts['packages'] ?? 0 ) > 0 || (int) ( $counts['price_zones'] ?? 0 ) > 0,
				'details' => sprintf(
					'%d package(s), %d price zone(s)',
					(int) ( $counts['packages'] ?? 0 ),
					(int) ( $counts['price_zones'] ?? 0 )
				),
			],
			'nfs_unavailable_blocks' => [
				'label'   => 'NFS / unavailable blocks',
				'active'  => (int) ( $counts['unavailable_blocks'] ?? 0 ) > 0,
				'details' => sprintf(
					'%d NFS block(s), %d unavailable block(s)',
					(int) ( $counts['nfs_blocks'] ?? 0 ),
					(int) ( $counts['unavailable_blocks'] ?? 0 )
				),
			],
			'account_manage_renew_flows' => [
				'label'   => 'Account / manage / renew flows',
				'active'  => ! empty( $counts['account_page_configured'] ) || self::page_exists( $pages, 'manage' ) || (int) ( $counts['account_manage_renew_orders'] ?? 0 ) > 0,
				'details' => sprintf(
					'Account page %s, manage page %s, %d renew order(s)',
					! empty( $counts['account_page_configured'] ) ? 'configured' : 'not configured',
					self::page_exists( $pages, 'manage' ) ? 'configured' : 'not configured',
					(int) ( $counts['account_manage_renew_orders'] ?? 0 )
				),
			],
			'woocommerce' => [
				'label'   => 'WooCommerce',
				'active'  => (int) ( $counts['woocommerce_linked_orders'] ?? 0 ) > 0 || ! empty( $counts['woocommerce_enabled'] ),
				'details' => sprintf(
					'%d WooCommerce-linked order(s)',
					(int) ( $counts['woocommerce_linked_orders'] ?? 0 )
				),
			],
			'page_shortcodes' => [
				'label'   => 'Page shortcodes',
				'active'  => (int) ( $counts['mds_shortcode_pages'] ?? 0 ) > 0 || $page_counts['with_shortcode'] > 0,
				'details' => sprintf(
					'%d configured page(s) with expected shortcode, %d total MDS shortcode page(s)',
					$page_counts['with_shortcode'],
					(int) ( $counts['mds_shortcode_pages'] ?? 0 )
				),
			],
		];

		return [
			'counts' => [
				'banners'                   => (int) ( $counts['banners'] ?? 0 ),
				'block_statuses'            => $block_statuses,
				'packages'                  => (int) ( $counts['packages'] ?? 0 ),
				'price_zones'               => (int) ( $counts['price_zones'] ?? 0 ),
				'order_statuses'            => $order_statuses,
				'woocommerce_linked_orders' => (int) ( $counts['woocommerce_linked_orders'] ?? 0 ),
				'pages'                     => $page_counts,
				'media'                     => [
					'missing_block_images'     => (int) ( $media['missing_block_images'] ?? 0 ),
					'invalid_block_images'     => (int) ( $media['invalid_block_images'] ?? 0 ),
					'missing_ad_attachments'   => (int) ( $media['missing_ad_attachments'] ?? 0 ),
					'invalid_ad_attachments'   => (int) ( $media['invalid_ad_attachments'] ?? 0 ),
				],
			],
			'pages'  => $pages,
			'flags'  => $flags,
		];
	}

	private static function collect_pages(): array {
		$pages = [];

		foreach ( self::PAGE_OPTIONS as $page_type => $config ) {
			$page_id = absint( Options::get_option( $config['option'], 0 ) );
			$post    = $page_id > 0 ? get_post( $page_id ) : null;
			$content = $post ? (string) $post->post_content : '';

			$pages[] = [
				'page_type'     => $page_type,
				'label'         => $config['label'],
				'option'        => $config['option'],
				'page_id'       => $page_id,
				'title'         => $post ? get_the_title( $post ) : '',
				'status'        => $post ? get_post_status( $post ) : '',
				'exists'        => (bool) $post,
				'has_shortcode' => $post ? self::contains_any_pattern( $content, $config['shortcodes'] ) : false,
			];
		}

		return $pages;
	}

	private static function collect_media_health(): array {
		global $wpdb;

		$missing_block_images = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . MDS_DB_PREFIX . "blocks WHERE status IN ('sold','ordered') AND image_data = ''"
		);

		$invalid_block_images = 0;
		$offset               = 0;
		$limit                = 200;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT block_id, banner_id, image_data FROM " . MDS_DB_PREFIX . "blocks WHERE image_data <> '' ORDER BY banner_id, block_id LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$image_data = base64_decode( (string) $row['image_data'], true );
				if ( false === $image_data || '' === $image_data || ! self::looks_like_image_binary( $image_data ) ) {
					$invalid_block_images++;
				}
			}

			$offset += $limit;
		} while ( count( $rows ) === $limit );

		$image_meta_key         = '_' . MDS_PREFIX . 'image';
		$missing_ad_attachments = 0;
		$invalid_ad_attachments = 0;
		$ad_images              = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
				$image_meta_key
			),
			ARRAY_A
		);

		foreach ( $ad_images as $ad_image ) {
			$attachment_id = absint( $ad_image['meta_value'] ?? 0 );
			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				$missing_ad_attachments++;
				continue;
			}

			$mime_type = get_post_mime_type( $attachment_id );
			if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/png', 'image/gif' ], true ) ) {
				$invalid_ad_attachments++;
			}
		}

		return [
			'missing_block_images'   => $missing_block_images,
			'invalid_block_images'   => $invalid_block_images,
			'missing_ad_attachments' => $missing_ad_attachments,
			'invalid_ad_attachments' => $invalid_ad_attachments,
		];
	}

	private static function count_table( string $table ): int {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function count_where( string $table, string $where ): int {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	private static function count_grouped( string $table, string $column ): array {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return [];
		}

		$rows = $wpdb->get_results(
			"SELECT {$column} AS item_key, COUNT(*) AS item_count FROM {$table} GROUP BY {$column} ORDER BY {$column}",
			ARRAY_A
		);

		$counts = [];
		foreach ( $rows as $row ) {
			$key = (string) ( $row['item_key'] ?? '' );
			if ( '' === $key ) {
				$key = '(empty)';
			}
			$counts[ $key ] = (int) ( $row['item_count'] ?? 0 );
		}

		return $counts;
	}

	private static function count_mds_shortcode_pages(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status IN ('publish','private','draft') AND post_content LIKE %s",
				'%[milliondollarscript%'
			)
		);
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	private static function normalize_counts( array $counts ): array {
		$normalized = [];
		foreach ( $counts as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key ) {
				$key = '(empty)';
			}
			$normalized[ $key ] = (int) $value;
		}

		ksort( $normalized );

		return $normalized;
	}

	private static function page_exists( array $pages, string $page_type ): bool {
		foreach ( $pages as $page ) {
			if ( ( $page['page_type'] ?? '' ) === $page_type ) {
				return ! empty( $page['exists'] );
			}
		}

		return false;
	}

	private static function woocommerce_enabled(): bool {
		return Options::get_option( 'woocommerce', 'no' ) === 'yes' || class_exists( 'WooCommerce' );
	}

	private static function contains_any_pattern( string $content, array $patterns ): bool {
		$normalized = strtolower( preg_replace( '/\s+/', ' ', $content ) );

		foreach ( $patterns as $pattern ) {
			if ( str_contains( $normalized, strtolower( $pattern ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function looks_like_image_binary( string $data ): bool {
		return str_starts_with( $data, "\xFF\xD8\xFF" )
			|| str_starts_with( $data, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" )
			|| str_starts_with( $data, "GIF87a" )
			|| str_starts_with( $data, "GIF89a" );
	}
}
