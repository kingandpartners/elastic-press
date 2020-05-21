<?php
/**
 * ElasticPress\ElasticSearch
 *
 * @package Elastic_Press
 */

namespace ElasticPress\ElasticSearch;

/**
 * Client class for interfacing with ElasticSearch
 */
class Client {

	/**
	 * Private variable for storing the ElasticSearch\Client instance
	 *
	 * @var Elasticsearch\Client $instance
	 */
	private static $instance;

	/**
	 * Public variable for memoizing indicies (purposefully misspelled as to not
	 * be confused with the Elasticsearch method). Also made public for simpler
	 * testing.
	 *
	 * @var Array $indexes
	 */
	public static $indexes;

	/**
	 * Private variable used as constant since type is deprecated in ES6
	 *
	 * @var string $type
	 */
	private static $type = 'jsondata';

	/**
	 * Builds Elasticseach\Client
	 */
	public static function client(): \Elasticsearch\Client {
		if ( null === static::$instance ) {
			static::$instance = \Elasticsearch\ClientBuilder::create()
			->setHosts( array( ELASTICSEARCH_URL ) )
			->build();
		}
		return static::$instance;
	}

	/**
	 * Sets a specified Elasticsearch index
	 */
	public static function indexes() {
		if ( null === static::$indexes ) {
			global $wpdb;
			$posts_indexes = $wpdb->get_results(
				'SELECT DISTINCT post_type FROM ' . $wpdb->posts
			);
			$posts_indexes = array_column( $posts_indexes, 'post_type' );
			$posts_indexes = array_merge( $posts_indexes, array( 'page', 'post' ) );

			$taxons_indexes = $wpdb->get_results(
				'SELECT DISTINCT taxonomy FROM ' . $wpdb->term_taxonomy
			);
			$taxons_indexes = array_column( $taxons_indexes, 'taxonomy' );
			$taxons_indexes = array_merge( $taxons_indexes, array( 'category', 'nav_menu' ) );
			$indexes        = array_merge( $posts_indexes, $taxons_indexes );
			array_push( $indexes, 'options', 'seo' );
			$indexes = array_values( array_unique( $indexes ) );

			static::$indexes = apply_filters( 'ep_indicies', $indexes );
		}
		return static::$indexes;
	}

	/**
	 * Sets a specified Elasticsearch index
	 *
	 * @param mixed  $id The main identifier for the data to be stored.
	 * @param string $index The index identifier.
	 * @param Array  $value The data array to be stored.
	 * @throws \Exception A general exception.
	 */
	public static function set( $id, $index, $value ) {
		$index_name = self::write_index_alias( $index );

		try {
			self::client()->index(
				array(
					'index'   => $index_name,
					'type'    => static::$type, // Type is deprecated in ES6 so don't use it.
					'id'      => $id,
					'body'    => $value,
					'refresh' => true,
				)
			);
		} catch ( \Exception $e ) {
			error_log( "Error trying to save record with index: '$index' id: '$id'" );

			throw $e;
		}
	}

	/**
	 * Finds a specified Elasticsearch index entry by id
	 *
	 * @param mixed  $id The main identifier for the data to be stored.
	 * @param string $index The index identifier.
	 */
	public static function find( $id, $index ) {
		$index_name = self::read_index_alias( $index );
		try {
			$response = self::client()->get(
				array(
					'index' => $index_name,
					'id'    => $id,
					'type'  => static::$type,
				)
			);
			return $response['_source'];
		} catch ( \Exception $e ) {
			// Elasticsearch throws an exception when nothing is found, so instead
			// we catch and return so the record is null.
			return;
		}
	}

	/**
	 * Builds a where query for Elasticsearch
	 *
	 * @param string $index The index identifier.
	 * @param Array  $params Array of lookup parameters.
	 */
	public static function where_query( $index, $params ) {
		$index_name = self::read_index_alias( $index );

		$or_params = array_filter(
			$params,
			function( $v ) {
				return is_array( $v );
			}
		);

		$or_queries = array();
		foreach ( $or_params as $k => $v ) {
			$or_query = array_map(
				function( $val ) use ( $k ) {
					return "$k:$val";
				},
				$v
			);
			$or_query = implode( ' OR ', $or_query );
			array_push( $or_queries, "($or_query)" );
		}

		$and_params = array_filter(
			$params,
			function( $v ) {
				return ! is_array( $v );
			}
		);

		$and_queries = array();
		foreach ( $and_params as $k => $v ) {
			array_push( $and_queries, "$k:$v" );
		}

		$query = implode( ' AND ', array_merge( $or_queries, $and_queries ) );
		return $query;
	}

