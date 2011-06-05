<?php
require_once 'PHPUnit/Autoload.php';

App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('NewUploadBehavior', 'Model/Behavior');

define('FILES', dirname(dirname(dirname(dirname(__FILE__)))) . DS . 'files' . DS);

class Image extends AppModel {
	public $name = 'Image';
	public $useDbConfig = 'test';
}

class NewUploadTestCase extends CakeTestCase {
/**
 * Image property.
 *
 * @access public
 * @var Image
 */
	public $Image;

/**
 * Set up the test.
 *
 * @return void
 * @see CakeTestCase::setUp()
 */
	public function setUp() {
		$this->Image = new Image();
	}

/**
 * tearDown method.
 *
 * @return void
 * @see CakeTestCase::tearDown()
 */
	public function tearDown() {
		ClassRegistry::flush();
		unset($this->Image);
	}

/**
 * Test setup of NewUploadBehavior with an field wich not exists.
 *
 * @return void
 */
	public function testSetupFieldNotExists() {
		try {
			$this->Image->Behaviors->load('NewUpload.NewUpload', array('field_not_exists'));
		} catch (FieldNotExistsNewUploadException $e) {
			$this->assertEqual('The field "field_not_exists" doesn\'t exists in the model "Image".', $e->getMessage());
		}
	}

/**
 * Test setup of NewUploadBehavior with an field wich exists.
 *
 * @return void
 */
	public function testSetupFieldExists() {
		$this->Image->Behaviors->load('NewUpload.NewUpload', array('image'));
		$this->assertEqual(array(
			'Image' => array(
				'image' => array(
					'dir' => WWW_ROOT . 'files' . DS . 'image',
					'adjustFilename' => 'fix',
					'fields' => array(
						'dir' => 'dir',
						'mimetype' => 'mimetype',
						'size' => 'size',
					),
				),
			),
		), $this->Image->Behaviors->NewUpload->config);
		$data = array(
			'dir' => '',
			'mimetype' => '',
			'size' => '',
			'image' => array(
				'name' => 'image.jpg',
				'type' => mime_content_type(FILES . 'image.jpg'),
				'tmp_name' => FILES . 'image.jpg',
				'error' => 0,
				'size' => filesize(FILES . 'image.jpg'),
			),
		);
		$this->Image->create($data);
		$this->assertNotEqual(false, $this->Image->save());
	}
}