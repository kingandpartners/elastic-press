<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Elastic_Press
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Give access to test support functions.
require_once 'support/acf-options-page/functions.php';
require_once 'support/nav-menus/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Manually require dependencies.
	require_once 'vendor/autoload.php';
	require_once 'vendor/mu-plugins/advanced-custom-fields-pro/acf.php';
	require_once 'vendor/mu-plugins/acf-field-group-composer/acf-field-group-composer.php';
	$root_dir = dirname( dirname( __FILE__ ) );
	/**
	 * Expose global env() function from oscarotero/env
	 */
	Env::init();

	/**
	 * Use Dotenv to set required environment variables and load .env file in root
	 */
	if ( file_exists( $root_dir . '/.env.test' ) ) {
		$dotenv = new Dotenv\Dotenv( $root_dir, '/.env.test' );
		$dotenv->load();
		$dotenv->required( array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ELASTICSEARCH_URL', 'SITE_INDEX_KEY', 'WP_ENV' ) );
	}

	require $root_dir . '/elastic-press.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
