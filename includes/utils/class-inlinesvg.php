<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

use ElasticPress\Utils\FileHelpers;

/**
 * InlineSVG class for handling SVGs.
 */
class InlineSVG {

	/**
	 * Gets the file contents of a remote SVG.
	 *
	 * @param string $remote_url url for remote SVG.
	 * @return string
	 */
	public static function remote( $remote_url ) {
		$file_contents = file_get_contents( $remote_url );
		$headers       = FileHelpers::parse_headers( $http_response_header );
		if ( 'gzip' === $headers['Content-Encoding'] ) {
			$file_contents = gzinflate( substr( $file_contents, 10, -8 ) );
		}
		return $file_contents;
	}

}
