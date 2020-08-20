<?php
/**
 * ElasticPress\Seo functions to serialize WordPress data
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Seo;

use Yoast\WP\SEO\Generators\Schema_Generator;
use Yoast\WP\SEO\Surfaces\Helpers_Surface;

/**
 * Serialize SEO data from Yoast
 *
 * @param String  $id The id for the data.
 * @param String  $type The type of post.
 * @param boolean $top_level Whether the request is top-level (optional).
 * @return Array  $meta
 */
function get_seo_data( $id, $type, $top_level = true ) {
	$obj = ( 'post' === $type ) ? get_post( $id ) : get_term( $id );

	if ( ! is_object( $obj ) ) {
		return array();
	}

	if ( 'post' === $type ) {
		$post      = $obj;
		$title     = $post->post_title;
		$type      = $post->post_type;
		$post_meta = get_post_meta( $id );
		$url       = get_the_permalink( $post );
		if ( ! is_array( $post_meta ) ) {
			$post_meta = array();
		}
		$post_meta = array_filter(
			$post_meta,
			function( $key ) {
				return strpos( $key, 'yoast' );
			},
			ARRAY_FILTER_USE_KEY
		);
	} elseif ( 'term' === $type ) {
		$term      = $obj;
		$title     = $term->name;
		$type      = $term->taxonomy;
		$tax_meta  = get_option( 'wpseo_taxonomy_meta' );
		$post_meta = ( array_key_exists( $type, $tax_meta ) && array_key_exists( $id, $tax_meta[ $type ] ) ) ? $tax_meta[ $type ][ $id ] : array();
		$url       = get_term_link( $term );
	}

	$meta = array();
	foreach ( $post_meta as $key => $value ) {
		$new_key          = str_replace( '_yoast_', '', $key );
		$new_key          = str_replace( 'wpseo_', '', $new_key );
		$new_key          = str_replace( 'metadesc', 'desc', $new_key );
		$new_val          = ( is_array( $value ) ) ? $value[0] : $value;
		$meta[ $new_key ] = $new_val;
	}

	$seo_titles   = get_option( 'wpseo_titles' );
	$meta['vars'] = array(
		'title'      => $title,
		'term_title' => $title,
		'type'       => $type,
		'sep'        => $seo_titles ?? yoast_separator( $seo_titles['separator'] ),
		'sitename'   => get_option( 'blogname' ),
		'url'        => $url,
	);

	if ( $top_level ) {
		$schema       = get_schema_markup( $id, $type );
		$meta['vars'] = array_merge( $meta['vars'], array( 'schema' => $schema ) );
	}

	return $meta;
}

/**
 * Serialize SEO data from Yoast
 *
 * @param String  $id The id for the data.
 * @param String  $type The type of post.
 * @return Array  $output
 */
function get_schema_markup( $id, $type ) {
	global $wp_query, $post;
	$is_term = false;
	$terms = []; // FIXME

	if ( 'page' === $type ) {
		$post = get_page( $id );
	} elseif ( in_array( $type, $terms) ) {
		$is_term = true;
		$post    = get_term( $id );
	} else {
		$post = get_post( $id );
	}

	if ( ! $post || 'publish' !== $post->post_status ) {
		return array();
	}

	// Store previous globals.
	$previous_wp_query = $GLOBALS['wp_query'];
	$previous_post     = $GLOBALS['post'];
	$previous_singular = $GLOBALS['wp_query']->is_singular;

	// Since Yoast schema is usually output in the context of a page and request
	// we have to set some global variables to provide context.
	$GLOBALS['wp_query']->queried_object_id = $id;
	$GLOBALS['wp_query']->queried_object    = $post;

	if ( $is_term ) {
		$GLOBALS['wp_query']->is_tag = true;
	} else {
		$GLOBALS['post']                  = $post;
		$GLOBALS['wp_query']->is_singular = true;
		$wp_query->is_front_page          = get_option( 'page_on_front' ) === $id;
	}

	$sch = new \WPSEO_Schema();
	// Yoast also echos its contents so we have to capture.
	ob_start();
	$sch->generate();
	$output = ob_get_clean();

	// Reset the WPSEO_Frontend instance so you get new data per entry.
	$front = \WPSEO_Frontend::get_instance();
	$front->reset();
	// Reset globals.
	$GLOBALS['wp_query']              = $previous_wp_query;
	$GLOBALS['post']                  = $previous_post;
	$GLOBALS['wp_query']->is_singular = $previous_singular;

	return rtrim( $output );
}
