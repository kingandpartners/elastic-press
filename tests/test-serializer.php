<?php
/**
 * Class SerializerTest
 *
 * @package Elastic_Press
 */

use ElasticPress\Serializers;
use ACFComposer\ACFComposer;

/**
 * Test for serializers
 */
class SerializerTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		ACFComposer::registerFieldGroup(
			array(
				'name'     => 'billboard',
				'title'    => 'Billboard',
				'fields'   => array(
					array(
						'name'  => 'title',
						'label' => 'Title',
						'type'  => 'text',
					),
					array(
						'name'  => 'image',
						'label' => 'Image',
						'type'  => 'image',
					),
					array(
						'name'  => 'pdf',
						'label' => 'PDF',
						'type'  => 'file',
					),
					array(
						'name'       => 'bullets',
						'label'      => 'Bullets',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'name'  => 'title',
								'label' => 'Title',
								'type'  => 'text',
							),
							array(
								'name'  => 'description',
								'label' => 'Description',
								'type'  => 'textarea',
							),
						),
					),
					array(
						'name'  => 'post',
						'label' => 'Post',
						'type'  => 'post_object',
					),
					array(
						'name'       => 'featured_group',
						'label'      => 'Featured Group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'name'  => 'title',
								'label' => 'Title',
								'type'  => 'text',
							),
							array(
								'name'  => 'description',
								'label' => 'Description',
								'type'  => 'textarea',
							),
						),
					),
					array(
						'name'  => 'cta',
						'label' => 'CTA',
						'type'  => 'link',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/billboard',
						),
					),
				),
			)
		);
		// Need the editor role to be able to insert taxonomy, etc.
		$admin = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $admin );
	}

	/**
	 * Runs after each test
	 */
	public function tearDown() {
		$uploads_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress/wp-content/uploads';
		// Clears uploads so they don't increment.
		exec( "rm -rf $uploads_dir/*" );
	}

	/**
	 * Test default page serialization
	 */
	public function test_default_page_serialization() {
		$content = array( 'post_title' => 'Some content' );
		$page    = $this->factory->post->create_and_get( $content );
		$result  = Serializers\page_data( $page );

		$this->assertEquals( $result['post_title'], 'Some content' );
	}

	/**
	 * Test default post serialization
	 */
	public function test_default_post_serialization() {
		$taxonomy = 'some-taxonomy';
		$term     = 'some-term';
		register_taxonomy( $taxonomy, 'post' );
		wp_insert_term( $term, $taxonomy );
		$content = array(
			'post_title' => 'Some content',
			'tax_input'  => array( $taxonomy => $term ),
		);
		$post    = $this->factory->post->create_and_get( $content );
		$result  = Serializers\post_data( $post );

		$this->assertEquals( $result['post_title'], 'Some content' );
		$this->assertArraySubset(
			array(
				'name'        => 'some-term',
				'slug'        => 'some-term',
				'taxonomy'    => 'some-taxonomy',
				'count'       => 1,
				'post_name'   => 'some-term',
				'post_status' => 'publish',
			),
			$result['taxonomies'][0]
		);
	}

	/**
	 * Test post Gutenberg serialization
	 */
	public function test_post_gutenberg_serialization() {
		$asset       = __DIR__ . '/assets/test.png';
		$image_id    = $this->factory->attachment->create_upload_object( $asset );
		$pdf         = __DIR__ . '/assets/test.pdf';
		$pdf_id      = $this->factory->attachment->create_upload_object( $pdf );
		$upload_path = $this->upload_path();
		$post        = $this->factory->post->create_and_get( array( 'post_title' => 'Attached Post' ) );
		$post_id     = $post->ID;
		$acf_gut     = <<<ACF_GUT
<!-- wp:acf/billboard {
	"id": "block_5e1f484429e05",
	"name": "acf/billboard",
	"data": {
			"title": "Test Title",
			"_title": "field_billboard_title",
			"image": $image_id,
			"_image": "field_billboard_image",
			"pdf": $pdf_id,
			"_pdf": "field_billboard_pdf",
			"bullets_0_title": "Bullet Title 1",
      "_bullets_0_title": "field_billboard_bullets_title",
			"bullets_0_description": "Bullet Description 1",
			"_bullets_0_description": "field_billboard_bullets_description",
			"bullets_1_title": "Bullet Title 2",
      "_bullets_1_title": "field_billboard_bullets_title",
			"bullets_1_description": "Bullet Description 2",
			"_bullets_1_description": "field_billboard_bullets_description",
			"bullets": 2,
      "_bullets": "field_billboard_bullets",
			"post": $post_id,
			"_post": "field_billboard_post",
			"featured_group_description": "Featured Group Description",
			"_featured_group_description": "field_billboard_featured_group_description",
			"featured_group_title": "Featured Group Title",
      "_featured_group_title": "field_billboard_featured_group_title",
			"featured_group": "",
      "_featured_group": "field_billboard_featured_group",
			"cta": {
		      "title": "CTA Title",
		      "url": "https://example.test/link/",
		      "target": "_blank"
      },
			"_cta": "field_billboard_cta"
	},
	"align": "",
	"mode": "edit"
} /-->
ACF_GUT;
		$content     = array( 'post_content' => $acf_gut );
		$page        = $this->factory->post->create_and_get( $content );
		$result      = Serializers\page_data( $page );

		$result_data = $result['blocks'][0]['attrs']['data'];
		// text.
		$this->assertEquals( $result_data['title'], 'Test Title' );
		// image.
		$this->assertEquals( $result_data['image']['url'], "$upload_path/test.png" );
		$this->assertEquals(
			$result_data['image']['sizes'],
			array(
				'thumbnail'    => "$upload_path/test-150x150.png",
				'medium'       => "$upload_path/test-300x185.png",
				'medium_large' => "$upload_path/test-768x475.png",
				'large'        => "$upload_path/test.png",
				'1536x1536'    => "$upload_path/test.png",
				'2048x2048'    => "$upload_path/test.png",
			)
		);
		$this->assertEquals( $result_data['image']['srcset'], "$upload_path/test-300x185.png 300w, $upload_path/test-768x475.png 768w, $upload_path/test.png 825w" );
		// file.
		$this->assertEquals( $result_data['pdf']['url'], "$upload_path/test.pdf" );
		// repeater.
		$this->assertEquals(
			$result_data['bullets'],
			array(
				array(
					'title'       => 'Bullet Title 1',
					'description' => 'Bullet Description 1',
				),
				array(
					'title'       => 'Bullet Title 2',
					'description' => 'Bullet Description 2',
				),
			)
		);
		// post_object.
		$this->assertEquals( $result_data['post']['post_title'], 'Attached Post' );
		// group.
		$this->assertEquals( $result_data['featured_group']['title'], 'Featured Group Title' );
		$this->assertEquals( $result_data['featured_group']['description'], 'Featured Group Description' );
		// link.
		$this->assertEquals( $result_data['cta']['title'], 'CTA Title' );
		$this->assertEquals( $result_data['cta']['url'], 'https://example.test/link/' );
		$this->assertEquals( $result_data['cta']['target'], '_blank' );
	}

	/**
	 * Test Gutenberg core/image serialization
	 */
	public function test_post_gutenberg_core_image_serialization() {
		$asset       = __DIR__ . '/assets/test.png';
		$image_id    = $this->factory->attachment->create_upload_object( $asset );
		$upload_path = $this->upload_path();
		$core_img    = <<<CORE_IMG
<!-- wp:image {"id":$image_id} -->
<figure class="wp-block-image"><img src="$upload_path/test.jpg" alt="Test alt" class="wp-image-$image_id"/></figure>
<!-- /wp:image -->
CORE_IMG;
		$content     = array( 'post_content' => $core_img );
		$page        = $this->factory->post->create_and_get( $content );
		$result      = Serializers\page_data( $page );

		$result_data = $result['blocks'][0]['attrs']['data'];

		$this->assertEquals( $result_data['url'], "$upload_path/test.png" );
		$this->assertEquals(
			$result_data['sizes'],
			array(
				'thumbnail'    => "$upload_path/test-150x150.png",
				'medium'       => "$upload_path/test-300x185.png",
				'medium_large' => "$upload_path/test-768x475.png",
				'large'        => "$upload_path/test.png",
				'1536x1536'    => "$upload_path/test.png",
				'2048x2048'    => "$upload_path/test.png",
			)
		);
		$this->assertEquals( $result_data['srcset'], "$upload_path/test-300x185.png 300w, $upload_path/test-768x475.png 768w, $upload_path/test.png 825w" );
	}

	/**
	 * Test featured image serialization
	 */
	public function test_featured_image_serialization() {
		$page        = $this->factory->post->create_and_get();
		$asset       = __DIR__ . '/assets/test.png';
		$upload_path = $this->upload_path();
		$image_id    = $this->factory->attachment->create_upload_object( $asset, $page->ID );
		update_post_meta( $page->ID, '_thumbnail_id', $image_id );
		$result = Serializers\page_data( $page );

		$this->assertEquals( $result['featured_image']['image']['url'], "$upload_path/test.png" );
	}

	/**
	 * Test upload_path
	 */
	private function upload_path() {
		$date_prefix = strftime( '%Y/%m', time() );
		return "http://example.org/wp-content/uploads/$date_prefix";
	}

}
