<?php
/**
 * ElasticPress\Utils\Fields helper functions for registering and storing ACF
 * Fields
 *
 * @package Elastic_Press
 */

namespace ElasticPress\Utils\Fields;

use ElasticPress\Utils\Config;
use ACFComposer\ACFComposer;

/**
 * Register Gutenberg Block
 *
 * @since 0.1.0
 * @param string $type The type of block (component or feature).
 * @param Array  $block The block config.
 * @param Array  $fields The fields for the block.
 */
function register_block( $type, $block, $fields ) {
	acf_register_block_type(
		array_merge(
			$block,
			array(
			// TODO: 'render_callback' => array(get_class(), 'acfBlockRenderCallback').
			)
		)
	);

	$config = array(
		'name'   => $block['name'],
		'title'  => $block['title'],
		'fields' => $fields,
	);

	$hyphenated_type = str_replace( '_', '-', $block['name'] );
	$location        = array(
		array(
			array(
				'param'    => 'block',
				'operator' => '==',
				'value'    => "acf/$hyphenated_type",
			),
		),
	);
	$config          = array_merge(
		$config,
		array(
			'location' => $location,
		)
	);
	ACFComposer::registerFieldGroup( $config );
}

/**
 * Register Field Layout
 *
 * @since 0.1.0
 * @param string $type The type of layout (component or feature).
 * @param Array  $layout The layout config.
 */
function register_layout( $type, $layout ) {
	// TODO.
}

/**
 * Register Field Group
 *
 * @since 0.1.0
 * @param string $type The type of layout (component or feature).
 * @param Array  $config The top-level group config.
 */
function register_group( $type, $config ) {
	if ( isset( $config['location'] ) ) {
		$location = $config['location'];
	} else {
		$location = array();
	}

	$new_config = array(
		'name'     => $config['group']['name'],
		'title'    => $config['group']['title'],
		'fields'   => $config['fields'],
		'location' => $location,
	);

	if ( isset( $config['position'] ) ) {
		$new_config['position'] = $config['position'];
	}

	ACFComposer::registerFieldGroup( $new_config );
}

/**
 * Register fields
 */
function register_fields() {
	$fields = array();
	if ( isset( Config::$files['cms']['fields.json'] ) ) {
		$fields['feature'] = Config::$files['cms']['fields.json'];
	}
	if ( isset( Config::$files['frontend']['fields.json'] ) ) {
		$fields['component'] = Config::$files['frontend']['fields.json'];
	}

	foreach ( $fields as $type => $configs ) {
		foreach ( $configs as $config ) {
			if ( isset( $config['config']['block'] ) ) {
				register_block(
					$type,
					$config['config']['block'],
					$config['config']['fields']
				);
			}

			if ( isset( $config['config']['group'] ) ) {
				register_group(
					$type,
					$config['config']
				);
			}

			if ( isset( $config['config']['layout'] ) ) {
				register_layout( $type, $config['config']['layout'] );
			}
		}
	}
}
