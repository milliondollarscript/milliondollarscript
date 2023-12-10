<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.7
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

defined( 'ABSPATH' ) or exit;

class Steps {

	// Define constants for the step names
	const STEP_UPLOAD = 'upload';
	const STEP_ORDER = 'order';
	const STEP_ORDER_PIXELS = 'order-pixels';
	const STEP_WRITE_AD = 'write-ad';
	const STEP_CONFIRM_ORDER = 'confirm_order';
	const STEP_PAYMENT = 'payment';
	const STEP_COMPLETE = 'complete';

	/**
	 * Get the steps in the order process.
	 *
	 * @return array The steps in the order process.
	 */
	public static function get_steps( $numeric_keys = true ): array {
		$USE_AJAX = Config::get( 'USE_AJAX' );

		$steps = [
			1 => $USE_AJAX == 'SIMPLE' ? self::STEP_UPLOAD : self::STEP_ORDER_PIXELS,
			2 => $USE_AJAX == 'SIMPLE' ? self::STEP_ORDER : self::STEP_UPLOAD,
			3 => self::STEP_WRITE_AD,
			4 => self::STEP_CONFIRM_ORDER,
			5 => self::STEP_PAYMENT,
			6 => self::STEP_COMPLETE
		];

		if ( ! $numeric_keys ) {
			$steps = array_flip( $steps );
		}

//		// Remove confirm step if confirm-orders is set to no
//		if ( Options::get_option( 'confirm-orders' ) == 'no' ) {
//			unset( $steps[4] );
//
//			// Collapse array indexes
//			$steps = array_values( $steps );
//		}

		return apply_filters( 'mds_order_steps', $steps );
	}

	/**
	 * Update the current step in the order process.
	 *
	 * @param string $mds_dest The destination of the next step.
	 *
	 * @return void
	 */
	public static function update_step( string $mds_dest ): void {
		// Get the steps with non-numeric keys.
		$steps = Steps::get_steps( false );
		if ( ! array_key_exists( $mds_dest, $steps ) ) {
			// If the key does not exist in the steps array, return early.
			return;
		}

		$order_id = Orders::get_current_order_in_progress();
		if ( ! empty( $order_id ) ) {
			// Get the numeric step value for the given step.
			$step = $steps[ $mds_dest ];
			Orders::set_current_step( $order_id, $step );
		}
	}
}