	/**
	 * Executes a where query in Elasticsearch
	 *
	 * @param string $index The index identifier.
	 * @param Array  $params Array of lookup parameters.
	 */
	public static function where( $index, $params ) {
		$index_name = self::read_index_alias( $index );
		$query      = self::where_query( $index, $params );

		$query_params = array(
			'index' => $index_name,
			'size'  => 10000, // set arbitrarily large number to return 'all'.
			'q'     => $query,
		);
		$results      = self::client()->search( $query_params );
		if ( empty( $results['hits']['hits'] ) ) {
			$records = array();
		} else {
			$records = array_map(
				function( $record ) {
					return $record['_source'];
				},
				$results['hits']['hits']
			);
		}
		return $records;
	}

	/**
	 * Deletes Elasticsearch index entires by lookup parameters.
	 *
	 * @param string $index The index identifier.
	 * @param Array  $params Array of lookup parameters.
	 */
	public static function delete_where( $index, $params ) {
		$index_name = self::read_index_alias( $index );
		$query      = self::where_query( $index, $params );

		$query_params = array(
			'index' => $index_name,
			'type'  => static::$type,
			'q'     => $query,
		);
		self::client()->deleteByQuery( $query_params );
	}

	/**
	 * Creates and refreshes all Elasticsearch indicies.
	 */
	public static function update_write_aliases() {
		$timestamp = strftime( '%Y%m%d%H%M%S' );
		foreach ( self::indexes() as $index_type ) {
			$write_alias = self::write_index_alias( $index_type );
			$new_index   = self::read_index_alias( $index_type ) . '_' . $timestamp;
			$old_indexes = self::delete_alias_if_exists( $write_alias );
			foreach ( $old_indexes as $old_index ) {
				// If there's no alias pointing the index now, it's garbage and should
				// be deleted. This can happen if there are errors in a previous reindex.
				if ( ! self::client()->indices()->getAlias( array( 'index' => $old_index ) )[ $old_index ]['aliases'] ) {
					self::client()->indices()->delete( array( 'index' => $old_index ) );
				}
			}
			$params = array(
				'index' => $new_index,
				'body'  => array(
					'settings' => array(
						'mapping' => array(
							'total_fields' => array(
								'limit' => 10000,
							),
						),
					),
				),
			);
			self::client()->indices()->create( $params );
			self::client()->indices()->putAlias(
				array(
					'index' => $new_index,
					'name'  => $write_alias,
				)
			);
		}
	}

	/**
	 * Refreshes read aliases for Elasticsearch
	 */
	public static function update_read_aliases() {
		foreach ( self::indexes() as $index_type ) {
			$read_alias  = self::read_index_alias( $index_type );
			$write_alias = self::write_index_alias( $index_type );
			$indices     = self::client()->indices();

			// Fetch new index name from write alias.
			list($new_index) = array_keys( $indices->getAlias( array( 'name' => $write_alias ) ) );

			// Point read alias to new index.
			$old_indexes = self::delete_alias_if_exists( $read_alias );
			$indices->putAlias(
				array(
					'index' => $new_index,
					'name'  => $read_alias,
				)
			);

			// Drop all old indexes.
			if ( $old_indexes ) {
				$indices->delete( array( 'index' => $old_indexes ) );
			}
		}
	}

	/**
	 * Deletes alias if it exists
	 *
	 * @param string $alias_name The index alias to look for.
	 */
	private static function delete_alias_if_exists( $alias_name ) {
		$indices = self::client()->indices();
		// Plain index with name of alias can be created in some scenarios, drop it
		// if one exists.
		$indices->delete(
			array(
				'index'  => $alias_name,
				'client' => array( 'ignore' => array( 400, 404 ) ),
			)
		);

		if ( $indices->existsAlias( array( 'name' => $alias_name ) ) ) {
			$current_indexes = $indices->getAlias( array( 'name' => $alias_name ) );
			$index_names     = array_keys( $current_indexes );

			// Loop over each index referenced by the current alias and delete the alias.
			foreach ( $index_names as $current_index ) {
				$indices->deleteAlias(
					array(
						'name'  => $alias_name,
						'index' => $current_index,
					)
				);
			}

			return $index_names;
		}

		return array();
	}

	/**
	 * Constructs the write index alias for given type
	 *
	 * @param string $type Index type.
	 */
	private static function write_index_alias( $type ) {
		return self::read_index_alias( $type ) . '_write';
	}

	/**
	 * Constructs the read index alias for given type
	 *
	 * @param string $type Index type.
	 * @throws \Exception A general exception.
	 */
	private static function read_index_alias( $type ) {
		if ( ! in_array( $type, self::indexes(), true ) ) {
			throw new \Exception( $type . ' is not in the $indexes array' );
		}

		$environment     = WP_ENV;
		$current_blog_id = get_current_blog_id();

		return SITE_INDEX_KEY . $current_blog_id . '_' . $environment . '_' . $type;
	}

}
