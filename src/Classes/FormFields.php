<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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

namespace MillionDollarScript\Classes;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;
use Imagine\Image\Box;

defined( 'ABSPATH' ) or exit;

class FormFields {

	public static string $post_type = 'mds-pixel';
	protected static array $fields;

	public static function register_post_type(): void {
		register_post_type( self::$post_type,
			array(
				'labels'      => array(
					'name'          => __( 'MDS Pixels' ),
					'singular_name' => __( 'MDS Pixel' )
				),
				'public'      => true,
				'has_archive' => false,
				'searchable'  => false,
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
		$statuses = self::get_statuses();

		if ( $post->post_type == self::$post_type ) {
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
		$fields = [
			// Order ID
			Field::make( 'hidden', MDS_PREFIX . 'order', Language::get( 'Order ID' ) ),

			// Grid ID
			Field::make( 'hidden', MDS_PREFIX . 'grid', Language::get( 'Grid ID' ) ),

			// Text
			Field::make( 'text', MDS_PREFIX . 'text', Language::get( 'Popup Text' ) )
			     ->set_default_value( '' )
			     ->set_help_text( Language::get( 'Text to display in the popup that shows when the block is interacted with.' ) ),

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
	 * Display the fields.
	 * Note: Should already be translated here.
	 *
	 * @return void
	 */
	public static function display_fields(): void {
		$post_id = 0;
		if ( isset( $_REQUEST['aid'] ) ) {
			$post = get_post( $_REQUEST['aid'] );
			if ( $post != null ) {
				$post_id = $post->ID;
			}
		} else if ( isset( $_REQUEST['order_id'] ) ) {
			$args  = array(
				'meta_query'     => array(
					array(
						'key'   => MDS_PREFIX . 'order_id',
						'value' => $_REQUEST['order_id']
					)
				),
				'post_type'      => self::$post_type,
				'posts_per_page' => 1
			);
			$posts = get_posts( $args );
			if ( count( $posts ) > 0 ) {
				$post_id = $posts[0];
			}
		}

		if ( empty( $post_id ) ) {
			$post_id = self::get_post_id( 'new' );
		}

		$fields = self::get_fields();
		foreach ( $fields as $field ) {
			if ( $field->get_type() !== 'hidden' ) {
				echo '<label for="' . esc_attr( $field->get_base_name() ) . '">' . esc_html( $field->get_label() ) . '</label>';
			}

			$value = "";

			if ( $field->get_type() === 'text' ) {
				if ( ! empty( $post_id ) ) {
					$value = carbon_get_post_meta( $post_id, $field->get_base_name() );
				}
				echo '<input type="text" id="' . esc_attr( $field->get_base_name() ) . '" name="' . esc_attr( $field->get_base_name() ) . '" value="' . esc_attr( $value ) . '">';
			} else if ( $field->get_type() === 'image' ) {
				if ( ! empty( $post_id ) ) {
					$image_id = carbon_get_post_meta( $post_id, $field->get_base_name() );
				}

				if ( ! empty( $image_id ) ) {
					$image_url = wp_get_attachment_url( $image_id );
					echo '<img src="' . esc_url( $image_url ) . '" alt="">';
				}

				echo '<input type="file" id="' . esc_attr( $field->get_base_name() ) . '" name="' . esc_attr( $field->get_base_name() ) . '">';
			} else if ( $field->get_type() === 'hidden' ) {
				if ( $field->get_base_name() == MDS_PREFIX . 'order' ) {
					echo '<input type="hidden" id="' . esc_attr( $field->get_base_name() ) . '" name="' . esc_attr( $field->get_base_name() ) . '" value="' . esc_attr( get_current_order_id() ) . '">';
				} else if ( $field->get_base_name() == MDS_PREFIX . 'grid' ) {
					global $f2;
					$BID = $f2->bid();
					echo '<input type="hidden" id="' . esc_attr( $field->get_base_name() ) . '" name="' . esc_attr( $field->get_base_name() ) . '" value="' . intval( $BID ) . '">';
				}
			}
		}
	}

	/**
	 * Get the current user's mds-pixel post id in new status if it exists. Otherwise, return false.
	 *
	 * @param $status
	 *
	 * @return bool|\WP_Post
	 */
	public static function get_post_id( $status ): bool|\WP_Post {

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
	 * Add the form fields to the current user's mds-pixel post.
	 *
	 * @return int|\WP_Error
	 */
	public static function add(): int|\WP_Error {
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

			global $wpdb;

			$current_user = wp_get_current_user();

			// Use the text field as the post title or username as backup
			$post_title = sanitize_title( $_POST[ MDS_PREFIX . 'text' ] ) ?? $current_user->user_login;

			// First check if the user has a new mds-pixel post.
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
				$post_id = wp_insert_post( [
					'post_title'  => $post_title,
					'post_status' => 'new',
					'post_type'   => self::$post_type
				] );
			}
			$fields = self::get_fields();

			$errors = [];

			foreach ( $fields as $field ) {
				$field_name = $field->get_base_name();
				$value      = $_POST[ $field_name ] ?? null;

				if ( isset( $_POST[ $field_name ] ) || isset( $_FILES[ $field_name ] ) ) {
					if ( $field->get_type() === 'hidden' ) {
						if ( $field->get_base_name() == MDS_PREFIX . 'order' ) {
							carbon_set_post_meta( $post_id, $field_name, get_current_order_id() );
						} else if ( $field->get_base_name() == MDS_PREFIX . 'grid' ) {
							$grid_id = $wpdb->get_var( $wpdb->prepare( "SELECT banner_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", intval( get_current_order_id() ) ) );
							carbon_set_post_meta( $post_id, $field_name, $grid_id );
						}
					} else if ( $field->get_type() === 'text' ) {
						if ( ! empty( $value ) ) {
							$value = sanitize_text_field( $value );
							carbon_set_post_meta( $post_id, $field_name, $value );
						}
						// if ( empty( $value ) ) {
						// 	$errors[ $field_name ] = Language::get('This field is required.');
						// }
					} else if ( $field->get_type() === 'image' ) {
						// Check if the file was uploaded
						if ( isset( $_FILES[ $field_name ] ) && $_FILES[ $field_name ]['error'] === UPLOAD_ERR_OK ) {
							$file = $_FILES[ $field_name ];

							// You can add additional checks here, such as checking the file type and size

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
								// 	error_log( wp_sprintf(
								// 		Language::get( 'Error copying file. From: %s To: %s' ),
								// 		$upload['file'],
								// 		$dest
								// 	) );
								// }

								carbon_set_post_meta( $post_id, $field_name, $attach_id );
							} else {
								// The upload failed, add an error message
								$errors[ $field_name ] = $upload['error'];
							}
						} else {
							$errors[ $field_name ] = Language::get( 'Please upload an image.' );
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
				return $post_id;
			}
		}

		return new \WP_Error( 'invalid_request', Language::get( 'Invalid request.' ) );
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
					if ( Config::get( 'MDS_RESIZE' ) == 'YES' ) {
						$imagine = "";
						if ( class_exists( 'Imagick' ) ) {
							$imagine = new \Imagine\Imagick\Imagine();
						} else if ( function_exists( 'gd_info' ) ) {
							$imagine = new \Imagine\Gd\Imagine();
						}

						$max_image_size = Options::get_option( 'max-image-size' );

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
					$field->set_value( sanitize_text_field( $field->get_value() ) );
					break;
				case 'url':
					$field->set_value( esc_url_raw( $field->get_value() ) );
					break;
				default:
					break;
			}

			apply_filters( 'mds_form_save', $field, $name );
		}

		return $post_id;
	}

	/**
	 * Runs when the container is saved, so we can get the post id.
	 *
	 * @param $post_id
	 *
	 * @return void
	 */
	public static function save_container( $post_id ): void {
		$attachment_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'image' );

		// Resize if option is enabled
		if ( Config::get( 'MDS_RESIZE' ) == 'YES' ) {
			$imagine = "";
			if ( class_exists( 'Imagick' ) ) {
				$imagine = new \Imagine\Imagick\Imagine();
			} else if ( function_exists( 'gd_info' ) ) {
				$imagine = new \Imagine\Gd\Imagine();
			}

			$max_image_size = Options::get_option( 'max-image-size' );

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
	 * Adds a 'status' column to the given array of columns.
	 *
	 * @param array $columns The array of columns to add the 'status' column to.
	 *
	 * @return array The updated array of columns.
	 */
	public static function add_status_column( $columns ): array {
		$new_columns           = array();
		$new_columns['cb']     = $columns['cb'];
		$new_columns['title']  = $columns['title'];
		$new_columns['status'] = Language::get( 'Status' );
		foreach ( $columns as $key => $title ) {
			if ( $key == 'cb' || $key == 'title' ) {
				continue;
			}
			$new_columns[ $key ] = $title;
		}

		return $new_columns;
	}

	/**
	 * A function to fill the status column.
	 *
	 * @param string $column The name of the column.
	 *
	 * @return void
	 */
	public static function fill_status_column( $column ): void {
		global $post;
		if ( $column == 'status' ) {
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
	public static function sortable_columns( $columns ) {
		$columns['status'] = 'status';

		return $columns;
	}

	/**
	 * Orders the query by post status.
	 *
	 * @param mixed $query The WP_Query instance.
	 *
	 * @return void
	 */
	public static function orderby_status( $query ): void {
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
	 * The valid MDS order statuses are: 'completed', 'cancelled',
	 * 'confirmed', 'new', 'expired', 'deleted', 'renew_wait',
	 * 'renew_paid', 'waiting', and 'reserved'.
	 *
	 * @return array An array of valid MDS order statuses.
	 */
	public static function get_statuses(): array {
		// Valid MDS order statuses: 'pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid'
		// Note: pending is a default WP status, so it isn't included here.
		return array(
			'completed'  => 'Completed',
			'cancelled'  => 'Cancelled',
			'confirmed'  => 'Confirmed',
			'new'        => 'New',
			'expired'    => 'Expired',
			'deleted'    => 'Deleted',
			'renew_wait' => 'Awaiting Renewal',
			'renew_paid' => 'Renewed',
			'waiting'    => 'Waiting',
			'reserved'   => 'Reserved',
		);
	}
}