<?php
/**
 * ElasticPress\Serializers functions to serialize WordPress data
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Serializers;

use function ElasticPress\Acf\parse_acf_field;
use function ElasticPress\Acf\acf_data;
use ElasticPress\Utils\ArrayHelpers;
use function ElasticPress\Acf\get_acf_image;
use ElasticPress\Utils\InlineSVG;
use function ElasticPress\Seo\get_seo_data;

/**
 * Gathers all page data into easy to access array
 *
 * @param WP_Post $page The page object.
 * @return Array
 */
function page_data( $page ) {
	$data                  = (array) $page;
	$data['url']           = get_the_permalink( $page );
	$data['page_template'] = get_post_meta( $page->ID, '_wp_page_template', true );
	$data['blocks']        = parse_gut_blocks( $data['post_content'] );
	$data['seo']           = get_seo_data( $page->ID, 'post' );
	$data                  = array_merge( acf_data( $page->ID ), $data );

	if ( has_post_thumbnail( $page ) ) {
		$data['featured_image']['type']  = 'image';
		$data['featured_image']['image'] = get_featured_image_obj( $page );
	}

	return apply_filters( 'ep_page_data', $data, $page );
}


/**
 * Gathers all post data into easy to access array
 *
 * @param WP_Post $post The post object.
 * @return Array
 */
function post_data( $post ) {
	if ( ! is_object( $post ) ) {
		return array();
	}
	$data                  = (array) $post;
	$taxonomies            = get_taxonomies( '', 'names' );
	$terms                 = wp_get_object_terms( $post->ID, $taxonomies );
	$template              = $post->post_type;
	$data['url']           = get_the_permalink( $post );
	$data['page_template'] = "template-$template-single";
	$data['blocks']        = parse_gut_blocks( $data['post_content'] );
	$data['taxonomies']    = array_map( 'ElasticPress\Serializers\term_data', $terms );
	$data['comments_open'] = comments_open( $post->ID );
	$data['edit_lock']     = get_post_meta( $post->ID, '_edit_lock', true );
	$data['seo']           = get_seo_data( $post->ID, 'post' );
	$data                  = array_merge( acf_data( $post->ID ), $data );

	if ( has_excerpt( $post->ID ) ) {
		$data['excerpt'] = $post->post_excerpt;
	}
	if ( has_post_thumbnail( $post ) && get_featured_image_obj( $post ) ) {
		$data['featured_image']['type']  = 'image';
		$data['featured_image']['image'] = get_featured_image_obj( $post );
	}

	return apply_filters( 'ep_post_data', $data, $post );
}

/**
 * Assimilates term data into post structure for easy searching.
 *
 * @param WP_Term $term The term object.
 * @return Array
 */
function term_data( $term ) {
	if ( ! is_object( $term ) ) {
		return array();
	}
	$asset               = get_field( 'asset', $term );
	$data                = (array) $term;
	$data['url']         = get_term_link( $term );
	$data['post_name']   = $data['slug'];
	$data['post_status'] = 'publish';
	$data['seo']         = get_seo_data( $term->term_id, 'term' );
	$data                = array_merge( acf_data( 'term_' . $term->term_id ), $data );
	return apply_filters( 'ep_term_data', $data, $term );
}

/**
 * Parses Gutenberg blocks from post_content.
 *
 * @param string $content The post_content from the post object.
 * @return Array
 */
function parse_gut_blocks( $content ) {
	$blocks = parse_blocks( $content );
	$blocks = array_values(
		array_filter(
			$blocks,
			function( $block ) {
				return ( null !== $block['blockName'] );
			}
		)
	);
	return array_map( 'ElasticPress\Serializers\parse_block_fields', $blocks );
}

/**
 * Parses Gutenberg block fields from a parsed block.
 *
 * @param WP_Block_Parser_Block $block A parsed block object.
 * @return Array
 */
