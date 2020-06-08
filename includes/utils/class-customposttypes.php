<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

/**
 * ElasticPress\Utils\CustomPostTypes custom post type functions.
 */
class CustomPostTypes {

	/**
	 * Array of data for registered custom post types.
	 *
	 * @var Array $registered_custom_post_types
	 */
	private static $registered_custom_post_types = array();

	/**
	 * Registers all custom post types from a directory.
	 *
	 * @since 0.1.0
	 */
	public static function register_all() {
		$cpt_files = array();
		if ( isset( Config::$files['cms']['config.json'] ) ) {
			$cpt_files = Config::$files['cms']['config.json'];
		}
		foreach ( $cpt_files as $file ) {
			self::register( $file['config'] );
		}
	}

	/**
	 * Returns all registered post types.
	 *
	 * @since 0.1.0
	 */
	public static function registered_post_types() {
		return self::$registered_custom_post_types;
	}

	/**
	 * Registers custom post type from config array.
	 *
	 * @since 0.1.0
	 * @param Array $config The config for the CPT.
	 */
	public static function register( $config ) {
		// clean up invalid and empty values.
		$config = self::clean_config( $config );

		// add string translations.
		// $config = Translator::translateConfig($config);.

		$name = $config['name'];
		unset( $config['name'] );

		if ( ! is_wp_error( register_post_type( $name, $config ) ) ) {
			self::$registered_custom_post_types[ $name ]['config'] = $config;
		}
	}

	/**
	 * Registers custom post type from config array.
	 *
	 * @since 0.1.0
	 * @param Array $config The config for the CPT.
	 * @return Array
	 */
	protected static function clean_config( $config ) {
		$clean_config = array_map(
			function ( $value ) {
				if ( is_array( $value ) ) {
					return self::clean_config( $value );
				}
				// don't remove boolean values.
				return empty( $value ) && false !== $value ? null : $value;
			},
			$config
		);

		// remove null values or empty arrays.
		return array_filter(
			$clean_config,
			function ( $value ) {
				return ! ( is_null( $value ) || ( is_array( $value ) && empty( $value ) ) );
			}
		);
	}

}
