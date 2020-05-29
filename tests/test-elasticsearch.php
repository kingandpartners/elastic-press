<?php
/**
 * Class ElasticsearchClientTest
 *
 * @package Elastic_Press
 */

use ACFComposer\ACFComposer;
use ElasticPress\ElasticSearch;
use ElasticPress\Serializers;
use ElasticPress\Storage;
use ElasticPress\Sweepers;
use function ElasticPress\ElasticSearch\elasticsearch_find;
use function ElasticPress\ElasticSearch\elasticsearch_store;
use function ElasticPress\ElasticSearch\elasticsearch_where;

/**
 * Test for ElasticSearch client class
 */
class ElasticsearchTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		ElasticSearch\Client::update_write_aliases();
		// Need the editor role to be able to insert taxonomy, etc.
		$admin = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $admin );
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
		$indexes = array( 'page', 'post', 'category', 'nav_menu', 'options', 'seo' );
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
		Storage\store_post( $post );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( $post->ID, 'post' );
		$this->assertEquals( $found['post_title'], 'Some title' );
	}

	/**
	 * Test store_page
	 */
	public function test_store_page() {
		$content = array(
			'post_title' => 'Page title',
			'post_type'  => 'page',
		);
		$page    = $this->factory->post->create_and_get( $content );
		Storage\store_page( $page );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( $page->ID, 'page' );
		$this->assertEquals( $found['post_title'], 'Page title' );
	}

	/**
	 * Test store_menu
	 */
	public function test_store_menu() {
		$menu = $this->createNavMenu();
		Storage\store_menu( $menu );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( 'test-menu_nav', 'nav_menu' );
		$this->assertEquals( count( $found['menu_items'] ), 1 );
		$this->assertEquals( $found['menu_items'][0]['title'], 'Instagram' );
		$this->assertEquals( $found['menu_items'][0]['url'], 'http://test.com' );
	}


	/**
	 * Test store_terms_data
	 */
	public function test_store_terms_data() {
		ElasticSearch\Client::$indexes = null;
		$taxonomy                      = 'custom_tax';
		register_taxonomy( $taxonomy, null );
		$this->factory()->term->create_many( 3, array( 'taxonomy' => $taxonomy ) );
		// since we are adding a custom taxonomy index we need to update the aliases.
		ElasticSearch\Client::update_write_aliases();
		Storage\store_terms_data( $taxonomy );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_where(
			$taxonomy,
			array( 'post_status' => 'publish' )
		);
		$this->assertCount( 3, $found );
	}

	/**
	 * Test elasticsearch_where
	 */
	public function test_elasticsearch_where() {
		$post_1 = $this->factory->post->create_and_get();
		$post_2 = $this->factory->post->create_and_get();
		$post_3 = $this->factory->post->create_and_get();
		ElasticPress\Sweepers\sweep_post_type( 'post' );
		ElasticSearch\Client::update_read_aliases();

		$id_arr = array( $post_3->ID, $post_2->ID, $post_1->ID );

		$posts = elasticsearch_where( 'post', array( 'ID' => $id_arr ) );

		$this->assertEquals( $id_arr, array_column( $posts, 'ID' ) );
	}

	/**
	 * Test store_options
	 */
	public function test_store_options() {
		acf_add_options_page(
			array(
				'page_title' => 'globalOptions',
				'menu_title' => 'globalOptions',
				'menu_slug'  => 'GlobalOptions',
			)
		);

		acf_add_options_sub_page(
			array(
				'page_title'   => 'SomePage',
				'menu_title'   => 'SomePage',
				'menu_slug'    => 'globalOptionsSomePage',
				'parent_slug'  => 'GlobalOptions',
				'show_in_rest' => true,
			)
		);

		$field_group = ACFComposer::registerFieldGroup(
			array(
				'name'     => 'globalOptionsSomePage',
				'title'    => 'Global Options',
				'fields'   => array(
					array(
						'name'  => 'globalOptionsSomePage_some_data',
						'label' => 'Some Data',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'globalOptionsSomePage',
						),
					),
				),
			)
		);

		update_option( '_options_globalOptionsSomePage_some_data', 'field_globalOptionsSomePage_globalOptionsSomePage_some_data' );
		update_option( 'options_globalOptionsSomePage_some_data', 'test' );
		Storage\store_options( 'options' );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( 'globalOptionsSomePage', 'options' );
		$this->assertEquals( $found, array( 'some_data' => 'test' ) );
	}

	/**
	 * Test warm_site_cache
	 */
	public function test_warm_site_cache() {
		// Overly simple test to make sure nothing is breaking in sweeper methods.
		Sweepers\warm_site_cache();
		$this->assertEquals( true, true );
	}

	/**
	 * Helper function to create a menu with one nav menu item
	 */
	private function createNavMenu() {
		// Create nav menu.
		$nav_menu = $this->factory->term->create_and_get(
			array(
				'name'     => 'Test Menu',
				'taxonomy' => 'nav_menu',
				'slug'     => 'test-menu',
			)
		);
		// Create nav menu item.
		$menu_item = $this->factory->post->create_and_get(
			array(
				'post_title' => 'Instagram',
				'post_type'  => 'nav_menu_item',
			)
		);
		// Add menu item to nav menu.
		wp_set_object_terms( $menu_item->ID, array( $nav_menu->term_id ), 'nav_menu' );
		// Update postmeta for menu item (link details).
		update_post_meta( $menu_item->ID, '_menu_item_type', 'custom' );
		update_post_meta( $menu_item->ID, '_menu_item_menu_item_parent', 0 );
		update_post_meta( $menu_item->ID, '_menu_item_object_id', $menu_item->ID );
		update_post_meta( $menu_item->ID, '_menu_item_object', 'custom' );
		update_post_meta( $menu_item->ID, '_menu_item_target', '' );
		update_post_meta( $menu_item->ID, '_menu_item_url', 'http://test.com' );
		return $nav_menu;
	}

}
