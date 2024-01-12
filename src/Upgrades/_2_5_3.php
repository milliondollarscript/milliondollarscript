<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

namespace MillionDollarScript\Upgrades;

use MillionDollarScript\Classes\Options;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_3 {

	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.5.3', '<' ) ) {
			// Update default popup template value if not changed.
			$popup_template = Options::get_option( '_' . MDS_DB_PREFIX . '_popup-template' );
			if ( $popup_template == '%text%<br/>
<span color="green">%url%</span><br/>
%image%<br/>
' || $popup_template == '%text%<br />
%url%<br />
%image%' ) {
				update_option( '_' . MDS_DB_PREFIX . '_popup-template', '%text%<br/>
<span style="color:green;">%url%</span><br/>
%image%<br/>
' );
			}
		}
	}
}
