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

namespace MillionDollarScript\Classes\Language;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

defined( 'ABSPATH' ) or exit;

class LanguageFunctionVisitor extends NodeVisitorAbstract {
	public array $strings = [];

	public function enterNode( Node $node ): void {
		if ( $node instanceof Node\Expr\StaticCall ) {
			// Check if it's a call to one of the Language functions
			if ( $node->class instanceof Node\Name && $node->class->toString() === 'Language' ) {
				$function = $node->name instanceof Node\Identifier ? $node->name->toString() : '';

				// Handling different Language functions based on their name and expected arguments
				if ( Language::is_lang_function( $function ) ) {
					$this->extract_language_call( $function, $node->args );
				}
			}
		}
	}

	private function extract_language_call( $function, $args ): void {
		// Assuming the first argument is always a string, and the next two (if present) are arrays
		$string = $args[0]->value;
		$array1 = $args[1]->value ?? null;
		$array2 = $args[2]->value ?? null;

		$this->strings[] = [
			'function' => $function,
			'string'   => $string,
			'array1'   => $array1,
			'array2'   => $array2
		];
	}
}