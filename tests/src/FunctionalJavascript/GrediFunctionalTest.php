<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
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
    // Install dblog to assist with debugging.
    'dblog',
    'helfi_gredi',
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
        'settings' => [
          'media_types' => [
            'image',
            'gredi_asset',
          ]
        ]
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('media_field', [
        'type' => 'entity_reference_entity_view',
      ])
      ->save();
  }

//  /**
//   * {@inheritdoc}
//   */
//  protected function tearDown(): void {
//    $status = $this->getStatus();
//
//    if ($status === BaseTestRunner::STATUS_ERROR || $status === BaseTestRunner::STATUS_WARNING || $status === BaseTestRunner::STATUS_FAILURE) {
//      $log = \Drupal::database()
//        ->select('watchdog', 'w')
//        ->fields('w')
//        ->execute()
//        ->fetchAll();
//      throw new \RuntimeException(var_export($log, TRUE));
//    }
//    parent::tearDown();
//  }

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
    $this->assertSession()->responseContains('Create page');

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    $this->container->set('helfi_gredi.dam_client', $grediClient);

    $this->setApiResponse('1');

    $this->click('#edit-media-field-open-button');

    $modal = $this->assertSession()->waitForElement('css', '#media-library-widget');

//    $tabs = $this->getSession()
//      ->getPage()
//      ->findAll('css', '.media-library-menu__link');
//    self::assertSame([
//      'Gredi Image',
//      'Image',
//    ], array_map(static function (NodeElement $element) {
//      return $element->getText();
//    }, $tabs));

  }

}
