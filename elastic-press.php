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

/**
 * Require necessary files
 */
require_once 'includes/utils/class-arrayhelpers.php';
require_once 'includes/utils/class-inlinesvg.php';
require_once 'includes/serializers.php';
require_once 'includes/acf.php';
