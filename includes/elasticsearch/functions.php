<?php
/**
 * ElasticPress\ElasticSearch
 *
 * @package Elastic_Press
 */

namespace ElasticPress\ElasticSearch;

require_once __DIR__ . '/class-client.php';

use ElasticPress\ElasticSearch\Client;

/**
 * Store values into Elasticsearch
 *
 * @param mixed  $id The main identifier for the data to be stored.
 * @param string $index_name The index identifier.
 * @param Array  $value The data array to be stored.
 */
function elasticsearch_store( $id, $index_name, $value ) {
	Client::set( $id, $index_name, $value );
}

/**
 * Retrieve values from Elasticsearch by ID
 *
 * @param mixed  $id The main identifier for the data to be found.
 * @param string $index_name The index identifier.
 */
function elasticsearch_find( $id, $index_name ) {
	return Client::find( $id, $index_name );
}

/**
 * Retrieve values from Elasticsearch by query params
 *
 * @param string $index_name The index identifier.
 * @param Array  $params Query array of parameters.
 */
function elasticsearch_where( $index_name, $params = array() ) {
	return Client::where( $index_name, $params );
}

/**
 * Retrieve values from Elasticsearch by query params
 *
 * @param string $index_name The index identifier.
 * @param Array  $params Query array of parameters.
 */
function elasticsearch_all( $index_name = '*' ) {
	return Client::all( $index_name );
}

/**
 * Delete values from Elasticsearch by query params
 *
 * @param string $index_name The index identifier.
 * @param Array  $params Query array of parameters.
 */
function elasticsearch_delete_where( $index_name, $params = array() ) {
	return Client::delete_where( $index_name, $params );
}

/**
 * Delete values from Elasticsearch by query params
 *
 * @param string $index_name The index identifier.
 * @param Array  $params Query array of parameters.
 */
function elasticsearch_find_by_url( $url ) {
	return Client::find_by_url( $url );
}

/**
 * Delete values from Elasticsearch by query params
 *
 * @param string $index_name The index identifier.
 * @param Array  $params Query array of parameters.
 */
function elasticsearch_get_pages() {
	return Client::get_pages();
}
