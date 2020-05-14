<?php
/**
 * Class ElasticsearchClientTest
 *
 * @package Elastic_Press
 */

use ElasticPress\ElasticSearch;
use ElasticPress\Serializers;
use function ElasticPress\ElasticSearch\elasticsearch_find;
use function ElasticPress\ElasticSearch\elasticsearch_store;

/**
 * Test for ElasticSearch client class
 */
class ElasticsearchTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		ElasticSearch\Client::update_write_aliases();
	}

	/**
	 * Test client instance
	 */
	public function test_client() {
		$client = ElasticSearch\Client::client();
		$this->assertEquals( get_class( $client ), 'Elasticsearch\Client' );
	}

	/**
	 * Test default indexes
	 */
	public function test_default_indexes() {
		$indexes = array( 'page', 'post', 'category' );
		$this->assertEquals( ElasticSearch\Client::indexes(), $indexes );
	}

	/**
	 * Test additional indexes created by custom post types
	 */
	public function test_additional_indexes() {
		register_post_type(
			'blorgh',
			array(
				'label'  => 'Blorgh',
				'public' => true,
			)
		);
		$content                       = array(
			'post_title' => 'Some title',
			'post_type'  => 'blorgh',
		);
		$post                          = $this->factory->post->create_and_get( $content );
		ElasticSearch\Client::$indexes = null;
		$this->assertContains( 'blorgh', ElasticSearch\Client::indexes() );
	}

	/**
	 * Test store and find methods
	 */
	public function test_store_and_find() {
		$content = array( 'post_title' => 'Some title' );
		$post    = $this->factory->post->create_and_get( $content );
		$value   = Serializers\post_data( $post );
		elasticsearch_store( 1, 'post', $value );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( 1, 'post' );
		$this->assertEquals( $found['post_title'], 'Some title' );
	}

}
