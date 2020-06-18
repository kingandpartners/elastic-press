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
class InlineSVG {

	/**
	 * Gets the file contents of a remote SVG.
	 *
	 * @param string $remote_url url for remote SVG.
	 * @return string
	 */
	public static function remote( $remote_url ) {
		// FIXME: if is gzipped.
		// return gzinflate( substr( file_get_contents( $remote_url ), 10, -8 ) );
		return file_get_contents( $remote_url );
	}

}
