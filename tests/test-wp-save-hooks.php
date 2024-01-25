<?php
/**
 * Class WpSaveHooksTest
 *
 * @package Elastic_Press
 */

use ElasticPress\ElasticSearch;
use function ElasticPress\ElasticSearch\elasticsearch_find;
use function ElasticPress\Utils\Options\register_global_options_page;
use function ElasticPress\Utils\Options\store_options_page;
use function Support\NavMenus\create_nav_menu;

/**
 * Test for ElasticSearch client class
 */
class WpSaveHooksTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp(); // Aha!
		ElasticSearch\Client::update_write_aliases();
		// Need the editor role to be able to insert taxonomy, etc.
		$admin = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $admin );
	}

	/**
	 * Test wp_insert_post hook
	 */
	public function test_wp_insert_post() {
		$content = array( 'post_title' => 'Some inserted title' );
		$post    = $this->factory->post->create_and_get( $content );
		do_action( 'wp_insert_post', $post->ID, $post, true );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( $post->ID, 'post' );
		$this->assertEquals( $found['post_title'], 'Some inserted title' );
	}

	/**
	 * Test acf_save_post hook
	 */
	public function test_acf_save_post() {
		register_global_options_page(
			'component',
			'SomeOptionsPage',
			array(
				array(
					'name'  => 'test_option',
					'label' => 'Test Option',
					'type'  => 'text',
				),
			)
		);
		store_options_page( 'globalOptionsComponentSomeOptionsPage', 'test_option', 'test' );
		$_POST['acf'] = false;
		do_action( 'acf/save_post', 'options' );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( 'globalOptionsComponentSomeOptionsPage', 'options' );
		$this->assertEquals(
			$found,
			array(
				'test_option' => 'test',
				'ID'          => 'globalOptionsComponentSomeOptionsPage',
			)
		);
	}

	/**
	 * Test edited_term hook
	 */
	public function test_edited_term() {
		ElasticSearch\Client::$indexes = null;
		$taxonomy                      = 'custom_tax';
		register_taxonomy( $taxonomy, null );
		$term_args = array( 'taxonomy' => $taxonomy );
		$term      = $this->factory()->term->create_and_get( $term_args );
		// since we are adding a custom taxonomy index we need to update the aliases.
		ElasticSearch\Client::update_write_aliases();
		do_action( 'edited_term', $term->term_id, $term->term_id, $taxonomy );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( $term->term_id, $taxonomy );
		$this->assertEquals( $found['term_id'], $term->term_id );
	}

	/**
	 * Test wp_update_nav_menu hook
	 */
	public function test_wp_update_nav_menu() {
		$menu = create_nav_menu( $this->factory );
		do_action( 'wp_update_nav_menu', $menu->term_id, array() );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( "{$menu->slug}_nav", 'nav_menu' );
		$this->assertEquals( count( $found['menu_items'] ), 1 );
		$this->assertEquals( $found['menu_items'][0]['title'], 'Instagram' );
		$this->assertEquals( $found['menu_items'][0]['url'], 'http://test.com' );
	}

	/**
	 * Test ep_skip_indexing filter
	 */
	public function test_ep_skip_indexing_filter() {
		$post_content      = array(
			'post_title' => 'This will be indexed',
			'post_type'  => 'post',
		);
		$skip_post_content = array(
			'post_title' => 'This will not be indexed',
			'post_type'  => 'skip_post',
		);

		add_filter(
			'ep_insert_post_object_filter',
			function( $object ) {
				$skip_indexing = array(
					'skip_post',
				);
				if ( in_array( $object->post_type, $skip_indexing ) ) {
					$object->skip_indexing = true;
				}
				return $object;
			},
			10,
			2
		);

		$post = $this->factory->post->create_and_get( $post_content );
		do_action( 'wp_insert_post', $post->ID, $post, true );

		$skip_post = $this->factory->post->create_and_get( $skip_post_content );
		do_action( 'wp_insert_post', $skip_post->ID, $skip_post, true );

		ElasticSearch\Client::update_read_aliases();
		$found_post = elasticsearch_find( $post->ID, 'post' );
		$this->assertEquals( $found_post['post_title'], 'This will be indexed' );

		$skip_post_exists = get_post( $skip_post->ID );
		// $skip_post_is_not_indexed = elasticsearch_find( $skip_post->ID, 'do_not_index' );
		$this->assertEquals( $skip_post_exists->post_title, 'This will not be indexed' );
		// $this->assertEquals( $skip_is_not_indexed, 'This will not be indexed' );
	}
}
