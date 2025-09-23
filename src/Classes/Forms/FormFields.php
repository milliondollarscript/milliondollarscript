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

namespace MillionDollarScript\Classes\Forms;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;
use Imagine\Image\Box;
use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Web\Permalinks;
use WP_Error;

defined( 'ABSPATH' ) or exit;

class FormFields {

	public static string $post_type = 'mds-pixel';
	protected static array $fields;
	protected static array $searchable_meta_keys = [];
	protected static bool $editor_assets_enqueued = false;

	public static function register_post_type(): void {
		register_post_type( self::$post_type,
			array(
				'labels'              => array(
					'name'          => __( 'MDS Pixels' ),
					'singular_name' => __( 'MDS Pixel' )
				),
				'public'              => true,
				'has_archive'         => false,
				'searchable'          => true,
				'exclude_from_search' => false,
				'rewrite'             => array( 'slug' => Permalinks::get_base() ),
			)
		);
	}

	public static function register_custom_post_status(): void {
		$statuses = self::get_statuses();
		foreach ( $statuses as $status => $label ) {
			register_post_status( $status, array(
				'label'                     => Language::get( $label ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
			) );
		}
	}

	public static function add_custom_post_status(): void {
		global $post;

		if ( $post->post_type == self::$post_type ) {
			$statuses = self::get_statuses();
			?>
            <script>
				jQuery(document).ready(function ($) {
					let $post_status = $("select#post_status");
					let $post_status_display, text;
					<?php
					foreach($statuses as $status => $label) {
					$selected = $post->post_status == $status ? 'selected=\'selected\'' : '';
					?>
					$post_status.append("<option value='<?php echo esc_attr( $status ); ?>' <?php echo $selected; ?>><?php Language::out( $label ); ?></option>");
					$post_status_display = $('#post-status-display');
					text = $post_status_display.text();
					if (text.toLowerCase() === '<?php echo esc_js( $status ); ?>') {
						$post_status_display.text('<?php echo esc_js( Language::get( $label ) ); ?>');
					}
					<?php } ?>

					$post_status.find('option').each(function () {
						if ($(this).val() === 'draft' || $(this).val() === 'publish' || $(this).val() === 'future') {
							$(this).remove();
						}
					});
				});
            </script>
			<?php
		}
	}

	public static function register(): void {
		Container::make( 'post_meta', 'MDS Pixels' )
		         ->where( 'post_type', '=', self::$post_type )
		         ->add_fields( self::get_fields() );
	}

	public static function get_fields(): array {
		$use_rich_text = Options::get_option( 'popup-rich-text', 'no' ) === 'yes';

		$text_field = $use_rich_text
			? Field::make( 'rich_text', MDS_PREFIX . 'text', Language::get( 'Popup Text' ) )
				->set_default_value( '' )
				->set_help_text( Language::get( 'Text to display in the popup that shows when the block is interacted with.' ) )
				->set_settings( [
					'toolbar'        => 'bold italic removeformat',
					'media_buttons'  => false,
					'quicktags'      => false,
					'drag_drop_upload' => false,
				] )
			: Field::make( 'text', MDS_PREFIX . 'text', Language::get( 'Popup Text' ) )
				->set_default_value( '' )
				->set_help_text( Language::get( 'Text to display in the popup that shows when the block is interacted with.' ) );

		$fields = [
			// Order ID
			Field::make( 'text', MDS_PREFIX . 'order', Language::get( 'Order ID' ) )
			     ->set_attribute( 'readOnly', true ),

			// Grid ID
			Field::make( 'text', MDS_PREFIX . 'grid', Language::get( 'Grid ID' ) )
			     ->set_attribute( 'readOnly', true ),

			// Text
			$text_field,

			// URL
			Field::make( 'text', MDS_PREFIX . 'url', Language::get( 'URL' ) )
			     ->set_default_value( '' )
			     ->set_help_text( Language::get( 'The URL to link to.' ) ),

			// Image
			Field::make( 'image', MDS_PREFIX . 'image', Language::get( 'Image' ) ),

		];

		self::$fields = apply_filters( 'mds_form_fields', $fields, MDS_PREFIX );

		return self::$fields;
	}

	/**
	 * Get the ad id from the order id.
	 *
	 * @param int $order_id
	 *
	 * @return \WP_Post|null
	 */
	public static function get_pixel_from_order_id( int $order_id ): \WP_Post|null {
		$args  = array(
			'meta_query'     => array(
				array(
					'key'   => '_' . MDS_PREFIX . 'order',
					'value' => $order_id
				)
			),
			'post_type'      => self::$post_type,
			'posts_per_page' => 1,
			'post_status'    => 'any'
		);
		$posts = get_posts( $args );
		if ( count( $posts ) > 0 ) {
			return $posts[0];
		}

		return null;
	}

	/**
	 * Display the fields.
	 * Note: Should already be translated here.
	 *
	 * @return bool
	 */
	public static function display_fields(): bool {
		$post_id = 0;
		$use_rich_text = Options::get_option( 'popup-rich-text', 'no' ) === 'yes';
		if ( $use_rich_text ) {
			self::enqueue_frontend_editor_assets();
		}
		if ( isset( $_REQUEST['aid'] ) ) {
			$post = get_post( $_REQUEST['aid'] );
			if ( $post != null ) {
				$post_id = $post->ID;
			}
		} else if ( isset( $_REQUEST['order_id'] ) ) {
			$post = self::get_pixel_from_order_id( intval( $_REQUEST['order_id'] ) );
			if ( $post != null ) {
				$post_id = $post->ID;
			}
		}

		// Check for any pixel post for the current order id.
		if ( empty( $post_id ) ) {
			$order_id = Orders::get_current_order_id();
			if ( $order_id !== null ) {
				$post = self::get_pixel_from_order_id( $order_id );
				if ( $post != null ) {
					$post_id = $post->ID;
				}
			}
		}

		$error_message = get_user_meta( get_current_user_id(), 'error_message', true );
		delete_user_meta( get_current_user_id(), 'error_message' );

		$show_required = false;

		$fields = self::get_fields();
		foreach ( $fields as $field ) {
			$field_name = $field->get_base_name();

			$readOnly = $field->get_attribute( 'readOnly' );

			if ( ! $readOnly ) {
				echo '<label for="' . esc_attr( $field_name ) . '">' . esc_html( $field->get_label() );
			}

			$required = false;
			if ( $field_name == MDS_PREFIX . 'text' && Options::get_option( 'text-optional' ) == 'no' ) {
				$required = true;
			} else if ( $field_name == MDS_PREFIX . 'url' && Options::get_option( 'url-optional' ) == 'no' ) {
				$required = true;
			} else if ( $field_name == MDS_PREFIX . 'image' && Options::get_option( 'image-optional', true ) == 'no' ) {
				$required = true;
			}

			$required = apply_filters( 'mds_form_field_required', $required, $field, $post_id );

			if ( $required ) {
				$show_required = true;
				echo '*';
			}

			if ( ! $readOnly ) {
				echo '</label>';
			}

			$value = "";

			$field_type = $field->get_type();
			if ( $field_type === 'text' ) {
				if ( ! empty( $post_id ) ) {
					$value = carbon_get_post_meta( $post_id, $field_name );
				}

				if ( $field_name === MDS_PREFIX . 'text' ) {
					$value = self::sanitize_plain_text_value( (string) $value, $field_name );
				}

				if ( $field_name == MDS_PREFIX . 'order' ) {
					echo '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( Orders::get_current_order_id() ) . '">';
				} else if ( $field_name == MDS_PREFIX . 'grid' ) {
					global $f2;
					$BID = $f2->bid();
					echo '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . intval( $BID ) . '">';
				} else {
					echo '<input type="text" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '">';
				}
			} else if ( $field_type === 'rich_text' ) {
				if ( ! empty( $post_id ) ) {
					$value = carbon_get_post_meta( $post_id, $field_name );
				}

				$editor_settings = [
					'tinymce' => [
						'toolbar1'              => 'bold italic | removeformat',
						'toolbar2'              => '',
						'menubar'               => false,
						'statusbar'             => false,
						'branding'              => false,
						'paste_as_text'         => true,
						'block_formats'         => 'Paragraph=p;',
						'forced_root_block'     => 'p',
						'valid_elements'        => 'p,strong/b,em/i,br',
						'valid_children'        => '+body[p]',
						'extended_valid_elements'=> 'p,strong/b,em/i,br',
						'content_style'        => 'body{margin:0;padding:8px;font-size:14px;line-height:1.4;}',
					],
					'quicktags' => false,
				];

				echo '<textarea class="mds-popup-rich-text" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" rows="6">' . esc_textarea( $value ) . '</textarea>';
				self::print_rich_text_initializer( $field_name, $editor_settings );
			} else if ( $field->get_type() === 'image' ) {
				if ( ! empty( $post_id ) ) {
					$image_id = carbon_get_post_meta( $post_id, $field_name );
				}

				if ( ! empty( $image_id ) ) {
					$image_url = wp_get_attachment_url( $image_id );
					echo '<img src="' . esc_url( $image_url ) . '" alt="">';
				}

				echo '<input type="file" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '">';
			}

			do_action( 'mds_form_field_display', $field, $value, $post_id );

			if ( ! empty( $error_message[ $field_name ] ) ) {
				echo '<div class="mds-error">' . $error_message[ $field_name ] . '</div>';
			}
		}

		// Manage Pixels page
		if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'manage' && isset( $_REQUEST['aid'] ) ) {
			echo '<input type="hidden" id="manage-pixels" name="manage-pixels" value="' . intval( $_REQUEST['aid'] ) . '">';
		}

		return $show_required;
	}

