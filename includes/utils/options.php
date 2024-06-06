<?php
/**
 * ElasticPress\Utils\Options helper functions for registering and storing ACF
 * Options pages
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils\Options;

use ACFComposer\ACFComposer;
use ElasticPress\Utils\Config;
use ElasticPress\Utils\StringHelpers;

/**
 * Register global options
 */
function register_global_options() {
	$fields = array();
	if ( isset( Config::$files['cms']['fields.json'] ) ) {
		$fields['feature'] = Config::$files['cms']['fields.json'];
	}
	if ( isset( Config::$files['frontend']['fields.json'] ) ) {
		$fields['component'] = Config::$files['frontend']['fields.json'];
	}

	foreach ( $fields as $type => $configs ) {
		foreach ( $configs as $config ) {
			if ( isset( $config['config']['globalOptions'] ) ) {
				register_global_options_page(
					$type,
					$config['name'],
					$config['config']['globalOptions']
				);
			}
		}
	}
}

/**
 * Helper function to register ACF Global Options page
 *
 * @param string $type The type of global option this is, i.e. component, feature, etc.
 * @param string $page_name The Global Options sub-page name.
 * @param Array  $fields The array of ACF fields to register.
 */
function register_global_options_page( $type, $page_name, $fields ) {
	$skip = apply_filters( 'ep_skip_global_options', false, $type, $page_name, $fields );
	if ( $skip ) {
		return;
	}
	$fields = apply_filters( 'ep_global_options_fields', $fields, $type, $page_name );
	register_options_page( 'Global Options', $type, $page_name, $fields );
}

/**
 * Helper function to register ACF Options page
 *
 * @param string $title The options page name.
 * @param string $type The type of options page name.
 * @param string $page_name The options sub-page name.
 * @param Array  $fields The array of ACF fields to register.
 */
function register_options_page( $title, $type, $page_name, $fields ) {
	$type            = ucfirst( $type );
	$camelized_title = str_replace( ' ', '', lcfirst( ucwords( $title ) ) );
	$page_title      = StringHelpers::split_camel_case( $page_name );
	$parent_slug     = ucfirst( $camelized_title );
	$menu_slug       = "$camelized_title$type$page_name";
	acf_add_options_page(
		array(
			'page_title' => $title,
			'menu_title' => $title,
			'menu_slug'  => $parent_slug,
		)
	);

	acf_add_options_sub_page(
		array(
			'page_title'   => $page_title,
			'menu_title'   => $page_title,
			'menu_slug'    => $menu_slug,
			'parent_slug'  => $parent_slug,
			'show_in_rest' => true,
		)
	);

	$field_group = ACFComposer::registerFieldGroup(
		array(
			'name'     => $menu_slug,
			'title'    => $page_title,
			'fields'   => prefix_fields( $fields, $menu_slug ),
			'style'    => 'seamless',
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => $menu_slug,
					),
				),
			),
		)
	);
}

/**
 * Helper function to store ACF options page
 *
 * @param string $page_name The full options page name - main and sub-pages.
 * @param string $key       The key for the option to be stored.
 * @param mixed  $value     The value for the option to be stored.
 */
function store_options_page( $page_name, $key, $value ) {
	$options_key = "options_{$page_name}_{$key}";
	$field_key   = "field_{$page_name}_{$page_name}_$key";
	update_option( "_$options_key", $field_key );
	update_option( $options_key, $value );
}

/**
 * Helper function to prefix ACF options fields
 *
 * @param Array  $fields The fields to prefix.
 * @param string $prefix The prefix for the fields.
 */
function prefix_fields( $fields, $prefix ) {
	return array_map(
		function ( $field ) use ( $prefix ) {
			$field['name'] = $prefix . '_' . $field['name'];
			return $field;
		},
		$fields
	);
}
