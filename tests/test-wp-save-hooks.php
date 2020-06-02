<?php
/**
 * Class WpSaveHooksTest
 *
 * @package Elastic_Press
 */

use ElasticPress\ElasticSearch;
use function ElasticPress\ElasticSearch\elasticsearch_find;
use function Support\AcfOptionsPage\register_global_options_page;
use function Support\AcfOptionsPage\store_options_page;
use function Support\NavMenus\create_nav_menu;

/**
 * Test for ElasticSearch client class
 */
class WpSaveHooksTest extends WP_UnitTestCase {

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
			'SomeOptionsPage',
			array(
				array(
					'name'  => 'test_option',
					'label' => 'Test Option',
					'type'  => 'text',
				),
			)
		);
		store_options_page( 'globalOptionsSomeOptionsPage', 'test_option', 'test' );
		$_POST['acf'] = false;
		do_action( 'acf/save_post', 'options' );
		ElasticSearch\Client::update_read_aliases();
		$found = elasticsearch_find( 'globalOptionsSomeOptionsPage', 'options' );
		$this->assertEquals(
			$found,
			array(
				'test_option' => 'test',
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

}
