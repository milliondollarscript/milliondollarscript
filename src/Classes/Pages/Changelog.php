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

namespace MillionDollarScript\Classes\Pages;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use MillionDollarScript\Classes\Language\Language;

class Changelog {

	public static function register(): void {
		$changelogContent = self::getChangelogContent();

		$container = Container::make( 'theme_options', MDS_PREFIX . 'changelog', Language::get( 'Million Dollar Script Changelog' ) )
		                      ->set_page_parent( 'milliondollarscript' )
		                      ->set_page_file( MDS_PREFIX . 'changelog' )
		                      ->set_page_menu_title( 'Changelog' );

		$container->add_fields( [
			Field::make( 'html', MDS_PREFIX . 'changelog_content', Language::get( 'Changelog' ) )
			     ->set_html( '<div>' . $changelogContent . '</div>' )
			     ->set_help_text( Language::get( 'This displays the changelog for the current and new versions based on the selected update branch. Note that if you wish to change the update branch you can do so on the <a href="' . admin_url( 'admin.php?page=milliondollarscript_options' ) . '">Million Dollar Script Options</a> page on the System tab.' ) ),
		] );
	}

	private static function getChangelogContent(): string {
		global $MDSUpdateChecker;

		if ( ! $MDSUpdateChecker ) {
			return '';
		}

		$currentVersion   = $MDSUpdateChecker->getInstalledVersion();
		$update           = $MDSUpdateChecker->getUpdate();
		$changelogContent = '<h2>Current Version: ' . esc_html( $currentVersion ) . '</h2>';
		$changelogContent .= '<h3>Changelog:</h3>';
		$changelogContent .= '<ul>';

		// Fetch current version info
		$currentVersionInfo = $MDSUpdateChecker->requestInfo();
		if ( $currentVersionInfo && isset( $currentVersionInfo->sections['changelog'] ) ) {
			$changelogContent .= '<li><strong>Version ' . esc_html( $currentVersion ) . ':</strong> ' . Language::get( $currentVersionInfo->sections['changelog'] ) . '</li>';
		} else {
			$changelogContent .= '<li>No changelog available for the current version.</li>';
		}

		// Fetch update changelog
		if ( $update && isset( $update->changelog ) ) {
			$changelog = $update->changelog;

			// Assuming the changelog is structured similarly to the current version info
			foreach ( $changelog as $version => $changes ) {
				if ( version_compare( $version, $currentVersion, '>' ) ) {
					$changelogContent .= '<li><strong>Version ' . esc_html( $version ) . ':</strong> ' . esc_html( $changes ) . '</li>';
				}
			}
		}

		$changelogContent .= '</ul>';

		return $changelogContent;
	}
}