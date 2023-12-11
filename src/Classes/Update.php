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

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) or exit;

class Update {
	public static function checker(): void {
		$updates = Options::get_option( 'updates' );
		if ( $updates == 'yes' ) {
			$MDSUpdateChecker = PucFactory::buildUpdateChecker(
				'https://gitlab.com/MillionDollarScript/milliondollarscript-two/',
				MDS_BASE_FILE,
				'milliondollarscript-two'
			);
			$MDSUpdateChecker->setBranch('main');

		} else if ( $updates == 'dev' ) {
			$MDSUpdateChecker = PucFactory::buildUpdateChecker(
				'https://gitlab.com/MillionDollarScript/milliondollarscript-two/',
				MDS_BASE_FILE,
				'milliondollarscript-two-dev'
			);
			$MDSUpdateChecker->setBranch('dev');
		}
	}
}