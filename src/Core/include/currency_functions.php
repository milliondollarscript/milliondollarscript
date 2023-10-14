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

defined( 'ABSPATH' ) or exit;

function currency_option_list( $selected ) {
	// Support for WooCommerce Payments Multi-currency
	if ( class_exists( 'WC_Payments_Utils' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
		$currencies = \WC_Payments_Multi_Currency()->get_enabled_currencies();
		$default    = get_default_currency();

		foreach ( $currencies as $currency ) {
			$code = $currency->get_code();
			if ( $code === $selected ) {
				$sel = " selected ";
			} else {
				$sel = "";
			}
			echo "<option $sel value=" . $code . ">" . $code . " " . $currency->get_symbol() . "</option>";
		}

		return;
	}

	?>
    <option value="USD">USD $</option>";
	<?php
}

function get_default_currency() {
	// Support for WooCommerce Payments Multi-currency
	if ( class_exists( 'WC_Payments_Utils' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
		$wc_currency = \WC_Payments_Multi_Currency()->get_selected_currency();

		return $wc_currency->get_code();
	}

	return 'USD';
}

// return as a float
function convert_to_default_currency( $cur_code, $amount ) {
	// Support for WooCommerce Payments Multi-currency
	if ( class_exists( 'WC_Payments_Features' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
		// /wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency

		return \WC_Payments_Multi_Currency()->get_price( $amount, 'product' );
	}

	return $amount;
}

function convert_to_default_currency_formatted( $cur_code, $amount ) {
	// Support for WooCommerce Payments Multi-currency
	if ( class_exists( 'WC_Payments_Utils' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
		return \WC_Payments_Utils::format_currency( $amount, $cur_code );
	}

	$show_code = "";
	if ( func_num_args() > 2 ) {
		$show_code = func_get_arg( 2 );
	}

	return format_currency( $amount, $cur_code, $show_code, true );
}

function format_currency( $amount, $cur_code ) {
	// Support for WooCommerce Payments Multi-currency
	if ( class_exists( 'WC_Payments_Utils' ) && WC_Payments_Features::is_customer_multi_currency_enabled() ) {
		return \WC_Payments_Utils::format_currency( $amount, $cur_code );
	}

	$show_code = "";
	if ( func_num_args() > 2 ) {
		$show_code = func_get_arg( 2 );
	}

	if ( ! empty( $show_code ) ) {
		$show_code = " " . $cur_code;
	}
	$amount = number_format( $amount, 2, '.', ',' );
	$amount = "$" . $amount . $show_code;

	return $amount;
}
