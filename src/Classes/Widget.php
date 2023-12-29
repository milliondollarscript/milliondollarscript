<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

use Carbon_Fields\Widget as CFWidget;

defined( 'ABSPATH' ) or exit;

class Widget extends CFWidget {
	private $fields;
	// protected $form_options = array(
	// 	'width' => 500
	// );

	function __construct() {
		$this->fields = new BlockFields();
		$this->setup( MDS_PREFIX . 'widget', 'Test Widget', 'This is a widget for Million Dollar script. It works in the theme customizer.', $this->fields->get_fields() );

		// Disable the default widget wrappers.
		$this->print_wrappers = false;
	}

	function front_end( $args, $instance ): void {
		$this->fields->display( $args );
	}
}