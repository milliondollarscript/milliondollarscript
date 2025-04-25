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

use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\Language\Language;

defined('ABSPATH') or exit;

global $f2;

// Get the selected Banner ID (Grid ID)
$BID = $f2->bid();

// Helper function to check if a package has associated orders
if (!function_exists('mds_package_has_orders')) {
	function mds_package_has_orders($package_id)
	{
		global $wpdb;
		$orders_table = MDS_DB_PREFIX . 'orders';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE package_id = %d",
				$package_id
			)
		);
		return $count > 0;
	}
}

?>
<p>Packages define the pricing and duration options available for purchasing pixels on a specific grid.</p>
<?php
global $wpdb;
$banners_table = MDS_DB_PREFIX . 'banners';

// Fetch banners for the dropdown
$banners = $wpdb->get_results("SELECT banner_id, name FROM {$banners_table} ORDER BY name", ARRAY_A);

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo Language::get('Packages'); ?></h1>
	<p class="description"><?php echo Language::get('Packages allow you to group pixels together.'); ?></p>

	<?php
	global $wpdb;
	$banners_table = MDS_DB_PREFIX . 'banners';

	// Fetch banners for the dropdown
	$banners = $wpdb->get_results("SELECT banner_id, name FROM {$banners_table} ORDER BY name", ARRAY_A);

	?>
	<form name="bidselect" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="mds_admin_form_submission" />
		<input type="hidden" name="mds_dest" value="packages" />
		<?php wp_nonce_field('mds-admin'); ?>

		Select grid: <select name="BID" onchange="this.form.submit()">
			<option value="0">-- Select Grid --</option>
			<?php
			if ($banners) {
				foreach ($banners as $row) {

					if (($row['banner_id'] == $BID) && ($BID != 'all')) {
						$sel = 'selected';
					} else {
						$sel = '';
					}
					echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
				}
			}
			?>
		</select>
	</form>

	<?php

	if ($BID != '') {
		$banner_data = load_banner_constants($BID);
	?>
		<hr>

		<b>Grid ID:</b> <?php echo $BID; ?><br>
		<b>Grid Name</b>: <?php echo $banner_data['G_NAME']; ?><br>
		<b>Default Price per 100:</b> <?php echo $banner_data['G_PRICE']; ?><br>

		<!-- Add spacing above the New Package button -->
		<div style="margin-top: 24px;"></div>
		<a href="<?php echo esc_url(admin_url('admin.php?page=mds-packages&new=1&BID=' . $BID)); ?>" class="page-title-action">New Package</a>
		<p><?php echo Language::get('Listing rows that are marked as custom price.'); ?></p>
		<?php

		function validate_input()
		{

			$error = "";
			if (trim($_REQUEST['price']) == '') {
				$error .= "<b>- Price is blank</b><br>";
			} else if (! is_numeric($_REQUEST['price'])) {
				$error .= "<b>- Price must be a number.</b><br>";
			}

			if (trim($_REQUEST['description']) == '') {
				$error .= "<b>- Description is blank</b><br>";
			}

			if (trim($_REQUEST['currency']) == '') {
				$error .= "<b>- Currency is blank</b><br>";
			}

			if (trim($_REQUEST['max_orders']) == '') {
				$error .= "<b>- Max orders is blank</b><br>";
			} else if (! is_numeric($_REQUEST['max_orders'])) {
				$error .= "<b>- Max orders must be a number</b><br>";
			}

			if (trim($_REQUEST['days_expire']) == '') {
				$error .= "<b>- Days to expire is blank</b><br>";
			} else if (! is_numeric($_REQUEST['days_expire'])) {
				$error .= "<b>- Days to expire must be a number.</b><br>";
			}

			return $error;
		}

		if (isset($_REQUEST['mds-action']) && $_REQUEST['mds-action'] == 'delete') {

			global $wpdb;
			$orders_table = MDS_DB_PREFIX . 'orders';
			$packages_table = MDS_DB_PREFIX . 'packages';
			$package_id = isset($_REQUEST['package_id']) ? intval($_REQUEST['package_id']) : 0;
			$really_delete = isset($_REQUEST['really']) && $_REQUEST['really'] === 'yes';

			// Check if package is part of any orders
			$order_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$orders_table} WHERE package_id = %d",
					$package_id
				)
			);

			if ($order_count > 0 && !$really_delete) {
		?>
				<div class="error">
					<p>
						<span style='color:red'>Cannot delete package: This package is a part of <?php echo $order_count; ?> order(s).</span>
						(<a href='<?php echo esc_url(admin_url('admin.php?page=mds-packages&BID=' . $BID . '&package_id=' . $package_id . '&mds-action=delete&really=yes&_wpnonce=' . wp_create_nonce('mds_delete_package_' . $package_id))); ?>'>Click here to delete anyway</a>)
					</p>
				</div>
			<?php
			} else {
				// Verify nonce before deleting
				if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'mds_delete_package_' . $package_id)) {
					$wpdb->delete(
						$packages_table,
						array('package_id' => $package_id),
						array('%d')
					);
					// Redirect to prevent re-deletion on refresh
					wp_safe_redirect(admin_url('admin.php?page=mds-packages&BID=' . intval($BID) . '&package_deleted=1'));
					exit;
				} else if ($order_count > 0 && $really_delete && (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'mds_delete_package_' . $package_id))) {
					// Nonce failed on forced delete
					echo '<div class="error"><p>Security check failed. Please try again.</p></div>';
				}
			}
		}

		function set_to_default($package_id, $banner_id)
		{

			global $wpdb;
			$packages_table = MDS_DB_PREFIX . 'packages';
			$orders_table   = MDS_DB_PREFIX . 'orders';

			// Find the old default package ID for this banner
			$old_default = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT package_id FROM {$packages_table} WHERE is_default = %s AND banner_id = %d",
					'Y',
					intval($banner_id)
				)
			);

			// Set all packages for this banner to not be default
			$updated_N = $wpdb->update(
				$packages_table,
				array('is_default' => 'N'), // Data
				array('banner_id' => intval($banner_id)), // Where
				array('%s'), // Data format
				array('%d')  // Where format
			);

			// Set the selected package as default
			$updated_Y = $wpdb->update(
				$packages_table,
				array('is_default' => 'Y'), // Data
				array('package_id' => intval($package_id), 'banner_id' => intval($banner_id)), // Where
				array('%s'), // Data format
				array('%d', '%d') // Where format
			);

			if (empty($old_default)) {
				// Update previous orders with no package_id to the new default
				// In the 1.7.0 database, all orders must have packages
				$wpdb->update(
					$orders_table,
					array('package_id' => intval($package_id)), // Data
					array('package_id' => 0, 'banner_id' => intval($banner_id)), // Where
					array('%d'), // Data format
					array('%d', '%d') // Where format
				);
			}
		}

		// Handle setting default package securely and with feedback
		if (isset($_REQUEST['mds-action']) && $_REQUEST['mds-action'] == 'default' && isset($_REQUEST['package_id']) && isset($_REQUEST['BID'])) {
			if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'mds_set_default_package_' . $_REQUEST['package_id'])) {
				set_to_default(intval($_REQUEST['package_id']), intval($_REQUEST['BID']));
				// Redirect to avoid resubmission and show success
				$redirect_bid = isset($_REQUEST['BID']) ? intval($_REQUEST['BID']) : 0;
				wp_safe_redirect(admin_url('admin.php?page=mds-packages&BID=' . $redirect_bid . '&default_set=1'));
				exit;
			} else {
				// Handle nonce failure or missing package_id/BID
				echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
			}
		}

		// Check if the form was submitted for saving/updating a package
		if (isset($_REQUEST['submit']) && $_REQUEST['submit'] != '') {

			// Verify the specific nonce for editing this package/grid
			check_admin_referer('mds-package-edit-' . $BID, 'mds_package_nonce');

			$error = validate_input();

			if ($error != '') {

				echo "<p>";
				echo "<span style='color:red;'>Error: cannot save due to the following errors:</span><br>";
				echo "<span style='color:red;'>$error</span>";
				echo "</p>";
			} else {

				// calculate block id..

				$_REQUEST['block_id_from'] = (($_REQUEST['row_from'] ?? 0) - 1) * $banner_data['G_WIDTH'];
				$_REQUEST['block_id_to']   = ((($_REQUEST['row_to'] ?? 0) * $banner_data['G_HEIGHT']) - 1);

				// Always set is_default to 'N' when saving via the form. The 'Set Default' action handles setting 'Y'.
				$is_default_value = 'N';

				global $wpdb;
				$packages_table = MDS_DB_PREFIX . 'packages';

				$data = array(
					'package_id'  => isset($_REQUEST['package_id']) ? intval($_REQUEST['package_id']) : null,
					'banner_id'   => intval($BID),
					'price'       => isset($_REQUEST['price']) ? floatval($_REQUEST['price']) : 0.0,
					'currency'    => isset($_REQUEST['currency']) ? sanitize_text_field($_REQUEST['currency']) : 'USD',
					'days_expire' => isset($_REQUEST['days_expire']) ? intval($_REQUEST['days_expire']) : 0,
					'max_orders'  => isset($_REQUEST['max_orders']) ? intval($_REQUEST['max_orders']) : 0,
					'description' => isset($_REQUEST['description']) ? sanitize_textarea_field($_REQUEST['description']) : '',
					'is_default'  => $is_default_value // Always set to 'N' when saving from the form
				);

				// Prepare formats for $wpdb->prepare
				$formats = array(
					'%d', // package_id
					'%d', // banner_id
					'%f', // price
					'%s', // currency
					'%d', // days_expire
					'%d', // max_orders
					'%s', // description
					'%s'  // is_default
				);

				// If package_id is not set or empty, it's a new package, let database assign ID.
				// $wpdb->replace handles this better, but we are using query for REPLACE INTO syntax.
				// For a true INSERT/UPDATE, we'd check existence first.
				if (empty($data['package_id'])) {
					// New package - Use INSERT
					// Remove package_id from data and formats for INSERT
					unset($data['package_id']);
					// Recalculate formats array without the first element for package_id
					$insert_formats = array_slice($formats, 1);
					$wpdb->insert($packages_table, $data, $insert_formats);
					$sql = null; // INSERT handled by $wpdb->insert
					$id = $wpdb->insert_id;
				} else {
					// Existing package - Use REPLACE
					// $wpdb->replace handles primary key checking internally
					$wpdb->replace($packages_table, $data, $formats);
					$sql = null; // REPLACE handled by $wpdb->replace
					// $wpdb->insert_id might not be reliable after REPLACE depending on context
					// Use the provided package_id for redirection logic if needed
					$id = $data['package_id'];
				}

				// Check if a default package exists *after* the insert/replace
				$default_exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$packages_table} WHERE banner_id = %d AND is_default = 'Y'",
						intval($BID)
					)
				);

				if (isset($_REQUEST['new']) && $_REQUEST['new'] == '1' && !$default_exists && $id > 0) {
					// Set the newly inserted package as default
					set_to_default($id, $BID);

					// Redirect or display success message
					wp_safe_redirect(admin_url('admin.php?page=mds-packages&BID=' . intval($BID) . '&package_saved=1&new_default=' . $id));
					exit;
				} else {
					// Redirect or display success message for regular save
					wp_safe_redirect(admin_url('admin.php?page=mds-packages&BID=' . intval($BID) . '&package_saved=1'));
					exit;
				}
			}
		}

		// Fetch package data if editing
		$package_data = null;
		if (isset($_REQUEST['mds-action']) && $_REQUEST['mds-action'] == 'edit' && isset($_REQUEST['package_id'])) {
			echo "<h4>Edit Package:</h4>";
			global $wpdb;
			$packages_table = MDS_DB_PREFIX . 'packages';
			$package_id_to_edit = intval($_REQUEST['package_id']);
			$package_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$packages_table} WHERE package_id = %d AND banner_id = %d",
					$package_id_to_edit,
					intval($BID)
				),
				ARRAY_A // Return associative array
			);

			// Populate $_REQUEST superglobal for form fields if not already set (e.g., after validation error)
			if ($package_data && (!isset($error) || $error == '')) {
				$_REQUEST['description'] = $_REQUEST['description'] ?? $package_data['description'];
				$_REQUEST['price']       = $_REQUEST['price'] ?? $package_data['price'];
				$_REQUEST['currency']    = $_REQUEST['currency'] ?? $package_data['currency'];
				$_REQUEST['days_expire'] = $_REQUEST['days_expire'] ?? $package_data['days_expire'];
				$_REQUEST['max_orders']  = $_REQUEST['max_orders'] ?? $package_data['max_orders'];
				// is_default is handled separately and not directly editable in the form
			}
		} else if (isset($_REQUEST['new']) && $_REQUEST['new'] == '1') {
			echo "<h4>New Package:</h4>";
			// Clear potential lingering values for new package form
			$_REQUEST['package_id'] = 0;
			$_REQUEST['description'] = '';
			$_REQUEST['price']       = '';
			$_REQUEST['currency']    = $banner_data['CURRENCY'] ??  Currency::get_default_currency();
			$_REQUEST['days_expire'] = '';
			$_REQUEST['max_orders']  = '';
		}

		global $wpdb;
		$packages_table = MDS_DB_PREFIX . 'packages';
		$packages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$packages_table} WHERE banner_id = %d ORDER BY package_id",
				intval($BID)
			),
			ARRAY_A // Return associative array
		);

		if (!empty($packages)) {
			?>
			<!-- Display the packages table using WordPress admin styles -->
			<table class="wp-list-table widefat striped" style="margin-top: 20px;">
				<thead>
					<tr>
						<th scope="col">ID</th>
						<th scope="col">Description</th>
						<th scope="col">Price</th>
						<th scope="col">Currency</th>
						<th scope="col">Days to Expire</th>
						<th scope="col">Max Orders</th>
						<th scope="col">Default</th>
						<th scope="col">Action</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($packages as $row) {
						$is_default = ($row['is_default'] === 'Y');
					?>
						<tr>
							<td><?php echo esc_html($row['package_id']); ?></td>
							<td><?php echo esc_html($row['description']); ?></td>
							<td><?php echo esc_html($row['price']); ?></td>
							<td><?php echo esc_html($row['currency']); ?></td>
							<td><?php echo esc_html($row['days_expire']); ?></td>
							<td><?php echo $row['max_orders'] == 0 ? 'unlimited' : esc_html($row['max_orders']); ?></td>
							<td><?php echo $is_default ? '✔' : '–'; ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds-packages&package_id=' . $row['package_id'] . '&BID=' . $BID . '&mds-action=edit')); ?>">Edit</a>
								<?php $delete_nonce_url = wp_nonce_url(admin_url('admin.php?page=mds-packages&package_id=' . $row['package_id'] . '&BID=' . $BID . '&mds-action=delete'), 'mds_delete_package_' . $row['package_id']); ?>
								<a class="button button-small" href="<?php echo esc_url($delete_nonce_url); ?>" onclick="return confirm('Delete this package? This action cannot be undone.');">Delete</a>
								<?php if (!$is_default) : ?>
									<?php $set_default_url = wp_nonce_url(
										admin_url('admin.php?page=mds-packages&package_id=' . $row['package_id'] . '&BID=' . $BID . '&mds-action=default'),
										'mds_set_default_package_' . $row['package_id']
									); ?>
									<a class="button button-small" href="<?php echo esc_url($set_default_url); ?>">Set Default</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		<?php
		} else if ($BID) { // Only show 'no packages' if a grid is selected
			echo "<p style='margin-top: 20px;'>There are no packages configured for this grid yet.</p>";
		}

		if ((isset($_REQUEST['new']) && $_REQUEST['new'] == '1') || (isset($_REQUEST['mds-action']) && $_REQUEST['mds-action'] == 'edit')) {

		?>
			<!-- Modernized add/edit form for packages -->
			<form action='<?php echo esc_url(admin_url('admin-post.php')); ?>' method="post" class="add-new-form" style="margin-top:24px;">
				<?php wp_nonce_field('mds-admin'); ?>
				<?php wp_nonce_field('mds-package-edit-' . $BID, 'mds_package_nonce'); ?>
				<input type="hidden" name="action" value="mds_admin_form_submission"> <!-- Target the admin-post hook -->
				<input type="hidden" name="page" value="mds-packages">
				<input type="hidden" name="mds_dest" value="packages">
				<input type="hidden" value="<?php echo isset($_REQUEST['package_id']) ? intval($_REQUEST['package_id']) : 0; ?>" name="package_id">
				<input type="hidden" value="<?php echo isset($_REQUEST['new']) ? intval($_REQUEST['new']) : ''; ?>" name="new">
				<input type="hidden" name="mds-action" value="mds-save-package">
				<input type="hidden" value="<?php echo intval($BID); ?>" name="BID">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="description">Name</label></th>
						<td><input class="regular-text" type="text" name="description" id="description" value="<?php echo esc_attr($_REQUEST['description'] ?? ''); ?>" required> <span class="description">Enter a descriptive name for the package. Eg, "$30 for 100 days."</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="price">Price Per Block</label></th>
						<td><input class="small-text" type="text" pattern="[0-9]+([\.,][0-9]+)?" name="price" id="price" value="<?php echo esc_attr($_REQUEST['price'] ?? ''); ?>" required> <span class="description">Price per block (<?php echo (isset($banner_data['BLK_WIDTH']) ? ($banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT']) : 'N/A'); ?> pixels). Enter a decimal number.</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="currency">Currency</label></th>
						<td><select name="currency" id="currency"><?php Currency::currency_option_list($_REQUEST['currency'] ?? ''); ?></select> <span class="description">The price's currency</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="days_expire">Days to expire</label></th>
						<td><input class="small-text" type="number" min="0" step="1" name="days_expire" id="days_expire" value="<?php echo esc_attr($_REQUEST['days_expire'] ?? '0'); ?>" required> <span class="description">How many days? (Enter 0 to use the grid's default expiry, if set)</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="max_orders">Maximum orders</label></th>
						<td><input class="small-text" type="number" min="0" step="1" name="max_orders" id="max_orders" value="<?php echo esc_attr($_REQUEST['max_orders'] ?? '0'); ?>" required> <span class="description">How many times can this package be ordered? (Enter 0 for unlimited)</span></td>
					</tr>
				</table>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo (isset($_REQUEST['mds-action']) && $_REQUEST['mds-action'] == 'edit') ? 'Update Package' : 'Add Package'; ?>"></p>
			</form>

		<?php
		}
	}

	if (isset($_REQUEST['package_saved']) && $_REQUEST['package_saved'] == '1') { ?>
		<div id="message" class="updated notice notice-success is-dismissible">
			<p>
				<?php
				if (isset($_REQUEST['new_default'])) {
					echo 'Package saved successfully and set as default (ID: ' . intval($_REQUEST['new_default']) . ').';
				} else {
					echo 'Package saved successfully.';
				}
				?>
			</p>
		</div>
	<?php } ?>

	<?php if (isset($_REQUEST['package_deleted']) && $_REQUEST['package_deleted'] == '1') { ?>
		<div id="message" class="updated notice notice-success is-dismissible">
			<p>Package deleted successfully.</p>
		</div>
	<?php } ?>

	<?php if (isset($_REQUEST['default_set']) && $_REQUEST['default_set'] == '1') { ?>
		<div id="message" class="updated notice notice-success is-dismissible">
			<p>Package set as default successfully.</p>
		</div>
	<?php } ?>

	<?php if (isset($package_save_error) && !empty($package_save_error)) { ?>
		<div id="message" class="error notice notice-error is-dismissible">
			<p><strong>Error saving package:</strong><br><?php echo $package_save_error; ?></p>
		</div>
	<?php } ?>

</div> <!-- .wrap -->