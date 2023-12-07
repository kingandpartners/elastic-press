<?php
/**
 * ElasticPress\Acf functions to serialize WordPress data
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Acf;

use ElasticPress\Utils\ArrayHelpers;
use ElasticPress\Utils\InlineSVG;
use function ElasticPress\Serializers\post_data;
use function ElasticPress\Serializers\get_image_array;
use function ElasticPress\Serializers\term_data;

/**
 * Convert raw ACF data into nested fields
 *
 * @param Array  $field The field array.
 * @param mixed  $value The raw value.
 * @param Array  $data Parsed data (optional).
 * @param string $base_prefix for nested fields (optional).
 * @return mixed $value
 */
function parse_acf_field( $field, $value, $data = array(), $base_prefix = '' ) {
	//
	// NOTE:
	// If $field is false or doesn't have 'type' key
	// we skip the switch statement and return the original $value
	// down below
	//
	$field_type = false;
	if ($field && array_key_exists('type', $field) ) {
		$field_type = $field['type'];
	}

	switch ( $field_type ) {
		// The default `image` metadata is just the attachment id
		// the image array is much more useful.
		case 'image':
			$value = ( ! empty( $value ) ) ? get_acf_image( $value ) : $value;
			break;
		case 'oembed':
			if ( empty( $value ) ) {
				$value = '';
			}
			break;
		case 'file':
			if ( ! is_array( $value ) ) {
				$value = file_data( $value );
			}
			if ( false === $value ) {
				$value = null;
			}
			break;
		case 'repeater':
			if ( ! is_array( $value ) ) {
				$value = parse_repeater_field( $field, $value, $data, $base_prefix );
			} else {
				// pre-parsed repeater fields do not have images parsed so we
				// check for all images and parse them.
				foreach ( $value as $index => $repeater_field ) {
					$new_value = array();
					foreach ( $repeater_field as $key => $repeater_value ) {
						if ( is_array( $repeater_value ) && isset( $repeater_value['type'] ) && 'image' === $repeater_value['type'] ) {
							$repeater_value = get_acf_image( $repeater_value );
						}
						$new_value[ $key ] = $repeater_value;
					}
					$value[ $index ] = $new_value;
				}
			}
			break;
		case 'post_object':
			$post  = get_post( $value );
			$value = post_data( $post );
			break;
		case 'group':
			$value = parse_group_field( $field, $value, $data, $base_prefix );
			break;
		case 'link':
			if ( empty( $value ) ) {
				$value = array();
			}
			break;
		case 'flexible_content':
			if (!getenv('ES_SKIP_ACF_PARSING')) {
				$data = array();
				foreach ( $value as $index => $m ) {
					$layout_idx = array_search( $m['acf_fc_layout'], array_column( $field['layouts'], 'name' ) );
					$layout     = $field['layouts'][ $layout_idx ];
					$idx        = 0;

					foreach ( $m as $k => $mod ) {
						if ( 'acf_fc_layout' === $k ) {
							continue;
						}
						$f    = false;
						$keys = array(
							$layout['key'] . "_$k",
							str_replace( 'layout', 'field', $layout['name'] . "_$k" ),
							$layout['key'] . '_' . $layout['name'],
						);
						foreach ( $keys as $key ) {
							if ( false !== $f ) {
								break;
							}
							$f = acf_get_field( $key );
						}

						// check for false/null values
						if ( $f && array_key_exists('type', $f) && 'clone' === $f['type'] ) {
							$f = $f['sub_fields'][ $idx ];
						}

						$parsed_value = parse_acf_field( $f, $mod, $data, $base_prefix );
						$data[ $index ][ $k ] = $parsed_value;
						$idx++;
					}
					$data[ $index ]['acf_fc_layout'] = $m['acf_fc_layout'];
				}
				$value = $data;
			}
			break;
	}

	if ( is_array( $value ) ) {
		$value = ArrayHelpers::convert_false_to_null( $value );
	}

	return $value;
}

/**
 * Parses SVG image for inline contents otherwise returns normal image array.
 *
 * @param mixed $value The image object or array.
 * @return Array
 */
function get_acf_image( $value ) {
	if ( ! is_array( $value ) ) {
		$value = get_image_array( $value );
	}
	if ( isset( $value['mime_type'] ) && 'image/svg+xml' === $value['mime_type'] ) {
		$value['raw'] = InlineSVG::remote( $value['url'] );
	}
	return $value;
}

