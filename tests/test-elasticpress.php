<?php
/**
 * Class ElasticPressTest
 *
 * @package Elastic_Press
 */

 /**
  * Test for ElasticSearch client class
  */
class ElasticPressTest extends WP_UnitTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp(); // Aha!
	}

	/**
	 * Test client instance
	 */
	public function test_config() {
		$pwd = getcwd();
		$this->assertEquals(
			ElasticPress\Utils\Config::$files['frontend']['fields.json'],
			array(
				array(
					'name'   => 'SomeComponent',
					'path'   => "$pwd/tests/frontend/components/SomeComponent/fields.json",
					'config' => array(
						'globalOptions' => array(
							array(
								'name'  => 'some_field',
								'label' => 'Some Field',
								'type'  => 'text',
							),
						),
					),
				),
			)
		);
		$this->assertEquals(
			ElasticPress\Utils\Config::$files['frontend']['fields.php'],
			array(
				array(
					'name' => 'AnotherComponent',
					'path' => "$pwd/tests/frontend/components/AnotherComponent/fields.php",
				),
			)
		);
		$this->assertEquals(
			ElasticPress\Utils\Config::$files['cms']['functions.php'],
			array(
				array(
					'name' => 'SomeFeature',
					'path' => "$pwd/tests/cms/features/SomeFeature/functions.php",
				),
			)
		);
	}

}
