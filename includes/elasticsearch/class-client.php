<?php
/**
 * ElasticPress\ElasticSearch
 *
 * @package Elastic_Press
 */

namespace ElasticPress\ElasticSearch;

use ElasticPress\Utils\CustomPostTypes;
use ElasticPress\Utils\Taxonomy;
use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\ElasticsearchService\ElasticsearchPhpHandler;
use Elasticsearch\ClientBuilder;

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
			if ( defined( 'EP_AWS_REGION' ) ) {
				$provider         = CredentialProvider::fromCredentials(
					new Credentials( EP_AWS_ACCESS_KEY_ID, EP_AWS_SECRET_ACCESS_KEY )
				);
				$handler          = new ElasticsearchPhpHandler( EP_AWS_REGION, $provider );
				static::$instance = ClientBuilder::create()
				->setHandler( $handler )
				->setHosts( array( ELASTICSEARCH_URL ) )
				->build();
			} else {
				static::$instance = ClientBuilder::create()
				->setHosts( array( ELASTICSEARCH_URL ) )
				->build();
			}
		}
		return static::$instance;
	}

	/**
	 * Sets a specified Elasticsearch index
	 */
	public static function indexes() {
		$indexes = static::$indexes;
		if ( null === $indexes ) {
			$indexes    = array( 'post', 'page', 'category', 'nav_menu', 'options', 'seo' );
			$cpts       = array_keys( CustomPostTypes::registered_post_types() );
			$taxonomies = array_keys( Taxonomy::registered_taxonomies() );
			$indexes    = array_unique( array_merge( $indexes, $cpts, $taxonomies ) );
		}
		static::$indexes = apply_filters( 'ep_indicies', $indexes );
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
			do_action(
				'ep_elasticsearch_before_set',
				array(
					'index' => $index,
					'id'    => $id,
					'value' => $value,
				)
			);
			self::client()->index(
				array(
					'index'   => $index_name,
					'type'    => static::$type, // Type is deprecated in ES6 so don't use it.
					'id'      => $id,
					'body'    => $value,
					'refresh' => true,
				)
			);
			do_action(
				'ep_elasticsearch_set',
				array(
					'index' => $index,
					'id'    => $id,
					'value' => $value,
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
	 * Gets all published documents with a url, a.k.a. pages
	 *
	 * @param array $body The query body.
	 */
	public static function search( $body ) {
		$params  = array(
			'body'  => $body,
			'index' => self::read_index_alias( '*' ),
			'size'  => 10000,
		);
		$results = self::client()->search( $params );
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
	 * Gets all published documents with a url, a.k.a. pages
	 */
	public static function get_pages() {
		$index_name   = self::read_index_alias( '*' );
		$public_types = array_keys( get_post_types( array( 'public' => true ) ) );
		$response     = self::client()->search(
			array(
				'index' => $index_name,
				'size'  => 10000,
				'body'  => array(
					'query' => array(
						'bool' => array(
							'must'     => array(
								array(
									'exists' => array(
										'field' => 'url',
									),
								),
								array(
									'term' => array(
										'post_status.keyword' => 'publish',
									),
								),
								array(
									'terms' => array(
										'post_type.keyword' => $public_types,
									),
								),
							),
							'must_not' => array(
								'exists' => array(
									'field' => 'taxonomy',
								),
							),
						),
					),
				),
			)
		);
		$results      = $response['hits']['hits'];
		return $results;
	}

	/**
	 * Gets all documents of a given index - all indicies by default
	 *
	 * @param string $index (optional) The index for lookup.
	 */
	public static function all( $index = '*' ) {
		$index_name = self::read_index_alias( $index );
		$response   = self::client()->search(
			array(
				'index' => $index_name,
				'size'  => 10000,
				'body'  => array(),
			)
		);
		$results    = $response['hits']['hits'];
		return $results;
	}

	/**
	 * Finds a document by url
	 *
	 * @param string $input The url for lookup.
	 */
	public static function find_by_url( $input ) {
		$index_name = self::read_index_alias( '*' );

		$record = null;
		$urls   = array(
			"$input/",
			$input,
		);
		foreach ( $urls as $url ) {
			$results = self::client()->search(
				array(
					'index' => $index_name,
					'body'  => array(
						'query' => array(
							'term' => array(
								'url.keyword' => $url,
							),
						),
					),
					'size'  => 1,
				)
			);

			$record = $results['hits']['hits'][0];

			if ( $record ) {
				break;
			}
		}
		return $record;
	}

	/**
	 * Builds a where query for Elasticsearch
	 *
	 * @param string $index The index identifier.
	 * @param Array  $params Array of lookup parameters.
	 */
	public static function where_query( $index, $params ) {
		$index_name = self::read_index_alias( $index );

		// Remove empty params.
		foreach ( $params as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $params[ $key ] );
			}
		}
		if ( empty( $params ) ) {
			return array();
		}

		// Scope queries by published status.
		$status = isset( $params['post_status'] ) ? $params['post_status'] : 'publish';
		$params = array_merge( $params, array( 'post_status' => $status ) );
		$sort   = isset( $params['sort'] ) ? $params['sort'] : null;
		$range  = isset( $params['range'] ) ? $params['range'] : null;
		$from   = isset( $params['from'] ) ? $params['from'] : 0;
		$size   = isset( $params['size'] ) ? $params['size'] : 10000;
		unset( $params['sort'], $params['range'], $params['from'], $params['size'] );

		// Convert `id` fields into integers.
		array_walk(
			$params,
			function( &$v, $k ) {
				if ( false !== stripos( $k, 'id' ) ) {
					  $v = ( is_array( $v ) ) ? array_map( 'intval', $v ) : intval( $v );
				}
			}
		);

		$or_params = array_filter(
			$params,
			function( $v ) {
				return is_array( $v );
			}
		);

		$and_params = array_filter(
			$params,
			function( $v ) {
				return ! is_array( $v );
			}
		);

		$id_params = array_filter(
			$params,
			function( $v, $k ) {
				return false !== stripos( $k, 'id' );
			},
			ARRAY_FILTER_USE_BOTH
		);

		$query = array( 'bool' => array() );

		if ( ! empty( $or_params ) ) {
			array_walk(
				$or_params,
				function( &$v, $k ) {
					$values = (array) $v;
					$v      = self::map_param( $k, $values );
				}
			);
			$query['bool'] = array(
				'minimum_should_match' => 1,
				'should'               => array_values( $or_params ),
			);
		}

		$must_params = array();
		if ( ! empty( $and_params ) ) {
			array_walk(
				$and_params,
				function( &$v, $k ) {
					$v = self::map_param( $k, $v );
				}
			);
			$must_params = $and_params;
		}

		if ( ! empty( $range ) ) {
			array_push( $must_params, array( 'range' => $range ) );
		}

		if ( ! empty( $must_params ) ) {
			$query['bool'] = array_merge(
				$query['bool'],
				array(
					'must' => array_values( $must_params ),
				)
			);
		}

		$body = array( 'query' => $query );

		$sort_param = null;
		foreach ( $id_params as $key => $val ) {
			if ( is_array( $val ) ) {
				$sort_param = array( $key, $val );
			}
		}
		if ( ! empty( $sort ) ) {
			$body['sort'] = $sort;
		} elseif ( ! empty( $sort_param ) ) {
			list( $key, $ids ) = $sort_param;
			$script            = self::painless_script( $key );
			$body['sort']      = array(
				array(
					'_script' => array(
						'type'   => 'number',
						'script' => array(
							'lang'   => 'painless',
							'source' => $script,
							'params' => array( 'ids' => $ids ),
						),
						'order'  => 'asc',
					),
				),
			);
		}

		return array(
			'index' => $index_name,
			'body'  => $body,
			'size'  => $size,
			'from'  => $from,
		);
	}

	/**
	 * Builds a painless script for ElasticSearch
	 *
	 * @param string $key The key for the id.
	 * @return string
	 */
	private static function painless_script( $key ) {
		return <<<SOURCE
int id = Integer.parseInt(doc['$key'].value);
List ids = params.ids;
for (int i = 0; i < ids.length; i++) {
	if (ids.get(i) == id) { return i; }
}
return 100000;
SOURCE;
	}

	/**
	 * Executes a where query in Elasticsearch
	 *
	 * @param string $index The index identifier.
	 * @param Array  $params Array of lookup parameters.
	 */
	public static function where( $index, $params ) {
		$index_name   = self::read_index_alias( $index );
		$query_params = self::where_query( $index, $params );
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
		$index_name   = self::read_index_alias( $index );
		$query_params = self::where_query( $index, $params );
		unset( $query_params['from'] );
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
			$mappings = array(
				'date_detection' => false,
				'properties'     => array(
					'content'       => array(
						'type' => 'text',
					),
					'taxonomies'    => array(
						'type' => 'nested',
					),
					'unit_id'       => array(
						'type' => 'keyword',
					),
					'ID'            => array(
						'type' => 'keyword',
					),
					'id'            => array(
						'type' => 'keyword',
					),
					'post_id'       => array(
						'type' => 'keyword',
					),
					'term_id'       => array(
						'type' => 'keyword',
					),
					'post_date'     => array(
						'type'   => 'date',
						'format' => 'yyyy-MM-dd HH:mm:ss',
					),
					'post_modified' => array(
						'type'   => 'date',
						'format' => 'yyyy-MM-dd HH:mm:ss',
					),
				),
			);
			$mappings = apply_filters( 'ep_mappings', $mappings, $index_type );
			$params   = array(
				'index' => $new_index,
				'body'  => array(
					'settings' => array(
						'mapping' => array(
							'total_fields' => array(
								'limit' => 10000,
							),
						),
					),
					'mappings' => array(
						'jsondata' => $mappings,
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
		if ( '*' !== $type && ! in_array( $type, self::indexes(), true ) ) {
			throw new \Exception( $type . ' is not in the $indexes array' );
		}

		$environment     = WP_ENV;
		$current_blog_id = get_current_blog_id();

		return SITE_INDEX_KEY . $current_blog_id . '_' . $environment . '_' . $type;
	}

	/**
	 * Maps params to either term or nested query parameters
	 *
	 * @param string $key The key identifier of the field.
	 * @param mixed  $value The mixed value of the field.
	 */
	private static function map_param( $key, $value ) {
		$is_assoc = (
			is_array( $value ) &&
			array_keys( $value ) !== range( 0, count( $value ) - 1 )
		);
		if ( $is_assoc ) {
			array_walk(
				$value,
				function( &$v, $k ) use ( $key ) {
					$k = ( is_int( $v ) ) ? $k : "$k.keyword";
					$v = array( 'match' => array( "$key.$k" => $v ) );
				}
			);
			return array(
				'nested' => array(
					'path'  => $key,
					'query' => array(
						'bool' => array(
							'must' => array_values( $value ),
						),
					),
				),
			);
		} else {
			if ( is_array( $value ) ) {
				array_walk(
					$value,
					function( &$v, $k ) use ( $key ) {
						$k = ( is_int( $v ) ) ? $key : "$key.keyword";
						$v = array( 'term' => array( $k => $v ) );
					}
				);
			} else {
				$key   = ( is_int( $value ) ) ? $key : "$key.keyword";
				$value = array(
					'term' => array( $key => $value ),
				);
			}
			return $value;
		}
	}

}
