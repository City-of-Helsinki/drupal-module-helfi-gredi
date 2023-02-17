<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\FunctionalJavascript;

use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
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

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
    'views_remote_data',
    'helfi_gredi',
    'helfi_gredi_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();
    $this->container->get('config.installer')->installDefaultConfig('module', 'helfi_gredi');

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
          'gredi_asset',
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
            'gredi_asset',
          ],
        ],
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

    // Open media library and assert the media items are visible.
    $this->click('#edit-media-field-open-button');
    $this->assertSession()->waitForElement('css', '#media-library-widget');
    $this->assertSession()->pageTextContains('Add or select media');
    $this->assertSession()->pageTextContains('test1.jpg');
    $this->assertSession()->pageTextContains('test2.png');
    $this->assertSession()->pageTextContains('test3.jpg');
    $this->assertSession()->pageTextContains('test4.jpg');

    // Select a media.
    $this->click('input[type="checkbox"][name="media_library_select_form[1]"]');
    $this->assertSession()->checkboxChecked('media_library_select_form[1]');
    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Insert selected');

    // Assert the media was selected.
    $this->assertSession()->waitForElement('css', 'media-library-item__preview-wrapper');
    $this->assertSession()->pageTextContains('test2.png');

    // Assert the media was created.
    $this->drupalGet('/admin/content/media');
    $this->assertSession()->pageTextContains('test2.png');

  }

}