/**
 * Adds url and metadata to file attachment.
 *
 * @param int $attachment_id The attachment id.
 * @return Array
 */
function file_data( $attachment_id ) {
	$file     = array( 'url' => wp_get_attachment_url( $attachment_id ) );
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $metadata ) ) {
		$file = array_merge( $file, $metadata );
	}

	return $file;
}

/**
 * Converts raw field data into repeater array
 *
 * Raw fields have the format `list_0_thing`, where `list` is the repeater
 * name, `0` is the index in the repeater array, and `thing` is the repeater
 * subfield.
 *
 * These are prefixed by `$base_prefix` if the repeater is itself a subfield.
 *
 * @param Array  $field The raw field array.
 * @param int    $num_repeater_fields The number of repeater fields.
 * @param Array  $data The data array.
 * @param string $base_prefix The prefix for nested fields.
 * @return Array
 */
function parse_repeater_field( $field, $num_repeater_fields, $data, $base_prefix ) {
	$name        = $field['name'];
	$sub_fields  = $field['sub_fields'];
	$value_array = array();
	for ( $i = 0; $i < $num_repeater_fields; $i++ ) {
		$value_array[ $i ] = array();
		foreach ( $sub_fields as $sub_field ) {
			$prefix = field_prefix( $base_prefix, $name, $i . '_' . $sub_field['name'] );

			if ( isset( $data[ $prefix ] ) ) {
				$v                                       = $data[ $prefix ];
				$value_array[ $i ][ $sub_field['name'] ] = parse_acf_field( $sub_field, $v, $data, $prefix );
			}
		}
	}

	return $value_array;
}

/**
 * Converts raw underscored fields to nested group data
 *
 * Similar to parse_repeater_field, but without numerical indexing
 *
 * @param Array  $field The raw field array.
 * @param Array  $value The parsed value.
 * @param Array  $data The data array.
 * @param string $base_prefix The prefix for nested fields.
 * @return Array
 */
function parse_group_field( $field, $value, $data, $base_prefix ) {
	$sub_fields  = $field['sub_fields'];
	$value_array = array();

	foreach ( $sub_fields as $sub_field ) {
		$sub_field_key = $sub_field['name'];
		$prefix        = field_prefix( $base_prefix, $field['name'], $sub_field_key );
		$val           = $value;

		if ( is_array( $value ) && array_key_exists( $sub_field_key, $value ) ) {
			// For non-gutenberge serialization.
			$val = parse_acf_field( $sub_field, $value[ $sub_field_key ], $data, $prefix );
		} elseif ( isset( $data[ $prefix ] ) ) {
			// For gutenberg serialization.
			$val = parse_acf_field( $sub_field, $data[ $prefix ], $data, $prefix );
		}
		$value_array[ $sub_field_key ] = $val;
	}

	return $value_array;
}


/**
 * Generate prefixed field name
 *
 * @param string $base_prefix The fields prefix.
 * @param string $field_name The fields name.
 * @param string $suffix The fields suffix.
 * @return string
 */
function field_prefix( $base_prefix, $field_name, $suffix ) {
	if ( ! empty( $base_prefix ) ) {
		return $base_prefix . '_' . $suffix;
	} else {
		return $field_name . '_' . $suffix;
	}
}

/**
 * Get ACF data
 *
 * @param int $id The identifier for ACF data.
 * @return Array
 */
function acf_data( $id ) {
	$fields = get_field_objects( $id );
	$data   = array();
	if ( is_array( $fields ) ) {
		foreach ( $fields as $field_name => $field ) {
			$data = array_merge( $data, parse_page_blocks( $field ) );
		}
	}

	return ArrayHelpers::convert_false_to_null( $data );
}

/**
 * Parse ACF blocks from field array
 *
 * @param Array $field The field array.
 * @return Array
 */
function parse_page_blocks( $field ) {
	$module = $field['value'];
	switch ( true ) {
		case ( is_array( $module ) ):
			$data = parse_acf_field( $field, $module );
			break;
		case ( is_object( $module ) ):
			break;
		case ( false === $module ):
			$data = null;
			break;
		case ( in_array( $field['type'], array( 'taxonomy' ), true ) ):
			$term = get_term( $module );
			$data = term_data( $term );
			break;
		default:
			$data = $module;
	}
	return array( $field['name'] => $data );
}
