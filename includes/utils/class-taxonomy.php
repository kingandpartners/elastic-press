<?php
/**
 * ElasticPress\Utils
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils;

/**
 * ElasticPress\Utils\Taxonomy custom post type functions.
 */
class Taxonomy {

	/**
	 * Array of data for registered taxonomies.
	 *
	 * @var Array $registered_taxonomies
	 */
	private static $registered_taxonomies = array();

	/**
	 * Registers all custom post types from a directory.
	 *
	 * @since 0.1.0
	 * @param string $taxonomy    The name of the taxonomy being registered.
	 * @param string $object_type The type of the object.
	 * @param Array  $args        The args to register the taxonomy.
	 */
	public static function register( $taxonomy, $object_type, $args ) {
		self::$registered_taxonomies[ $taxonomy ]['config'] = $args;
	}

	/**
	 * Returns and array or registered taxonomies.
	 *
	 * @return Array
	 */
	public static function registered_taxonomies() {
		return self::$registered_taxonomies;
	}

}
