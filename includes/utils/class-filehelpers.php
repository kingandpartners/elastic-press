<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

/**
 * ElasticPress\Utils\FileHelpers array helper functions.
 */
class FileHelpers {

	/**
	 * Checks if a directory exists and raises an error if not
	 *
	 * @since 0.1.0
	 * @param string $path The path to the directory.
	 */
	public static function error_unless_directory_exists( $path ) {
		if ( ! is_dir( $path ) ) {
			trigger_error(
				esc_html( "$path does not exist. Make sure the directory is created and has proper permissions." ),
				E_USER_ERROR
			);
		}
	}

	/**
	 * Parses $http_response_header into associative array.
	 *
	 * @since 0.1.0
	 * @param Array $headers $http_response_header array.
	 */
	public static function parse_headers( $headers ) {
		$head = array();
		foreach ( $headers as $k => $v ) {
			$t = explode( ':', $v, 2 );
			if ( isset( $t[1] ) ) {
				$head[ trim( $t[0] ) ] = trim( $t[1] );
			} else {
				$head[] = $v;
				if ( preg_match( '#HTTP/[0-9\.]+\s+([0-9]+)#', $v, $out ) ) {
					$head['reponse_code'] = intval( $out[1] );
				}
			}
		}
		return $head;
	}

}
