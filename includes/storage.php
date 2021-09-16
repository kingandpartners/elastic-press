<?php
/**
 * ElasticPress\Storage functions to store WordPress data into Elasticsearch
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Storage;

use ElasticPress\ElasticSearch;
use function ElasticPress\Serializers\post_data;
use function ElasticPress\Serializers\page_data;
use function ElasticPress\Serializers\term_data;
use function ElasticPress\Acf\acf_data;

/**
 * Serializes and stores a page (post) object into elasticsearch
 *
 * @param WP_Post $page The page (post) object.
 */
function store_page( $page ) {
	ElasticSearch\elasticsearch_store( $page->ID, 'page', page_data( $page ) );
}

/**
 * Serializes and stores a post object into elasticsearch
 *
 * @param WP_Post $post The post object.
 */
function store_post( $post ) {
	$key  = $post->ID;
	$type = $post->post_type;
	ElasticSearch\elasticsearch_store( $key, $type, post_data( $post ) );
}

/**
 * Serializes and stores a menu object into elasticsearch
 *
 * @param WP_Term $menu The menu (term) object.
 */
function store_menu( $menu ) {
	$key                = $menu->slug . '_nav';
	$items              = wp_get_nav_menu_items( $menu->name );
	$data               = term_data( $menu );
	$data['menu_items'] = array_map( 'ElasticPress\Serializers\nav_map', $items );
	$type               = $menu->taxonomy;
	ElasticSearch\elasticsearch_store( $key, $type, $data );
}

/**
 * Serializes and stores a term object into elasticsearch
 *
 * @param WP_Term $term The term object.
 */
function store_term( $term ) {
	$data = term_data( $term );
	ElasticSearch\elasticsearch_store( $term->term_id, $data['taxonomy'], $data );
}

/**
 * Serializes and stores all terms of a taxonomy type
 *
 * @param string $type The type of taxonomy to store.
 */
function store_terms_data( $type ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $type,
			'hide_empty' => false,
		)
	);
	foreach ( $terms as $term ) {
		if ( is_object( $term ) ) {
			store_term( $term );
		}
	}
}

/**
 * Serializes and stores an options page into elasticsearch
 *
 * @param string $id The prefix for values in the options table.
 * @param string $page The specific option page name.
 */
function store_options( $id, $page = null ) {
	$data       = acf_data( $id );
	$clean_data = array();
	foreach ( $data as $key => $value ) {
		$key_array         = explode( '_', $key );
		$id                = array_shift( $key_array );
		$key               = implode( '_', $key_array );
		$new_data          = array( $key => $value );
		$clean_data[ $id ] = isset( $clean_data[ $id ] ) ? array_merge( $clean_data[ $id ], $new_data ) : $new_data;
	}

	foreach ( $clean_data as $key => $value ) {
		$value['ID'] = $key;
		if ( $page && $page !== $key ) {
			continue;
		}
		ElasticSearch\elasticsearch_store( $key, 'options', $value );
	}
}
