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
	// Null Coalescing Operator (??)
	$head = $context->head ?? $context->html;
	return $head;
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
	$tags    = $doc->getElementsByTagName( 'meta' );
	$scripts = $doc->getElementsByTagName( 'script' );
	$title   = null;
	$meta    = array();

	foreach ( $tags as $tag ) {
		$piece = array();
		foreach ( $keys as $key ) {
			if ( $tag->hasAttribute( $key ) ) {
				$attribute = $tag->getAttribute( $key );
				if ( 'property' === $key && 'og:title' === $attribute ) {
					$title = $tag->getAttribute( 'content' );
				}
				$piece[ $key ] = $attribute;
			}
		}
		array_push( $meta, $piece );
	}

	$output = array( 'meta' => $meta );

	if ( $title ) {
		$output['title'] = $title;
	}
	if ( $scripts[0] ) {
		$output['schema'] = $scripts[0]->nodeValue;
	}

	return $output;
}
