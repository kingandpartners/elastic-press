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

}
