<?php
/**
 * Support\AcfOptionsPage helper functions for registering and storing ACF
 * Options pages
 *
 * @package Elastic_Press
 */

namespace Support\AcfOptionsPage;

use ACFComposer\ACFComposer;

/**
 * Helper function to register ACF Global Options page
 *
 * @param string $page_name The Global Options sub-page name.
 * @param Array  $fields The array of ACF fields to register.
 */
function register_global_options_page( $page_name, $fields ) {
	register_options_page( 'Global Options', $page_name, $fields );
}

/**
 * Helper function to register ACF Options page
 *
 * @param string $title The options page name.
 * @param string $page_name The options sub-page name.
 * @param Array  $fields The array of ACF fields to register.
 */
function register_options_page( $title, $page_name, $fields ) {
	$camelized_title = str_replace( ' ', '', lcfirst( ucwords( $title ) ) );
	$parent_slug     = ucfirst( $camelized_title );
	acf_add_options_page(
		array(
			'page_title' => $camelized_title,
			'menu_title' => $camelized_title,
			'menu_slug'  => $parent_slug,
		)
	);

	acf_add_options_sub_page(
		array(
			'page_title'   => $page_name,
			'menu_title'   => $page_name,
			'menu_slug'    => "$camelized_title$page_name",
			'parent_slug'  => $parent_slug,
			'show_in_rest' => true,
		)
	);

	$field_group = ACFComposer::registerFieldGroup(
		array(
			'name'     => "$camelized_title$page_name",
			'title'    => $title,
			'fields'   => prefix_fields( $fields, "$camelized_title$page_name" ),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => "$camelized_title$page_name",
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
	$options_key = "options_${page_name}_${key}";
	$field_key   = "field_${page_name}_${page_name}_$key";
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
