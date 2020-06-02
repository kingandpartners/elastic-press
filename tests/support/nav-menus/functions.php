<?php
/**
 * Support\NavMenus helper functions for creating nav manus
 *
 * @package Elastic_Press
 */

namespace Support\NavMenus;

/**
 * Helper function to create a menu with one nav menu item
 *
 * @param WP_Factory $factory The factory object from the test.
 */
function create_nav_menu( $factory ) {
	// Create nav menu.
	$nav_menu = $factory->term->create_and_get(
		array(
			'name'     => 'Test Menu',
			'taxonomy' => 'nav_menu',
			'slug'     => 'test-menu',
		)
	);
	// Create nav menu item.
	$menu_item = $factory->post->create_and_get(
		array(
			'post_title' => 'Instagram',
			'post_type'  => 'nav_menu_item',
		)
	);
	// Add menu item to nav menu.
	wp_set_object_terms( $menu_item->ID, array( $nav_menu->term_id ), 'nav_menu' );
	// Update postmeta for menu item (link details).
	update_post_meta( $menu_item->ID, '_menu_item_type', 'custom' );
	update_post_meta( $menu_item->ID, '_menu_item_menu_item_parent', 0 );
	update_post_meta( $menu_item->ID, '_menu_item_object_id', $menu_item->ID );
	update_post_meta( $menu_item->ID, '_menu_item_object', 'custom' );
	update_post_meta( $menu_item->ID, '_menu_item_target', '' );
	update_post_meta( $menu_item->ID, '_menu_item_url', 'http://test.com' );
	return $nav_menu;
}
