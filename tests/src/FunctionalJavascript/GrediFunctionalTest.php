<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\FunctionalJavascript;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\helfi_gredi\GrediClient;
use Drupal\media\Entity\Media;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\helfi_gredi\Traits\GrediClientTestTrait;
use Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase;
use PHPUnit\Runner\BaseTestRunner;

/**
 * Tests media library integration.
 *
 * Helpers copied from the media_library web driver test base class.
 *
 * @group helfi_gredi
 *
 * @see \Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase
 * @see \Drupal\Tests\media_library\FunctionalJavascript\CKEditorIntegrationTest
 */
class GrediFunctionalTest extends MediaLibraryTestBase {

  use EntityReferenceTestTrait;
  use GrediClientTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_ui',
    'node',
    'media',
    'media_library',
    'field_ui',
    'block',
    'helfi_gredi',
    'views_remote_data',
    // Install dblog to assist with debugging.
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('config.installer')->installDefaultConfig('module', 'helfi_gredi');

    $this->createServiceMocks();

    $this->drupalCreateContentType(['type' => 'page']);

    $this->createEntityReferenceField(
      'node',
      'page',
      'media_field',
      'A Media Field',
      'media',
      'default',
      [
        'target_bundles' => [
          'image',
          'gredi_asset'
        ],
      ],
      -1);
    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent('media_field', [
        'type' => 'media_library_widget',
        'region' => 'content',
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('media_field', [
        'type' => 'entity_reference_entity_view',
      ])
      ->save();

//    $grediClient = new GrediClient(
//      $this->guzzleClientMock,
//      $this->configFactoryMock,
//      $this->authServiceMock,
//      $this->loggerChannelFactoryMock,
//      $this->cacheBin
//    );
//    $this->setApiResponse('1');

//    $mock = $this->getMockBuilder(GrediClient::class)
//      ->disableOriginalConstructor()
//      ->getMock();
//
//    // Set the return value of the getAssetData() method to fixture data, since we don't want it to be called.
//    $mock->method('getAssetData')
//      ->willReturn(Json::decode($this->getAssetFixture('1')));
//
//    $this->container->set('helfi_gredi.client', $mock);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $status = $this->getStatus();
    if ($status === BaseTestRunner::STATUS_ERROR || $status === BaseTestRunner::STATUS_WARNING || $status === BaseTestRunner::STATUS_FAILURE) {
      $log = \Drupal::database()
        ->select('watchdog', 'w')
        ->fields('w')
        ->execute()
        ->fetchAll();
      throw new \RuntimeException(var_export($log, TRUE));
    }
    parent::tearDown();
  }

  /**
   * Test media library integration with gredi module.
   *
   * @return void
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEditorMediaLibrary(): void {
    $user = $this->drupalCreateUser([
      'access media overview',
      'create page content',
      'edit any page content',
      'access content',
      'view media',
      'create media',
      'administer node form display',
    ]);
    $this->drupalLogin($user);


    $this->drupalGet('/node/add/page');

    $this->drupalGet('/node/add/page');
    $this->assertSession()->responseContains('Create page');

    $this->assertElementExistsAfterWait('css', "#media_field-media-library-wrapper.js-media-library-widget")
      ->pressButton('Add media');

//    $this->click('#edit-media-field-open-button');
//
//    $this->assertSession()->waitForElement('css', '#media-library-wrapper');
//    $this->assertSession()->assertWaitOnAjaxRequest();
//    $this->assertSession()->waitForElementVisible('css', '#media-library-wrapper');


//    $modal = $this->assertSession()->waitForElement('css', '#media-library-wrapper');
//    dump($modal);

//    $tabs = $this->getSession()
//      ->getPage();

//    self::assertSame([
//      'Gredi Image',
//      'Image',
//    ], array_map(static function (NodeElement $element) {
//      return $element->getText();
//    }, $tabs));

  }

}
