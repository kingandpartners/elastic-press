<?php
/**
 * ElasticPress\Seo functions to serialize WordPress data
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Seo;

use Yoast\WP\SEO\Actions\Indexables\Indexable_Head_Action;
use Yoast\WP\SEO\Generators\Schema_Generator;
use Yoast\WP\SEO\Surfaces\Helpers_Surface;

/**
 * Serialize SEO data from Yoast
 *
 * @param String $id The id for the data.
 * @param String $type The type of post.
 * @return String $head
 */
function get_yoast_head( $id, $type ) {
	$action = YoastSEO()->classes->get( Indexable_Head_Action::class );
	if ( 'term' === $type ) {
		$context = $action->for_term( $id );
	} else {
		$context = $action->for_post( $id );
	}
	// v15 of Yoast uses "head" and v16+ uses "html"
	// in order to keep both versions working simultaneously we use the
	// Null Coalescing Operator (??).
	$head = $context->head ?? $context->html;
	return $head;
}

/**
 * Get Yoast title
 *
 * @param String $id The id for the data.
 * @param String $type The type of post.
 * @return String $title
 */
function get_yoast_title( $id, $type ) {
	if ( 'term' === $type ) {
		$context = YoastSEO()->meta->for_term( $id );
	} else {
		$context = YoastSEO()->meta->for_post( $id );
	}
	return $context->title;
}

/**
 * Get Yoast description
 *
 * @param String $id The id for the data.
 * @param String $type The type of post.
 * @return String $description
 */
function get_yoast_description( $id, $type ) {
	if ( 'term' === $type ) {
		$context = YoastSEO()->meta->for_term( $id );
	} else {
		$context = YoastSEO()->meta->for_post( $id );
	}
	return $context->description;
}

/**
 * Serialize SEO data from Yoast
 *
 * @param String $id The id for the data.
 * @param String $type The type of post.
 * @return Array  $output
 */
function get_seo_data( $id, $type ) {
	$keys = array( 'name', 'content', 'property' );
	$head = get_yoast_head( $id, $type );
	$doc  = new \DOMDocument();
	$doc->loadHTML( $head );
	$tags        = $doc->getElementsByTagName( 'meta' );
	$scripts     = $doc->getElementsByTagName( 'script' );
	$title       = get_yoast_title( $id, $type );
	$description = get_yoast_description( $id, $type );
	$meta        = array();

	foreach ( $tags as $tag ) {
		$piece = array();
		foreach ( $keys as $key ) {
			if ( $tag->hasAttribute( $key ) ) {
				$attribute     = $tag->getAttribute( $key );
				$piece[ $key ] = $attribute;
			}
		}
		if ( isset( $piece['property'] ) && preg_match( '/title/', $piece['property'] ) ) {
			$piece['content'] = $title;
		}
		if ( isset( $piece['property'] ) && preg_match( '/description/', $piece['property'] ) ) {
			$piece['content'] = $description;
		}

		$piece = apply_filters( 'ep_seo_meta_piece', $piece );
		if ( isset( $piece['name'] ) ) {
			$piece['content'] = mb_convert_encoding($piece['content'], 'ISO-8859-1', 'UTF-8');
			$piece = apply_filters( 'ep_seo_meta_piece_' . $piece['name'], $piece );
		}
		array_push( $meta, $piece );
	}

	$meta = apply_filters( 'ep_seo_meta', $meta );

	$output = array( 'meta' => $meta );

	if ( $title ) {
		$output['title'] = $title;
	}
	if ( $description ) {
		$output['description'] = $description;
	}

	if ( $scripts[0] ) {
		$schema = json_decode($scripts[0]->nodeValue);
		$output['schema'] = json_encode($schema, JSON_UNESCAPED_UNICODE);
	}

	return $output;
}
