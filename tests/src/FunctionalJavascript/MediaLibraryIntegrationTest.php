<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
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
class MediaLibraryIntegrationTest extends WebDriverTestBase {
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_ui',
    'node',
    'media',
    'media_library',
    'field_ui',
    // Install dblog to assist with debugging.
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
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
    ]);

    $this->drupalLogin($user);

    $this->drupalGet('/node/add/page');
    $this->assertSession()->responseContains('Create page');

    $this->click('#edit-media-field-open-button');

    $modal = $this->assertSession()->waitForElement('css', '#media-library-modal');

    $tabs = $this->getSession()
      ->getPage()
      ->findAll('css', '.media-library-menu__link');
    self::assertSame([
      'Gredi Image',
      'Image',
    ], array_map(static function (NodeElement $element) {
      return $element->getText();
    }, $tabs));

  }

}