	/**
	 * Get the current user's mds-pixel post id in new status if it exists. Otherwise, return false.
	 *
	 * @param $status
	 *
	 * @return bool|int
	 */
	public static function get_post_id( $status ): bool|int {

		// Check valid status was passed
		if ( ! in_array( $status, array_keys( self::get_statuses() ) ) ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$user_posts   = get_posts( array(
			'author'      => $current_user->ID,
			'post_status' => $status,
			'post_type'   => self::$post_type
		) );

		if ( ! empty( $user_posts ) ) {
			return $user_posts[0]->ID;
		}

		return false;
	}

	/**
	 * Retrieves the post ID by searching for a specific meta value.
	 *
	 * @param string $status The post status to search for.
	 * @param string $key The meta key to search for.
	 * @param mixed $value The meta value to search for.
	 *
	 * @return int|bool The post ID if found, false otherwise.
	 */
	public static function get_post_id_by_meta_value( string $status, string $key, mixed $value ): bool|int {
		$args = array(
			'post_type'   => self::$post_type,
			'post_status' => $status,
			'meta_query'  => array(
				array(
					'key'     => MDS_PREFIX . $key,
					'value'   => $value,
					'compare' => '=',
				),
			),
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			$post_ids = $query->posts;
			wp_reset_postdata();

			return $post_ids[0]->ID;
		}

		return false;
	}

	/**
	 * Add the form fields to the current user's mds-pixel post.
	 *
	 * @return int|\WP_Error
	 */
	public static function add(): int|array|WP_Error {
		$errors = '';
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

			global $wpdb;

			$current_user = wp_get_current_user();

			// Use the text field as the post title or username as backup
			$raw_title_input = self::normalize_raw_input( $_POST[ MDS_PREFIX . 'text' ] ?? null );
			$raw_title       = $raw_title_input !== '' ? $raw_title_input : $current_user->user_login;
			$post_title = sanitize_text_field( $raw_title ); // Keep original for title
			
			// Generate slug: Use transliterated title if setting is enabled
			$transliterate_slugs = Options::get_option( 'transliterate-slugs' );
			$post_name = '';
			if ( $transliterate_slugs === 'on' ) {
				$slug_base = Functions::transliterate_cyrillic_to_latin( $raw_title );
				$post_name = sanitize_title( $slug_base );
			} // Otherwise, let WordPress generate the slug from $post_title

			if ( isset( $_REQUEST['manage-pixels'] ) ) {
				$post_id = intval( $_REQUEST['manage-pixels'] );
				if ( get_current_user_id() != get_post_field( 'post_author', $post_id ) ) {
					return new WP_Error( 'unauthorized', Language::get( 'Sorry, you are not allowed to access this page.' ) );
				}
			} else {

				// First check if the current order already has an associated ad_id to prevent duplicate posts
				$current_order_id = Orders::get_current_order_id();
				$existing_ad_id = null;
				if ( $current_order_id ) {
					$existing_ad_id = Orders::get_ad_id_from_order_id( $current_order_id );
				}

				if ( $existing_ad_id && get_post( $existing_ad_id ) ) {
					// The current order already has an associated post, use it to prevent duplicates
					$post_id = $existing_ad_id;
					
					// Verify the current user owns this post for security
					if ( get_current_user_id() != get_post_field( 'post_author', $post_id ) ) {
						return new WP_Error( 'unauthorized', Language::get( 'Sorry, you are not allowed to access this page.' ) );
					}
				} else {
					// No existing post for this order, check if the user has a new mds-pixel post
					$user_posts = get_posts( array(
						'author'      => $current_user->ID,
						'post_status' => 'new',
						'post_type'   => self::$post_type
					) );

					if ( ! empty( $user_posts ) ) {
						// The user already has a new post of the given post type, so an order must be in progress
						$post_id = $user_posts[0]->ID;
					} else {
						// Insert a new mds-pixel post
						$post_data = [
							'post_title'  => $post_title,
							'post_status' => 'new',
							'post_type'   => self::$post_type
						];
						if ( !empty($post_name) ) {
							$post_data['post_name'] = $post_name;
						}
						$post_id = wp_insert_post( $post_data );
						// Apply configured slug pattern immediately after creation
						if ( ! is_wp_error( $post_id ) && $post_id ) {
							Permalinks::sync_post_slug( (int) $post_id );
						}
					}
				}
			}

			$fields = self::get_fields();

			$errors = [];

			foreach ( $fields as $field ) {
				$field_name = $field->get_base_name();
				$value      = $_POST[ $field_name ] ?? null;

				if ( isset( $_POST[ $field_name ] ) && is_string( $_POST[ $field_name ] ) ) {
					$value = self::normalize_raw_input( $_POST[ $field_name ] );
				}

				if ( isset( $_POST[ $field_name ] ) || isset( $_FILES[ $field_name ] ) ) {
					$field_type = $field->get_type();
					if ( in_array( $field_type, [ 'text', 'textarea', 'rich_text' ], true ) ) {
						if ( ! empty( $value ) ) {

							// If post already has an order and grid don't set them again.
							if ( $field_name == MDS_PREFIX . 'order' || $field_name == MDS_PREFIX . 'grid' ) {
								$grid_id  = carbon_get_post_meta( $post_id, MDS_PREFIX . 'grid' );
								$order_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'order' );
								if ( empty( $grid_id ) || empty( $order_id ) ) {
									// No order or grid set on this post yet, so it's a new one, and we must set them.
									if ( $field_name == MDS_PREFIX . 'order' ) {
										carbon_set_post_meta( $post_id, $field_name, Orders::get_current_order_id() );
									} else if ( $field_name == MDS_PREFIX . 'grid' ) {
										$grid_id = $wpdb->get_var( $wpdb->prepare( "SELECT banner_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", intval( Orders::get_current_order_id() ) ) );
										carbon_set_post_meta( $post_id, $field_name, $grid_id );
									}
								} else {
									// Order or grid already set on this post, so user is editing their order.

									if ( $field_name == MDS_PREFIX . 'grid' ) {
										// Disapprove order if auto-approve is disabled for this grid.
										$auto_approve = $wpdb->get_var( $wpdb->prepare( "SELECT auto_approve FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d", $grid_id ) );
										if ( $auto_approve == 'N' ) {
											// Disapprove order
											$wpdb->update( MDS_DB_PREFIX . 'orders', [
												'approved' => 'N',
											], [
												'banner_id' => $grid_id,
												'order_id'  => $order_id
											] );

											// Disapprove blocks
											$wpdb->update( MDS_DB_PREFIX . 'blocks', [
												'approved' => 'N',
											], [
												'ad_id' => $post_id
											] );

											// Process pixels
											process_image( $grid_id );
											publish_image( $grid_id );
											process_map( $grid_id );
										}
									}
								}
							}

						if ( $field_name != MDS_PREFIX . 'order' && $field_name != MDS_PREFIX . 'grid' ) {
							// Update other fields besides order and grid if it's new or not.
							if ( is_string( $value ) ) {
								$raw_value = $value;
								if ( $field_type === 'rich_text' ) {
									$value = self::sanitize_rich_text_value( $value );
								} else {
									$value = self::sanitize_plain_text_value( $value, $field_name );
								}
								$value = self::filter_field_value( $field_name, $value, $field_type, (int) $post_id, $raw_value );
							}
							carbon_set_post_meta( $post_id, $field_name, $value );
							}

							// Update blocks
							if ( $field_name == MDS_PREFIX . 'text' ) {
								$wpdb->update( MDS_DB_PREFIX . 'blocks', [
									'alt_text' => $value,
								], [
									'ad_id' => $post_id
								] );
							} else if ( $field_name == MDS_PREFIX . 'url' ) {
								$wpdb->update( MDS_DB_PREFIX . 'blocks', [
									'url' => esc_url_raw( $value ), // Ensure URL is properly sanitized
								], [
									'ad_id' => $post_id
								] );
							}
						}
						if ( empty( $value ) ) {
							// The field was empty so check if it's optional
							if ( ( $field_name == MDS_PREFIX . 'text' && Options::get_option( 'text-optional' ) == 'no' ) ||
							     ( $field_name == MDS_PREFIX . 'url' && Options::get_option( 'url-optional' ) == 'no' ) ) {
								// The field isn't optional so add an error
								$errors[ $field_name ] = Language::get_replace( 'The %FIELD% field is required.', '%FIELD%', $field->get_label() );
							}
						}
					} else if ( $field->get_type() === 'image' ) {
						// Check if the file was uploaded
						if ( isset( $_FILES[ $field_name ] ) && $_FILES[ $field_name ]['error'] === UPLOAD_ERR_OK ) {
							$file = $_FILES[ $field_name ];

							// Enforce upload dimension limits before processing the file
							$dimension_limits = Options::get_upload_dimension_limits();
							if ( ( $dimension_limits['width'] ?? 0 ) > 0 || ( $dimension_limits['height'] ?? 0 ) > 0 ) {
								$image_size = @getimagesize( $file['tmp_name'] );
								if ( $image_size === false ) {
									$errors[ $field_name ] = Language::get( 'Unable to read the uploaded image dimensions. Please upload a valid image file.' );
									continue;
								}

								list( $uploaded_width, $uploaded_height ) = $image_size;
								$width_limit  = $dimension_limits['width'];
								$height_limit = $dimension_limits['height'];

								$width_violation  = $width_limit && $uploaded_width > $width_limit;
								$height_violation = $height_limit && $uploaded_height > $height_limit;

								if ( $width_violation || $height_violation ) {
									if ( $width_violation && $height_violation ) {
										$errors[ $field_name ] = Language::get_replace(
											'The uploaded image (%ACTUAL_WIDTH% × %ACTUAL_HEIGHT% pixels) exceeds the maximum allowed dimensions of %WIDTH% × %HEIGHT% pixels.',
											[ '%ACTUAL_WIDTH%', '%ACTUAL_HEIGHT%', '%WIDTH%', '%HEIGHT%' ],
											[ $uploaded_width, $uploaded_height, $width_limit, $height_limit ]
										);
									} else if ( $width_violation ) {
										$errors[ $field_name ] = Language::get_replace(
											'The uploaded image width (%ACTUAL_WIDTH% pixels) exceeds the maximum of %WIDTH% pixels.',
											[ '%ACTUAL_WIDTH%', '%WIDTH%' ],
											[ $uploaded_width, $width_limit ]
										);
									} else {
										$errors[ $field_name ] = Language::get_replace(
											'The uploaded image height (%ACTUAL_HEIGHT% pixels) exceeds the maximum of %HEIGHT% pixels.',
											[ '%ACTUAL_HEIGHT%', '%HEIGHT%' ],
											[ $uploaded_height, $height_limit ]
										);
									}

									continue;
								}
							}

							// Upload the file
							$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

							// Check if the upload succeeded
							if ( $upload && ! isset( $upload['error'] ) ) {
								// The upload succeeded, save the file URL
								$value = $upload['url'];

								// Create an attachment post for the uploaded file
								$attachment = array(
									'guid'           => $value,
									'post_mime_type' => $upload['type'],
									'post_title'     => wp_basename( $value, "." . pathinfo( $value, PATHINFO_EXTENSION ) ),
									'post_content'   => '',
									'post_status'    => 'inherit'
								);
								$attach_id  = wp_insert_attachment( $attachment, $upload['file'] );

								// Generate the metadata for the attachment
								$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
								wp_update_attachment_metadata( $attach_id, $attach_data );

								// Copy the file using the Filesystem class
								// $url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
								// $filesystem = new Filesystem( $url );
								// $dest       = get_tmp_img_name();
								//
								// if ( ! $filesystem->copy( $upload['file'], $dest ) ) {
								// }

								carbon_set_post_meta( $post_id, $field_name, $attach_id );
								
								// Trigger grid regeneration when image is updated
								$grid_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'grid' );
								if ( $grid_id ) {
									// Process and regenerate the grid image with the new pixel image
									process_image( $grid_id );
									publish_image( $grid_id );
									process_map( $grid_id );
								}
							} else {
								// The upload failed, add an error message
								$errors[ $field_name ] = $upload['error'];
							}
						} else {
							if ( Options::get_option( 'image-optional', true ) == 'no' ) {
								$errors[ $field_name ] = Language::get_replace( 'The %FIELD% field is required.', '%FIELD%', $field->get_label() );
							}
						}
					}
				} else {
					$field_data = [
						'name'  => $field_name,
						'type'  => $field->get_type(),
						'value' => $value
					];

					/*
					Example custom field validation:

					add_filter( 'mds_validate_custom_field', function ( $errors, $field_data ) {
						if ( $field_data['type'] === 'number' ) {
							if ( ! is_numeric( $value ) ) {
								$errors[ $field_data['name'] ] = 'Please enter a number.';
							} else if ( ! empty( $value ) ) {
								$value = sanitize_text_field( $field_data['value'] );
							}
						}

						return $errors;
					}, 10, 2 );
					 */
					$custom_errors = apply_filters( 'mds_validate_custom_field', [], $field_data );

					$errors = array_merge( $errors, $custom_errors );
				}
			}

