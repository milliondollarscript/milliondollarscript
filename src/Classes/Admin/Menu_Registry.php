<?php
/**
 * Menu_Registry class
 *
 * Central registry for dashboard menu items.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Classes\Admin;

/**
 * Menu_Registry class
 *
 * Manages registration and rendering of dashboard menu items.
 */
class Menu_Registry {

	/**
	 * Registered menu items indexed by slug
	 *
	 * @var Menu_Item[]
	 */
	private static array $items = array();

	/**
	 * Section dividers
	 *
	 * @var array
	 */
	private static array $sections = array();

	/**
	 * Whether initialization has occurred
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Register a menu item
	 *
	 * @param array $args Menu item configuration.
	 * @return Menu_Item The created menu item.
	 */
	public static function register( array $args ): Menu_Item {
		$item = new Menu_Item( $args );

		if ( isset( self::$items[ $item->slug ] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				sprintf( 'Menu item with slug "%s" is already registered.', esc_html( $item->slug ) ),
				E_USER_WARNING
			);
			return self::$items[ $item->slug ];
		}

		self::$items[ $item->slug ] = $item;

		return $item;
	}

	/**
	 * Register a section divider
	 *
	 * @param string $section Section identifier.
	 * @param string $title   Section title.
	 * @param int    $position Position for the section.
	 */
	public static function register_section( string $section, string $title, int $position ): void {
		self::$sections[ $section ] = array(
			'title'    => $title,
			'position' => $position,
		);
	}

	/**
	 * Get a menu item by slug
	 *
	 * @param string $slug Menu item slug.
	 * @return Menu_Item|null Menu item or null if not found.
	 */
	public static function get( string $slug ): ?Menu_Item {
		return self::$items[ $slug ] ?? null;
	}

	/**
	 * Build parent-child relationships
	 *
	 * Must be called after all items are registered.
	 */
	private static function build_hierarchy(): void {
		foreach ( self::$items as $item ) {
			if ( null !== $item->parent ) {
				$parent = self::get( $item->parent );
				if ( $parent ) {
					$parent->add_child( $item );
				}
			}
		}
	}

	/**
	 * Get all top-level menu items (items without a parent)
	 *
	 * @return Menu_Item[] Top-level menu items sorted by position.
	 */
	private static function get_top_level_items(): array {
		$top_level = array_filter(
			self::$items,
			function ( $item ) {
				return null === $item->parent;
			}
		);

		// Sort by position.
		usort(
			$top_level,
			function ( $a, $b ) {
				return $a->position <=> $b->position;
			}
		);

		return $top_level;
	}

	/**
	 * Initialize the menu registry
	 *
	 * Fires the hook for extensions and core to register menu items.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		/**
		 * Hook for registering dashboard menu items
		 *
		 * Extensions and core features should hook into this to register
		 * their menu items using Menu_Registry::register().
		 *
		 * @param Menu_Registry $registry The menu registry instance.
		 */
		do_action( 'mds_register_dashboard_menu', __CLASS__ );

		self::build_hierarchy();
		self::$initialized = true;
	}

	/**
	 * Render the dashboard menu HTML
	 *
	 * @return string Dashboard menu HTML.
	 */
	public static function render(): string {
		if ( ! self::$initialized ) {
			self::init();
		}

		$html = '<ul class="milliondollarscript-menu">';

		$top_level = self::get_top_level_items();

		foreach ( $top_level as $item ) {
			if ( ! $item->user_can_view() ) {
				continue;
			}

			$html .= $item->to_html();
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Reset the registry (used for testing)
	 */
	public static function reset(): void {
		self::$items       = array();
		self::$sections    = array();
		self::$initialized = false;
	}
}
