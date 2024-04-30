<?php
/**
 * ElasticPress\Utils\Config
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

use ElasticPress\Utils\FileHelpers;

/**
 * ElasticPress\Utils\Config configuration helpers.
 */
class Config {
	/**
	 * Array to store registered files.
	 *
	 * @var Array $files
	 *
	 * @since 0.1.0
	 */
	public static $files = array();

	/**
	 * Registers files for a given type.
	 *
	 * @since 0.1.0
	 *
	 * @param string $type Type of files being registered.
	 * @param string $dir  Directory of files being registered.
	 */
	public static function register_files( $type, $dir ) {
		$skip = \RecursiveDirectoryIterator::SKIP_DOTS;
		$di   = new \RecursiveDirectoryIterator( $dir, $skip );
		$it   = new \RecursiveIteratorIterator( $di );
		foreach ( $it as $file ) {
			self::register( $type, $file );
		}
	}

	/**
	 * Registers a file.
	 *
	 * @since 0.1.0
	 *
	 * @param string      $type Type of file being registered.
	 * @param SplFileInfo $file File object being registered.
	 */
	public static function register( $type, $file ) {
		$file_path     = $file->getRealPath();
		$file_name     = basename( $file_path );
		$allowed_files = array(
			'config.json',
			'fields.json',
			'fields.php',
			'functions.php',
			'taxonomies.php',
		);
		if ( ! in_array( $file_name, $allowed_files ) ) {
			return;
		}
		if ( ! isset( self::$files[ $type ][ $file_name ] ) ) {
			self::$files[ $type ][ $file_name ] = array();
		}

		$path_parts = explode( '/', $file_path );
		end( $path_parts );
		$part_name = prev( $path_parts );

		$data = array(
			'name' => $part_name,
			'path' => $file_path,
		);

		if ( 'json' === pathinfo( $file, PATHINFO_EXTENSION ) ) {
			$data['config'] = json_decode( file_get_contents( $file_path ), true );
		}

		array_push( self::$files[ $type ][ $file_name ], $data );
	}

	/**
	 * Raises an error if one of the required constants is not defined.
	 *
	 * @since 0.1.0
	 *
	 * @param array $constants The array of required constants.
	 */
	public static function required_constants( $constants ) {
		foreach ( $constants as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, getenv( $constant ) );
			}
			if ( empty( constant( $constant ) ) ) {
				$msg = sprintf( '%s not defined. Make sure to set %s in your environment.', $constant, $constant );
				trigger_error(
					esc_html( $msg ),
					E_USER_ERROR
				);
			}
		}
	}

	/**
	 * Raises an error if one of the required directories does not exist.
	 *
	 * @since 0.1.0
	 *
	 * @param array $directories The array of required directories.
	 */
	public static function required_directories( $directories ) {
		foreach ( $directories as $directory ) {
			FileHelpers::error_unless_directory_exists( $directory );
		}
	}

}