			if ( empty( $errors ) ) {
				if ( ! is_wp_error( $post_id ) ) {
					$resolved_title = $post_title !== '' ? $post_title : $current_user->user_login;
					wp_update_post( [
						'ID'         => $post_id,
						'post_title' => $resolved_title,
					] );
					Permalinks::sync_post_slug( (int) $post_id );
				}
				return $post_id;
			}
		}

		if ( ! empty( $errors ) ) {
			update_user_meta( get_current_user_id(), 'error_message', $errors );
		}

		return $errors;
	}

	/**
	 * Load Carbon Fields.
	 *
	 * @return void
	 */
	public static function load(): void {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	/**
	 * Hook into Carbon Fields filter to sanitize some fields on save.
	 *
	 * @param $post_id
	 *
	 * @return int
	 */
	public static function save( $post_id ): int {
		$post = get_post( $post_id );
		if ( $post->post_type !== self::$post_type ) {
			return $post_id;
		}

		global $wpdb;

		$fields = self::get_fields();

		foreach ( $fields as $field ) {
			$name = str_replace( '_' . MDS_PREFIX, '', $field->get_name() );

			switch ( $name ) {
				case 'order':
					$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE ad_id = %d", intval( $post_id ) ) );
					$field->set_value( (int) $order_id );
					break;
				case 'grid':
					$grid_id = $wpdb->get_var( $wpdb->prepare( "SELECT banner_id FROM " . MDS_DB_PREFIX . "orders WHERE ad_id = %d", intval( $post_id ) ) );
					$field->set_value( (int) $grid_id );
					break;
				case 'image':

					$attachment_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'image' );

					// Resize if option is enabled
					if ( Options::get_option( 'resize' ) == 'YES' ) {
						$imagine = "";
						if ( class_exists( 'Imagick' ) ) {
							$imagine = new \Imagine\Imagick\Imagine();
						} else if ( function_exists( 'gd_info' ) ) {
							$imagine = new \Imagine\Gd\Imagine();
						}

						$max_image_size = Options::get_option( 'max-image-size', 332 );

						if ( ! empty( $attachment_id ) ) {
							$image_path = get_attached_file( $attachment_id );

							$image = $imagine->open( $image_path );
							$size  = $image->getSize();

							if ( $size->getWidth() > $max_image_size || $size->getHeight() > $max_image_size ) {
								$width_ratio  = $size->getWidth() / $max_image_size;
								$height_ratio = $size->getHeight() / $max_image_size;

								$ratio = max( $width_ratio, $height_ratio );

								$new_width  = $size->getWidth() / $ratio;
								$new_height = $size->getHeight() / $ratio;

								$new_size = new Box( $new_width, $new_height );

								$image->resize( $new_size );

								$image->save( $image_path );
							}
						}
					}

					break;
				case 'text':
					$value = $field->get_value();
					if ( is_string( $value ) ) {
						$value = self::normalize_raw_input( $value );
						$value = sanitize_text_field( $value );
					}
					$field->set_value( $value );
					break;
				case 'url':
					$value = $field->get_value();
					if ( is_string( $value ) ) {
						$value = self::normalize_raw_input( $value );
						$value = esc_url_raw( $value );
					}
					$field->set_value( $value );
					break;
				default:
					break;
			}

			do_action( 'mds_form_save', $field, $name, $post_id );
		}

		return $post_id;
	}

	/**
	 * Adds a quick edit status to the post.
	 * @return void
	 */
	public static function add_quick_edit_status(): void {
		global $post;
		if ( empty( $post ) || $post->post_type != self::$post_type ) {
			return;
		}

		$statuses = self::get_statuses();
		?>
        <script type="text/javascript">
			jQuery(document).ready(function ($) {
				$(document).on('click', '.editinline', function () {
					const $post_status = $('.inline-edit-row select[name="_status"]');
					const validStatuses = <?php echo json_encode( $statuses ); ?>;

					// Loop through each status in the PHP array
					<?php foreach ($statuses as $status_key => $status_label) : ?>
					$post_status.append(new Option('<?php echo $status_label; ?>', '<?php echo $status_key; ?>'));
					<?php endforeach; ?>

					// Remove options not in the status array
					$post_status.find('option').each(function () {
						if (!validStatuses.hasOwnProperty($(this).val())) {
							$(this).remove();
						}
					});
				});
			});
        </script>
		<?php
	}

	/**
	 * Adds custom columns.
	 *
	 * @param array $columns The array of columns to add custom columns to.
	 *
	 * @return array The updated array of columns.
	 */
	public static function add_custom_columns( array $columns ): array {
		$new_columns              = array();
		$new_columns['cb']        = $columns['cb'];
		$new_columns['title']     = $columns['title'];
		$new_columns['pixels']    = Language::get( 'Pixels' );
		$new_columns['grid']      = Language::get( 'Grid' );
		$new_columns['expiry']    = Language::get( 'Expiry Date' );
		$new_columns['approved']  = Language::get( 'Approved' );
		$new_columns['published'] = Language::get( 'Published' );
		$new_columns['status']    = Language::get( 'Status' );
		foreach ( $columns as $key => $title ) {
			if ( $key == 'cb' || $key == 'title' ) {
				continue;
			}
			$new_columns[ $key ] = $title;
		}

		return $new_columns;
	}

	/**
	 * A function to fill the custom columns.
	 *
	 * @param string $column The name of the column.
	 *
	 * @return void
	 */
	public static function fill_custom_columns( string $column ): void {
		global $post;
		if ( $column == 'grid' ) {
			$grid_id = carbon_get_post_meta( $post->ID, MDS_PREFIX . 'grid' );
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=edit&BID=' . intval( $grid_id ) ) ) . '">' . intval( $grid_id ) . '</a>';
		} else if ( $column == 'pixels' ) {
			// Get the blocks for this order to display
			$grid_id = carbon_get_post_meta( $post->ID, MDS_PREFIX . 'grid' );
			?>
            <img style="max-width:50px;height:auto;" src="<?php echo esc_url( Utility::get_page_url( 'get-order-image', [ 'BID' => intval( $grid_id ), 'aid' => $post->ID ] ) ); ?>" alt=""/>
			<?php
		} else if ( $column == 'expiry' ) {
			// Get the order id
			$order_id = intval( carbon_get_post_meta( $post->ID, MDS_PREFIX . 'order' ) );

			// Get the order expiration date
			$expiry = Orders::get_order_expiration_date( $order_id );

			apply_filters( 'mds_order_expiry', $expiry, $order_id );

			if ( ! empty( $expiry ) ) {
				echo esc_html( $expiry );

				// Output if it's expired
				if ( $expiry != 0 && strtotime( $expiry ) < time() ) {
					echo ' <span style="color:red;">' . Language::get( 'Expired' ) . '</span>';
				}

			} else {
				if ( $expiry == 0 ) {
					Language::out( 'Never' );
				} else {
					Language::out( 'Not Yet Published' );
				}
			}

		} else if ( $column == 'approved' ) {
			$approved = Orders::get_order_approved_status( $post->ID );
			if ( $approved == 'Y' ) {
				echo '<a style="color:green;" href="' . esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=Y' ) ) . '">' . Language::get( 'Yes' ) . '</a>';
			} else {
				echo '<a style="color:red;" href="' . esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=N' ) ) . '">' . Language::get( 'No' ) . '</a>';
			}

		} else if ( $column == 'published' ) {
			$published = Orders::get_order_published_status( $post->ID );
			if ( $published == 'Y' ) {
				echo '<a style="color:green;" href="' . esc_url( admin_url( 'admin.php?page=mds-process-pixels' ) ) . '">' . Language::get( 'Yes' ) . '</a>';
			} else {
				echo '<a style="color:red;" href="' . esc_url( admin_url( 'admin.php?page=mds-process-pixels' ) ) . '">' . Language::get( 'No' ) . '</a>';
			}

		} else if ( $column == 'status' ) {
			$status_object = get_post_status_object( $post->post_status );
			Language::out( $status_object->label );
		}
	}

	/**
	 * Generate the function comment for the given function body.
	 *
	 * @param array $columns The array of columns.
	 *
	 * @return array The updated array of columns.
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['grid']      = 'grid';
		$columns['pixels']    = 'pixels';
		$columns['expiry']    = 'expiry';
		$columns['approved']  = 'approved';
		$columns['published'] = 'published';
		$columns['status']    = 'status';

		return $columns;
	}

	/**
	 * Orders the query by grid.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_grid( mixed $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'grid' === $query->get( 'orderby' ) ) {
			add_filter( 'posts_clauses', [ __CLASS__, 'modify_orderby_grid' ], 10, 2 );
		}
	}

	/**
	 * Modifies the query to order by grid.
	 *
	 * @param array $clauses The query clauses.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return array The modified query clauses.
	 */
	public static function modify_orderby_grid( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		if ( 'grid' === $query->get( 'orderby' ) ) {

			// Define the post meta key that will be used for the join and order by
			$grid_meta_key = '_' . MDS_PREFIX . 'grid';

			// Join the posts table with the postmeta table using the meta_key
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '$grid_meta_key'";

			// Order by the Grid ID
			$clauses['orderby'] = "{$wpdb->postmeta}.meta_value " . $query->get( 'order' );
		}

		return $clauses;
	}

	/**
	 * Orders the query by expiry.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_expiry( mixed $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'expiry' === $query->get( 'orderby' ) ) {
			// Add filter to modify the query
			add_filter( 'posts_clauses', [ __CLASS__, 'modify_orderby_expiry' ], 10, 2 );
		}
	}

	/**
	 * Modifies the query to order by expiry.
	 *
	 * @param array $clauses The query clauses.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return array The modified query clauses.
	 */
	public static function modify_orderby_expiry( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		if ( 'expiry' === $query->get( 'orderby' ) ) {

			// Define the post meta key that will be used for the join
			$order_meta_key = '_' . MDS_PREFIX . 'order';

			// Join the orders table and banners table with the posts table using the meta_key
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '$order_meta_key'
                              LEFT JOIN " . MDS_DB_PREFIX . "orders ON " . MDS_DB_PREFIX . "orders.order_id = {$wpdb->postmeta}.meta_value
                              LEFT JOIN " . MDS_DB_PREFIX . "banners ON " . MDS_DB_PREFIX . "banners.banner_id = " . MDS_DB_PREFIX . "orders.banner_id";

			// Order by the calculated `expiry` date
			$clauses['orderby'] = "DATE_ADD(" . MDS_DB_PREFIX . "orders.date_published, INTERVAL " . MDS_DB_PREFIX . "banners.days_expire DAY) " . $query->get( 'order' );
		}

		return $clauses;
	}

	/**
	 * Orders the query by approved.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_approved( mixed $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'approved' === $query->get( 'orderby' ) ) {
			// Add filter to modify the query
			add_filter( 'posts_clauses', [ __CLASS__, 'modify_orderby_approved' ], 10, 2 );
		}
	}

	/**
	 * Modifies the query to order by approved.
	 *
	 * @param array $clauses The query clauses.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return array The modified query clauses.
	 */
	public static function modify_orderby_approved( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		if ( 'approved' === $query->get( 'orderby' ) ) {

			// MDS order meta key
			$order_meta_key = '_' . MDS_PREFIX . 'order';

			// Join the orders table with the posts table using the meta_key
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '$order_meta_key'
                              LEFT JOIN " . MDS_DB_PREFIX . "orders ON " . MDS_DB_PREFIX . "orders.order_id = {$wpdb->postmeta}.meta_value";

			// Order by the `approved` status
			$clauses['orderby'] = MDS_DB_PREFIX . "orders.approved " . $query->get( 'order' );
		}

		return $clauses;
	}


	/**
	 * Orders the query by published.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_published( mixed $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'published' === $query->get( 'orderby' ) ) {
			// Add filter to modify the query
			add_filter( 'posts_clauses', [ __CLASS__, 'modify_orderby_published' ], 10, 2 );
		}
	}

	/**
	 * Modifies the query to order by published.
	 *
	 * @param array $clauses The query clauses.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return array The modified query clauses.
	 */
	public static function modify_orderby_published( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		if ( 'published' === $query->get( 'orderby' ) ) {

			// MDS order meta key
			$order_meta_key = '_' . MDS_PREFIX . 'order';

			// Join the orders table with the posts table using the meta_key
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '$order_meta_key'
                              LEFT JOIN " . MDS_DB_PREFIX . "orders ON " . MDS_DB_PREFIX . "orders.order_id = {$wpdb->postmeta}.meta_value";

			// Order by the `published` status
			$clauses['orderby'] = MDS_DB_PREFIX . "orders.published " . $query->get( 'order' );
		}

		return $clauses;
	}

	/**
	 * Orders the query by post status.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_status( mixed $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'status' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'post_status' );
		}
	}

	/**
	 * Returns an array of valid MDS order statuses.
	 *
	 * The valid MDS order statuses are: 'paid', 'denied', 'completed', 'cancelled',
	 * 'confirmed', 'new', 'expired', 'deleted', 'renew_wait',
	 * 'renew_paid', 'waiting', and 'reserved'.
	 *
	 * @return array An array of valid MDS order statuses.
	 */
	public static function get_statuses(): array {
		// Valid MDS order statuses: 'pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid','denied'
		// Note: pending is a default WP status, so it isn't included here.
		// Note: waiting is confirmed OR pending
		return array(
			'cancelled'  => 'Cancelled',
			'completed'  => 'Completed',
			'confirmed'  => 'Confirmed',
			'deleted'    => 'Deleted',
			'expired'    => 'Expired',
			'new'        => 'New',
			'renew_paid' => 'Renewed',
			'renew_wait' => 'Awaiting Renewal',
			'reserved'   => 'Reserved',
			'waiting'    => 'Waiting',
			'paid'       => 'Paid',
			'denied'     => 'Denied',
		);
	}

	/**
	 * Exclude from search.
	 *
	 * @param \WP_Query $query
	 */
	public static function modify_search_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		if ( Options::get_option( 'exclude-from-search', 'no' ) == 'yes' ) {
			$post_types_to_exclude = [ self::$post_type ];

			if ( $query->get( 'post_type' ) ) {
				$query_post_types = $query->get( 'post_type' );

				if ( is_string( $query_post_types ) ) {
					$query_post_types = explode( ',', $query_post_types );
				}
			} else {
				$query_post_types = get_post_types( [ 'exclude_from_search' => false ] );
			}

			do_action( 'mds_modify_search_query', $query_post_types, $post_types_to_exclude );

			if ( sizeof( array_intersect( $query_post_types, $post_types_to_exclude ) ) ) {
				$query->set( 'post_type', array_diff( $query_post_types, $post_types_to_exclude ) );
			}
		}
	}

	/**
	 * Use posts search filter to add the ability to also search by our custom text field.
	 *
	 * @param string $search
	 * @param \WP_Query $query
	 *
	 * @return string
	 */
	public static function posts_search( string $search, \WP_Query $query ): string {
		global $wpdb;

			$s = $query->get( 's' );

			if ( is_string( $s ) ) {
				$s = self::normalize_raw_input( $s );
			}

		if ( empty( $s ) ) {
			return $search;
		}

		// Check if MDS pixel template is enabled and exclude-from-search is disabled
		if ( Options::get_option( 'exclude-from-search', 'no' ) == 'no' ) {

				$s = sanitize_text_field( $s );
				$searchable_statuses = apply_filters(
					'mds_searchable_pixel_statuses',
					[ 'completed', 'renew_paid' ]
				);
				$searchable_statuses = array_values( array_filter( array_unique( array_map( 'sanitize_key', (array) $searchable_statuses ) ) ) );

				if ( empty( $searchable_statuses ) ) {
					$searchable_statuses = [ 'completed' ];
				}

				$status_placeholders = implode( ', ', array_fill( 0, count( $searchable_statuses ), '%s' ) );
				$search_variants     = self::build_search_variants( $s );
				if ( empty( $search_variants ) ) {
					$search_variants = [ $s ];
				}

				$title_like_clauses = [];
				$title_like_args    = [];
				foreach ( $search_variants as $variant ) {
					$title_like_clauses[] = "{$wpdb->posts}.post_title LIKE %s";
					$title_like_args[]    = '%' . $wpdb->esc_like( $variant ) . '%';
				}

				$meta_keys           = self::get_searchable_meta_keys();
				$pixel_match_clauses = [];
				$pixel_match_args    = [];

				if ( ! empty( $title_like_clauses ) ) {
					$pixel_match_clauses[] = '( ' . implode( ' OR ', $title_like_clauses ) . ' )';
					$pixel_match_args      = array_merge( $pixel_match_args, $title_like_args );
				}

				if ( ! empty( $meta_keys ) ) {
					$meta_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
					$meta_like_clauses = [];
					$meta_like_args    = [];

					foreach ( $search_variants as $variant ) {
						$meta_like_clauses[] = 'meta_value LIKE %s';
						$meta_like_args[]    = '%' . $wpdb->esc_like( $variant ) . '%';
					}

					if ( ! empty( $meta_like_clauses ) ) {
						$pixel_match_clauses[] = "{$wpdb->posts}.ID IN (
							SELECT post_id
							FROM {$wpdb->postmeta}
							WHERE meta_key IN ( {$meta_placeholders} )
							AND ( " . implode( ' OR ', $meta_like_clauses ) . " )
						)";
						$pixel_match_args      = array_merge( $pixel_match_args, $meta_keys, $meta_like_args );
					}
				}

				if ( empty( $pixel_match_clauses ) ) {
					$pixel_match_clauses[] = "{$wpdb->posts}.post_title LIKE %s";
					$pixel_match_args[]    = '%' . $wpdb->esc_like( $s ) . '%';
				}

				$pixel_clause_sql = implode( ' OR ', $pixel_match_clauses );
				$primary_pattern  = $title_like_args[0] ?? '%' . $wpdb->esc_like( $s ) . '%';

				$sql = "
					AND (
						(
							{$wpdb->posts}.post_type = %s
							AND {$wpdb->posts}.post_status IN ( {$status_placeholders} )
							AND ( {$pixel_clause_sql} )
						)
						OR (
							{$wpdb->posts}.post_type != %s
							AND (
								{$wpdb->posts}.post_title LIKE %s
								OR {$wpdb->posts}.post_content LIKE %s
							)
						)
					)
				";

				$args = array_merge(
					[ self::$post_type ],
					$searchable_statuses,
					$pixel_match_args,
					[ self::$post_type, $primary_pattern, $primary_pattern ]
				);

			$search = $wpdb->prepare( $sql, $args );

			$search = apply_filters( 'mds_posts_search', $search, $s, $query );
		}

		// Exclude the specific MDS pages from search results

		// Get the array of page IDs to exclude
		$exclude_ids = Utility::get_page_ids();

		// Filter out empty values
		$exclude_ids = array_filter( array_map( 'absint', $exclude_ids ) );

		if ( ! empty( $exclude_ids ) ) {
			$exclude_ids_string = implode( ',', $exclude_ids );
			// Modify the $search query
			$search .= " AND {$wpdb->posts}.ID NOT IN (" . $exclude_ids_string . ")";
		}

		return $search;
	}

	protected static function get_searchable_meta_keys(): array {
		if ( ! empty( self::$searchable_meta_keys ) ) {
			return self::$searchable_meta_keys;
		}

		if ( ! isset( self::$fields ) || empty( self::$fields ) ) {
			self::get_fields();
		}

		$fields    = isset( self::$fields ) ? self::$fields : [];
		$meta_keys = [];

		foreach ( $fields as $field ) {
			if ( ! $field instanceof Field ) {
				continue;
			}

			$base_name = $field->get_base_name();
			if ( ! is_string( $base_name ) || $base_name === '' ) {
				continue;
			}

			$meta_keys[] = '_' . $base_name;
		}

		$default_text_key = '_' . MDS_PREFIX . 'text';
		if ( ! in_array( $default_text_key, $meta_keys, true ) ) {
			$meta_keys[] = $default_text_key;
		}

		$meta_keys = array_values( array_unique( array_filter( $meta_keys, 'is_string' ) ) );

		$meta_keys = apply_filters( 'mds_searchable_pixel_meta_keys', $meta_keys, $fields );

		if ( ! is_array( $meta_keys ) ) {
			$meta_keys = [];
		}

		$meta_keys = array_values( array_unique( array_filter( $meta_keys, 'is_string' ) ) );

		self::$searchable_meta_keys = $meta_keys;

		return self::$searchable_meta_keys;
	}

	protected static function filter_field_value( string $field_name, string $value, string $field_type, int $post_id, string $raw_value ): string {
		$value = apply_filters( 'mds_form_field_sanitize', $value, $field_name, $field_type, $post_id, $raw_value );
		$field_key = self::normalize_field_filter_key( $field_name );
		$value = apply_filters( 'mds_form_field_sanitize_' . $field_key, $value, $field_name, $field_type, $post_id, $raw_value );

		return $value;
	}

	public static function sanitize_rich_text_value( string $value ): string {
		$allowed = self::get_allowed_rich_text_tags();
		$sanitized = wp_kses( $value, $allowed );

		return trim( $sanitized );
	}

	public static function sanitize_plain_text_value( string $value, string $field_name = '' ): string {
		$value = wp_unslash( $value );
		$value = wp_specialchars_decode( $value, ENT_QUOTES );
		$value = wp_strip_all_tags( $value, true );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = trim( $value );

		return (string) apply_filters( 'mds_plain_text_value', $value, $field_name );
	}

	protected static function get_allowed_rich_text_tags(): array {
		$allowed = [
			'p'      => [],
			'br'     => [],
			'strong' => [],
			'em'     => [],
			'b'      => [],
			'i'      => [],
		];

		return apply_filters( 'mds_rich_text_allowed_tags', $allowed );
	}

	protected static function print_rich_text_initializer( string $field_name, array $settings ): void {
		$settings = apply_filters( 'mds_rich_text_editor_settings', $settings, $field_name );
		$settings_json = wp_json_encode( $settings );
		$field_id = esc_js( $field_name );
		?>
		<script>
		(function(){
			function initMdsEditor() {
				if ( typeof wp === 'undefined' || ! wp.editor || ! wp.editor.initialize ) {
					return;
				}
				try {
					if ( wp.editor.get && wp.editor.get('<?php echo $field_id; ?>') ) {
						wp.editor.remove('<?php echo $field_id; ?>');
					}
					wp.editor.initialize('<?php echo $field_id; ?>', <?php echo $settings_json; ?>);
				} catch (e) {
					console.error('MDS editor init failed', e);
				}
			}
			if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
				setTimeout(initMdsEditor, 0);
			} else {
				document.addEventListener('DOMContentLoaded', initMdsEditor);
			}
		})();
		</script>
		<?php
	}

	protected static function normalize_field_filter_key( string $field_name ): string {
		$key = preg_replace( '/^' . preg_quote( MDS_PREFIX, '/' ) . '/', '', $field_name );
		$key = preg_replace( '/[^a-z0-9_]+/i', '_', (string) $key );
		$key = trim( (string) $key, '_' );

		return $key !== '' ? strtolower( $key ) : 'field';
	}

	protected static function normalize_raw_input( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_unslash( $value );
		$value = wp_specialchars_decode( $value, ENT_QUOTES );

		return $value;
	}

	protected static function build_search_variants( string $term ): array {
		$variants   = [];
		$variants[] = $term;

		$decoded = wp_specialchars_decode( $term, ENT_QUOTES );
		if ( $decoded !== $term ) {
			$variants[] = $decoded;
		}

		$base_for_entities = $decoded;

		if ( $base_for_entities !== '' && strpos( $base_for_entities, "'" ) !== false ) {
			$variants[] = str_replace( "'", '&#039;', $base_for_entities );
			$variants[] = str_replace( "'", '&#8217;', $base_for_entities );
			$variants[] = str_replace( "'", '&#x27;', $base_for_entities );
			$variants[] = addslashes( $base_for_entities );
		}

		$variants = array_values( array_unique( array_filter( $variants, 'strlen' ) ) );

		return $variants;
	}

	public static function enqueue_frontend_editor_assets(): void {
		if ( self::$editor_assets_enqueued ) {
			return;
		}
		if ( Options::get_option( 'popup-rich-text', 'no' ) !== 'yes' ) {
			return;
		}
		if ( ! function_exists( 'wp_enqueue_editor' ) ) {
			return;
		}

		self::$editor_assets_enqueued = true;

		wp_enqueue_editor();
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'editor' );
		wp_enqueue_script( 'quicktags' );
		wp_enqueue_style( 'editor-buttons' );
		wp_enqueue_style( 'dashicons' );
	}
}

if ( ! \is_admin() ) {
	\add_action( 'wp_enqueue_scripts', [ FormFields::class, 'enqueue_frontend_editor_assets' ] );
}
