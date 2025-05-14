<?php
/**
 * Class FlexibleContentTest
 *
 * @package Elastic_Press
 */

use ElasticPress\Serializers;
use ACFComposer\ACFComposer;

/**
 * Test for serializers
 */
class FlexibleContentTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp(); // Aha!

		ACFComposer::registerFieldGroup(
			array(
				'name'     => 'flex_components',
				'title'    => 'Flex Components',
				'fields'   => array(
					array(
						'name'    => 'components',
						'label'   => 'Components',
						'type'    => 'flexible_content',
						'layouts' => array(
							array(
								'name'       => 'callout',
								'label'      => 'Callout',
								'type'       => 'group',
								'layout'     => 'block',
								'sub_fields' => array(
									array(
										'name'  => 'title',
										'label' => 'Title',
										'type'  => 'text',
									),
									array(
										'name'  => 'cta',
										'label' => 'CTA',
										'type'  => 'link',
									),
								),
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'template',
							'operator' => '==',
							'value'    => 'flex_components',
						),
					),
				),
			)
		);
	}

	/**
	 * Runs after each test
	 */
	public function tearDown(): void {
		$uploads_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress/wp-content/uploads';
		// Clears uploads so they don't increment.
		exec( "rm -rf $uploads_dir/*" );
	}

	/**
	 * Test default page serialization
	 */
	public function test_flexible_content_serialization() {
		$content    = array(
			'post_title' => 'Title',
			'meta_input' => array(
				'_wp_page_template' => 'flex_components',
			),
		);
		$components = array(
			array(
				'title'         => 'Test Title',
				'cta'           => null,
				'acf_fc_layout' => 'callout',
			),
		);
		$page       = $this->factory->post->create_and_get( $content );
		update_field( 'field_flex_components_components', $components, $page->ID );
		$result = Serializers\page_data( $page );

		$this->assertEquals( $result['post_title'], 'Title' );
		$this->assertEquals( $result['components'][0]['title'], 'Test Title' );
		$this->assertEquals( $result['components'][0]['cta'], array() );
	}

}
