<?php
/**
 * Menu_Item class
 *
 * Represents a single menu item in the dashboard menu system.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Language\Language;

/**
 * Menu_Item class
 *
 * Represents a menu item with support for nested submenus and sections.
 */
class Menu_Item {

	/**
	 * Unique menu item slug
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Menu item title (translatable)
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Menu item URL (if null, not clickable - used for parent items)
	 *
	 * @var string|null
	 */
	public ?string $url;

	/**
	 * Parent menu item slug (null for top-level items)
	 *
	 * @var string|null
	 */
	public ?string $parent;

	/**
	 * Sort position (lower numbers appear first)
	 *
	 * @var int
	 */
	public int $position;

	/**
	 * Required capability to view this menu item
	 *
	 * @var string
	 */
	public string $capability;

	/**
	 * Section identifier for grouping (e.g., 'admin', 'extensions')
	 *
	 * @var string|null
	 */
	public ?string $section;

	/**
	 * Child menu items
	 *
	 * @var Menu_Item[]
	 */
	public array $children = array();

	/**
	 * Link target attribute (e.g., '_blank' for external links)
	 *
	 * @var string|null
	 */
	public ?string $target;

	/**
	 * Dashicon class name for icon display (e.g., 'dashicons-admin-tools')
	 *
	 * @var string|null
	 */
	public ?string $icon;

	/**
	 * Whether to display as icon-only (text hidden, shown on hover)
	 *
	 * @var bool
	 */
	public bool $icon_only;

	/**
	 * Whether this item is a visual separator (non-clickable divider)
	 *
	 * @var bool
	 */
	public bool $is_separator;

	/**
	 * Constructor
	 *
	 * @param array $args Menu item configuration.
	 */
	public function __construct( array $args ) {
		$defaults = array(
			'slug'         => '',
			'title'        => '',
			'url'          => null,
			'parent'       => null,
			'position'     => 10,
			'capability'   => 'manage_options',
			'section'      => null,
			'target'       => null,
			'icon'         => null,
			'icon_only'    => false,
			'is_separator' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$this->slug         = $args['slug'];
		$this->title        = $args['title'];
		$this->url          = $args['url'];
		$this->parent       = $args['parent'];
		$this->position     = $args['position'];
		$this->capability   = $args['capability'];
		$this->section      = $args['section'];
		$this->target       = $args['target'];
		$this->icon         = $args['icon'];
		$this->icon_only    = $args['icon_only'];
		$this->is_separator = $args['is_separator'];
	}

	/**
	 * Add a child menu item
	 *
	 * @param Menu_Item $item Child menu item.
	 */
	public function add_child( Menu_Item $item ): void {
		$this->children[] = $item;

		// Sort children by position.
		usort(
			$this->children,
			function ( $a, $b ) {
				return $a->position <=> $b->position;
			}
		);
	}

	/**
	 * Check if current user can see this menu item
	 *
	 * @return bool True if user has required capability.
	 */
	public function user_can_view(): bool {
		return current_user_can( $this->capability );
	}

	/**
	 * Check if this menu item has children
	 *
	 * @return bool True if has children.
	 */
	public function has_children(): bool {
		// Filter children by capability.
		$visible_children = array_filter(
			$this->children,
			function ( $item ) {
				return $item->user_can_view();
			}
		);

		return count( $visible_children ) > 0;
	}

	/**
	 * Generate HTML for this menu item
	 *
	 * @param int $depth Current nesting depth.
	 * @return string HTML output.
	 */
	public function to_html( int $depth = 0 ): string {
		if ( ! $this->user_can_view() ) {
			return '';
		}

		$html = '';

		// Handle separators.
		if ( $this->is_separator ) {
			return '<li class="mds-menu-separator" role="separator"></li>';
		}

		// Build CSS classes.
		$classes = array();
		if ( $this->icon_only ) {
			$classes[] = 'mds-menu-icon-only';
		}
		$class_attr = ! empty( $classes ) ? sprintf( ' class="%s"', esc_attr( implode( ' ', $classes ) ) ) : '';

		// Only top-level items get the style attribute.
		if ( 0 === $depth && null === $this->parent ) {
			$html .= sprintf( '<li%s style="--milliondollarscript-menu: %d">', $class_attr, absint( $this->position ) );
		} else {
			$html .= sprintf( '<li%s>', $class_attr );
		}

		// Build link content.
		$link_content = '';

		// Add icon if specified.
		if ( null !== $this->icon ) {
			$link_content .= sprintf( '<span class="dashicons %s mds-menu-icon" aria-hidden="true"></span>', esc_attr( $this->icon ) );
		}

		// Add text (always included for accessibility).
		$link_content .= sprintf( '<span class="mds-menu-text">%s</span>', esc_html( Language::get( $this->title ) ) );

		// Generate link.
		if ( null !== $this->url ) {
			$target_attr = $this->target ? sprintf( ' target="%s"', esc_attr( $this->target ) ) : '';
			$title_attr  = $this->icon_only ? sprintf( ' title="%s"', esc_attr( Language::get( $this->title ) ) ) : '';
			$aria_label  = $this->icon_only ? sprintf( ' aria-label="%s"', esc_attr( Language::get( $this->title ) ) ) : '';

			$html .= sprintf(
				'<a href="%s"%s%s%s>%s</a>',
				esc_url( $this->url ),
				$target_attr,
				$title_attr,
				$aria_label,
				$link_content
			);
		} else {
			// Parent item without URL (used for grouping).
			$html .= sprintf( '<a href="#">%s</a>', $link_content );
		}

		// Add children if any.
		if ( $this->has_children() ) {
			$html .= '<ul>';
			foreach ( $this->children as $child ) {
				$html .= $child->to_html( $depth + 1 );
			}
			$html .= '</ul>';
		}

		$html .= '</li>';

		return $html;
	}
}
