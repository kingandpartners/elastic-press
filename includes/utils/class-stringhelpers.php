<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

/**
 * InlineSVG class for handling SVGs.
 */
class StringHelpers {
	/**
	 * Converts a string from camel case to kebap case.
	 *
	 * @since 0.1.0
	 *
	 * @param string $str The string to convert.
	 *
	 * @return string
	 */
	public static function camel_case_to_kebap( $str ) {
		return strtolower( preg_replace( '/([a-zA-Z])(?=[A-Z])/', '$1-', $str ) );
	}

	/**
	 * Strips all HTML tags including script and style,
	 * and trims text to a certain number of words.
	 *
	 * @since 0.1.0
	 *
	 * @param string $str    The string to trim and strip.
	 * @param number $length The string length to return.
	 *
	 * @return string
	 */
	public static function trim_strip( $str, $length = 25 ) {
		if ( isset( $str ) ) {
			return wp_trim_words( wp_strip_all_tags( $str ), $length, '&hellip;' );
		}
		return $str;
	}

	/**
	 * Splits a camel case string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $str The string to split.
	 *
	 * @return string
	 */
	public static function split_camel_case( $str ) {
		$a = preg_split(
			'/(^[^A-Z]+|[A-Z][^A-Z]+)/',
			$str,
			-1,                        // no limit for replacement count.
			PREG_SPLIT_NO_EMPTY        // don't return empty elements.
			| PREG_SPLIT_DELIM_CAPTURE // don't strip anything from output array.
		);
		return implode( ' ', $a );
	}

	/**
	 * Converts a string from kebap case to camel case.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $str                        The string to convert.
	 * @param boolean $capitalize_first_character  Sets if the first character should be capitalized.
	 *
	 * @return string
	 */
	public static function kebap_case_to_camel_case( $str, $capitalize_first_character = false ) {
		$str = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $str ) ) );
		if ( false === $capitalize_first_character ) {
			$str[0] = strtolower( $str[0] );
		}
		return $str;
	}

	/**
	 * Removes a prefix from a string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix The prefix to be removed.
	 * @param string $str    The string to manipulate.
	 *
	 * @return string
	 */
	public static function remove_prefix( $prefix, $str ) {
		if ( substr( $str, 0, strlen( $prefix ) ) == $prefix ) {
			return substr( $str, strlen( $prefix ) );
		}
		return $str;
	}

	/**
	 * Checks if a string starts with a certain string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $search   The string to search for.
	 * @param string $subject  The string to look into.
	 *
	 * @return boolean Returns true if the subject string starts with the search string.
	 */
	public static function starts_with( $search, $subject ) {
		return substr( $subject, 0, strlen( $search ) ) === $search;
	}

	/**
	 * Checks if a string ends with a certain string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $search   The string to search for.
	 * @param string $subject  The string to look into.
	 *
	 * @return boolean Returns true if the subject string ends with the search string.
	 */
	public static function ends_with( $search, $subject ) {
		$search_length  = strlen( $search );
		$subject_length = strlen( $subject );
		if ( $search_length > $subject_length ) {
			return false;
		}
		return substr_compare( $subject, $search, $subject_length - $search_length, $search_length ) === 0;
	}
}
