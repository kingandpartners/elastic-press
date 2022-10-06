<?php
/**
 * Plugin Name:     Elastic Press
 * Plugin URI:      https://github.com/kingandpartners/elastic-press
 * Description:     This plugin serializes and stores WordPress data into ElasticSearch upon save.
 * Author:          Justin Grubbs
 * Author URI:      https://github.com/kingandpartners/elastic-press
 * Text Domain:     elastic-press
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Elastic_Press
 */

namespace ElasticPress;

use ElasticPress\Utils\Config;
use ElasticPress\Utils\Fields;
use ElasticPress\Utils\Options;
use ElasticPress\Utils\CustomPostTypes;

/**
 * Require utility files
 */
require_once 'includes/utils/class-arrayhelpers.php';
require_once 'includes/utils/class-stringhelpers.php';
require_once 'includes/utils/class-inlinesvg.php';
require_once 'includes/utils/class-filehelpers.php';
require_once 'includes/utils/class-config.php';
require_once 'includes/utils/class-customposttypes.php';
require_once 'includes/utils/class-taxonomy.php';
require_once 'includes/utils/options.php';
require_once 'includes/utils/fields.php';

/**
 * Define required constants
 */
Config::required_constants(
	array(
		'ELASTICSEARCH_URL',
		'WP_ENV',
		'SITE_INDEX_KEY',
		'FRONTEND_PATH',
		'CMS_PATH',
	)
);

/**
 * Define required directories
 */
Config::required_directories(
	array(
		FRONTEND_PATH,
		CMS_PATH,
	)
);

/**
 * Require necessary files
 */
require_once 'includes/elasticsearch/functions.php';
require_once 'includes/acf.php';
require_once 'includes/seo.php';
require_once 'includes/serializers.php';
require_once 'includes/storage.php';
require_once 'includes/sweepers.php';
require_once 'includes/wp-save-hooks.php';

/**
 * WordPress hooks to init and load plugin
 */
add_action( 'after_setup_theme', __NAMESPACE__ . '\init' );
add_action( 'after_setup_theme', __NAMESPACE__ . '\load', 100 );
add_action( 'registered_taxonomy', 'ElasticPress\Utils\Taxonomy::register', 10, 3 );

/**
 * Register files
 */
function init() {
	Config::register_files( 'frontend', FRONTEND_PATH );
	Config::register_files( 'cms', CMS_PATH );
}

/**
 * Load fields from registered files
 */
function load() {
	CustomPostTypes::register_all();
	Options\register_global_options();
	register_php_files();
	Fields\register_fields();
	do_action( 'ep_after_load' );
}

/**
 * Register PHP files
 */
function register_php_files() {
	$php_files = array(
		'fields.php',
		'functions.php',
		'taxonomies.php',
	);

	foreach ( array( 'cms', 'frontend' ) as $type ) {
		foreach ( $php_files as $php_file ) {
			if ( ! isset( Config::$files[ $type ][ $php_file ] ) ) {
				continue;
			}
			foreach ( Config::$files[ $type ][ $php_file ] as $config ) {
				require_once $config['path'];
			}
		}
	}
}
