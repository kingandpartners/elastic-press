<?php
/**
 * Sample taxonomy registration
 *
 * @package Elastic_Press
 */

register_taxonomy(
	'experience_category',
	'experiences',
	array(
		'labels'            => array(
			'name'          => _x( 'Experience Categories', 'taxonomy general name' ),
			'singular_name' => _x( 'Experience Category', 'taxonomy singular name' ),
			'add_new_item'  => 'Add New Category',
		),
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rest_base'         => 'experience_categories',
		'rewrite'           => array(
			'slug'       => 'experience_category',
			'with_front' => false,
		),
	)
);
