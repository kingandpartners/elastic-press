<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

/**
 * ElasticPress\Utils\ArrayHelpers array helper functions.
 */
class ArrayHelpers {

	/**
	 * Checks if an array is associative.
	 *
	 * @since 0.1.0
	 * @param array $array The array to check.
	 * @return boolean
	 */
	public static function is_assoc( array $array ) {
		// Keys of the array.
		$keys = array_keys( $array );

		// If the array keys of the keys match the keys, then the array must
		// not be associative (e.g. the keys array looked like {0:0, 1:1...}).
		return array_keys( $keys ) !== $keys;
	}

	/**
	 * Converts indexed values to associative keys.
	 *
	 * @since 0.1.0
	 * @param array $array The array to convert.
	 * @return array
	 */
	public static function indexed_values_to_assoc_keys( array $array ) {
		$values = array_map(
			function ( $value ) {
				return is_array( $value ) ? $value : array();
			},
			$array
		);

		$keys = array_map(
			function ( $key ) use ( $array ) {
				return is_int( $key ) ? $array[ $key ] : $key;
			},
			array_keys( $array )
		);

		return array_combine( $keys, $values );
	}

	/**
	 * Convert false values from ACF to null so ElasticSearch is happy
	 * We may want to whitelist valid boolean keys in the future, but this is good
	 * for now.
	 *
	 * @since 0.1.0
	 *
	 * @param array $array The array to convert.
	 *
	 * @return array
	 */
	public static function convert_false_to_null( array $array ) {
		array_walk_recursive(
			$array,
			function( &$value, $key ) {
				$ignore_keys = array(
					'enable',
				);

				if ( ! in_array( $key, $ignore_keys ) && false === $value ) {
					$value = null;
				}

				// Link fields must return an array or be null. Acf seems to return
				// empty strings rather than false which causes an issue with
				// ElasticSearch if we have link keys at the same depth with different
				// schemas.
				if ( 'link' === $key && ! is_array( $value ) && ! is_null( $value ) ) {
					$value = array( 'string_value' => $value );
				}
			}
		);

		return $array;
	}
}
