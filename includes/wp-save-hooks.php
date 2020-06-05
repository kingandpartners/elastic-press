<?php
/**
 * ElasticPress\WpSaveHooks WordPress action hooks and functions to serialize
 * and store data into Elasticsearch upon save
 *
 * @package Elastic_Press
 */

namespace ElasticPress\WpSaveHooks;

use ElasticPress\Storage;
use ElasticPress\ElasticSearch;

add_action( 'wp_insert_post', __NAMESPACE__ . '\wp_insert_post', 10, 3 );
add_action( 'acf/save_post', __NAMESPACE__ . '\acf_save_post', 10, 3 );
add_action( 'edited_term', __NAMESPACE__ . '\edited_term', 10, 3 );
add_action( 'wp_update_nav_menu', __NAMESPACE__ . '\wp_update_nav_menu', 10, 1 );

/**
 * Handles the 'wp_insert_post' action from WordPress and stores serialized Post
 * into Elasticsearch
 *
 * @param int     $id The id of the object.
 * @param Array   $obj The object.
 * @param boolean $update Whether the post updated or not.
 */
function wp_insert_post( $id, $obj, $update ) {
	// Skip ACF internals.
	if ( ! $update || strpos( $obj->post_type, 'acf-' ) === 0 ) {
		return;
	}

	switch ( $obj->post_type ) {
		case 'page':
			Storage\store_page( $obj );
			break;
		case 'revision':
			Storage\store_revision( $obj );
			break;
		case 'nav_menu_item':
			// Do nothing since this is fired for every item in a menu and we store
			// the menu atomically, see 'wp_update_nav_menu' hook below.
			break;
		default:
			Storage\store_post( $obj );
	}
}

/**
 * Handles the 'acf/save_post' action from WordPress and stores serialized
 * options into Elasticsearch
 *
 * @param int $id The id of the object.
 */
function acf_save_post( $id = null ) {
	// Saving terms triggers this, however we only want to save after "edited".
	if ( is_numeric( $id ) ) {
		return; // Do not store IDs triggered on CPT save.
	}
	if ( strpos( $id, 'term_' ) === 0 ) {
		return;
	}
	Storage\store_options( $id );
}

/**
 * Handles the 'edited_term' action from WordPress and stores serialized
 * non-nav_menu taxonomy / term data into Elasticsearch
 *
 * @param int $term_id  The term id.
 * @param int $tt_id    The taxononmy id.
 * @param int $taxonomy The taxonomy.
 */
function edited_term( $term_id, $tt_id, $taxonomy ) {
	if ( 'nav_menu' === $taxonomy ) {
		return;
	}
	$term = get_term( $term_id );
	Storage\store_term( $term );
}

/**
 * Handles the 'wp_update_nav_menu' action from WordPress and stores serialized
 * nav menu into Elasticsearch
 *
 * @param int $id The id of the nav object.
 */
function wp_update_nav_menu( $id ) {
	$menu = wp_get_nav_menu_object( $id );
	Storage\store_menu( $menu );
}
