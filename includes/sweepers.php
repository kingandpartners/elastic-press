<?php
/**
 * ElasticPress\Sweepers functions to sweep all WordPress data into Elasticsearch
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Sweepers;

use ElasticPress\Storage;
use ElasticPress\ElasticSearch;

/**
 * Loops through all content and stores it into Elasticsearch - also clears indexes
 */
function warm_site_cache() {
	ElasticSearch\Client::prune_stale_aliases();
	ElasticSearch\Client::update_write_aliases();

	Storage\store_options( 'options' );
	sweep_menu_cache();
	sweep_posts();
	sweep_pages();
	sweep_taxonomy();

	do_action( 'ep_warm_site_cache' );

	ElasticSearch\Client::update_read_aliases();
}

/**
 * Loops through all posts of a type and stores in Elasticsearch
 *
 * @param string $post_type The post object.
 * @param Array  $options The post object.
 */
function sweep_post_type( $post_type, $options = null ) {
	$query = array(
		'post_type'      => $post_type,
		'posts_per_page' => -1,
	);
	$query = apply_filters( "sweep_post_type", $query );
	$query = apply_filters( "sweep_post_type_$post_type", $query );
	if ( $options ) {
		$query = array_merge( $query, $options );
	}
	$posts = get_posts( $query );
	foreach ( $posts as $post ) {
		Storage\store_post( $post );
	}
}

/**
 * Loops through all post types and stores each post into Elasticsearch
 */
function sweep_posts() {
	$post_types = array_keys(
		get_post_types(
			array(
				'_builtin' => false,
			)
		)
	);
	array_unshift( $post_types, 'post' );

	$ignore_post_types = array(
		'acf-field-group',
		'acf-field',
	);
	foreach ( $ignore_post_types as $post_type ) {
		$key = array_search( $post_type, $post_types );
		if ( false !== $key ) {
			unset( $post_types[ $key ] );
		}
	}

	foreach ( $post_types as $post_type ) {
		sweep_post_type( $post_type );
	}
}

/**
 * Loops through all pages and stores each into Elasticsearch
 */
function sweep_pages() {
	foreach ( get_pages() as $page ) {
		Storage\store_page( $page );
	}
}

/**
 * Loops through all menus and stores each into Elasticsearch
 */
function sweep_menu_cache() {
	$menus = get_terms( 'nav_menu', array( 'hide_empty' => false ) );
	foreach ( $menus as $menu ) {
		Storage\store_menu( $menu );
	}
}

/**
 * Loops through all taxonomies and stores each into Elasticsearch
 */
function sweep_taxonomy() {
	$taxonomies = get_taxonomies(
		array(
			'_builtin' => false,
		)
	);
	array_unshift( $taxonomies, 'category' );
	foreach ( $taxonomies as $taxonomy ) {
		Storage\store_terms_data( $taxonomy );
	}
}