function parse_block_fields( $block ) {
	$block = (array) $block;

	$block = parse_core_block( $block );

	if ( ! isset( $block['attrs']['name'] ) ) {
		return $block;
	}

	$type   = str_replace( 'acf/', '', $block['attrs']['name'] );
	$type   = str_replace( '-', '_', $type );
	$fields = acf_get_block_fields( $block['attrs'] );
	$data   = isset( $block['attrs']['data'] ) ? $block['attrs']['data'] : array();

	foreach ( $fields as $field ) {
		$name = $field['name'];
		if ( ! isset( $data[ $name ] ) && ! isset( $data[ "field_$type\_$name" ] ) ) {
			continue;
		}
		$value = isset( $data[ $name ] ) ? $data[ $name ] : $data[ "field_$type\_$name" ];
		if ( isset( $value['row-0'] ) ) {
			// Something wonky happening for ACF repeater fields data.
			$arr = array();
			foreach ( array_values( $value ) as $item ) {
				$hash = array();
				foreach ( $item as $k => $v ) {
					$replaced_key          = str_replace( "field_$type\_$name\_", '', $k );
					$hash[ $replaced_key ] = parse_acf_field( array( 'type' => $replaced_key ), intval( $v ) );
				}
				$arr[] = $hash;
			}

			$value = $arr;
		} else {
			$value = parse_acf_field( $field, $value, $data );
		}
		if ( ! isset( $block['attrs']['parsed'] ) ) {
			$block['attrs']['parsed'] = array();
		}
		$block['attrs']['parsed'][ $name ] = $value;
	}

	// This removes unparsed data that causes issues for ElasticSearch indexing.
	if ( isset( $block['attrs']['parsed'] ) ) {
		$block['attrs']['data'] = $block['attrs']['parsed'];
		unset( $block['attrs']['parsed'] );
	}

	// The id needs to be an int otherwise it will sometimes contain letters and
	// elasticsearch doesn't like id's to contain letters, i.e. block_12323423212.
	$block['attrs']['id'] = intval( $block['attrs']['id'] );
	return $block;
}

/**
 * Adds attrs > data to Gutenberg core blocks
 *
 * @param Array $block A Gutenberg block array.
 * @return Array
 */
function parse_core_block( $block ) {
	switch ( $block['blockName'] ) {
		case 'core/image':
			if ( ! isset( $block['attrs']['id'] ) ) {
				return;
			}
			$data = get_acf_image( $block['attrs']['id'] );
			$orig = $block['innerHTML'];
			preg_match( '/alt=("[^"]*")/i', $orig, $alt_matches );
			if ( count( $alt_matches ) > 1 ) {
				$alt         = str_replace( '"', '', $alt_matches[1] );
				$data['alt'] = $alt;
			}
			if ( count( $alt_matches ) > 1 ) {
				$alt         = str_replace( '"', '', $alt_matches[1] );
				$data['alt'] = $alt;
			}
			$block['attrs']['data'] = $data;
			break;
	}
	if ( is_array( $block ) ) {
		$block = ArrayHelpers::convert_false_to_null( $block );
	}
	return $block;
}

/**
 * Gets normalized image array for a given post's featured image.
 *
 * @param WP_Post $post The post object.
 * @return array
 */
function get_featured_image_obj( $post ) {
	$image        = array( 'sizes' => array() );
	$thumbnail_id = get_post_thumbnail_id( $post->ID );
	return get_acf_image( $thumbnail_id );
}

/**
 * Get full image array of details for given attachment ID.
 *
 * @param int $thumbnail_id The ID for a given attachment.
 * @return Array
 */
function get_image_array( $thumbnail_id ) {
	$attachment = get_post( $thumbnail_id );
	if ( ! is_object( $attachment ) ) {
		return null;
	}
	$file              = get_attached_file( $thumbnail_id );
	$full_image        = wp_get_attachment_image_src( $thumbnail_id, 'full' );
	$image             = (array) $attachment;
	$image['id']       = $thumbnail_id;
	$image['url']      = $full_image[0];
	$image['width']    = $full_image[1];
	$image['height']   = $full_image[2];
	$image['filename'] = basename( $file );
	$image['filesize'] = image_filesize( $file );
	$image['alt']      = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
	$image['srcset']   = wp_get_attachment_image_srcset( $thumbnail_id ) ?? '';
	foreach ( get_intermediate_image_sizes() as $size ) {
		$image['sizes'][ $size ] = wp_get_attachment_image_src( $thumbnail_id, $size )[0];
	}
	if ( isset( $image['post_mime_type'] ) && 'image/svg+xml' === $image['post_mime_type'] ) {
		$image['raw'] = InlineSVG::remote( $image['url'] );
	}

	return apply_filters( 'ep_get_image_array', $image, $attachment );
}

/**
 * Get image filesize whether image is remote, local, or doesn't exist.
 *
 * @param string $file The path or url to a file.
 * @return int
 */
function image_filesize( $file ) {
	if ( false !== strpos( $file, 'http' ) ) {
		$head     = array_change_key_case( get_headers( $file, true ) );
		$filesize = $head['content-length'];
	} elseif ( file_exists( $file ) ) {
		$filesize = filesize( $file );
	} else {
		$filesize = 0;
	}
	return (int) $filesize;
}

/**
 * Serialize and add ACF data to nav items
 *
 * @param WP_Post $nav_item The nav menu item object.
 * @return Array
 */
function nav_map( $nav_item ) {
	$data     = (array) $nav_item;
	$acf_data = acf_data( $nav_item->ID );
	if ( is_array( $acf_data ) ) {
		$data = array_merge( $acf_data, $data );
	}
	return apply_filters( 'ep_nav_map', $nav_item, $data );
}
