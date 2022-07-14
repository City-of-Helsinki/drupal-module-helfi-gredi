<?php

namespace Drupal\Tests\helfi_gredi_image\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Plugin\EntityBrowser\Widget\Gredidam;
use Drupal\media\Entity\Media;
use Drupal\Tests\UnitTestCase;



/**
 * Tests for Gredidam widget for helfi_gredi_image
 *
 * @group helfi_gredi_image
 */
class GredidamUnitTest extends UnitTestCase
{
  /**
   * Data provider for testPrepareEntities().
   */
  public function provideTestPrepareEntities() {
    return JSON::encode([
      'id' => '13584702',
      "parentId" => "5316423",
      "created" => "2022-06-28T11:28:28Z",
      "modified" => "2022-06-28T11:28:29Z",
      "location" => "material",
      "type" => "material",
      "fileType" => "file",
      "mimeGroup" => "picture",
      "mimeType" => "image/jpeg",
      "entryType" => "concrete",
      "archive" => false,
      "apiLink" => "/api/v1/files/13584702",
      "apiContentLink" => "/api/v1/files/13584702/contents/original",
      "name" => "DSC00718_William_Velmala.JPG",
      "language" => "fi",
      "score" => 0.5,
      "indicateSynkka" => false,
      "hasPublicSharingValidityPeriod" => false,
      "folder" => false,
    ]);
  }

  /**
   * Check if the prepareEntities method creates entities.
   *
   * @dataProvider provideTestPrepareEntities
   *
   * @return void
   */
  public function testPrepareEntities() {

    $asset = Asset::fromJSON($this->provideTestPrepareEntities());

    // Create mock for Gredidam constructor.
    $gredidam = $this->getMockBuilder(Gredidam::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Create reflection for protected function prepareEntities.
    $ref_add = new \ReflectionMethod($gredidam, 'prepareEntities');
    $ref_add->setAccessible(TRUE);

    // Create form_state by asset id.
    $form_state = (new FormState())->setUserInput(['assets' => [$asset->id]]);

    $image_name = $asset->name . '.jpg';
    $image_uri = 'public://gredidam/' . $image_name;

    $file = File::create([
      'uid' => 1,
      'filename' => $image_name,
      'uri' => $image_uri,
      'status' => 1,
    ]);
    $file->save();

    $entity = Media::create([
      'bundle' => 'gredi_dam_assets',
      'uid' => 1,
      'langcode' => 'en',
      // @todo Find out if we can use status from Gredi Dam.
      'status' => 1,
      'name' => $asset->name,
      'field_media_image' => [
        'target_id' => $file->id(),
      ],

      'field_external_id' => [
        'asset_id' => $asset->external_id,
      ],

      'created' => strtotime($asset->created),
      'changed' => strtotime($asset->modified),
    ]);

    $entity->save();

    $this->assertEquals([$entity], $ref_add->invokeArgs($gredidam, [[],$form_state]));
  }

  /**
   * @return void
   */
  public function tearDown() {
    parent::tearDown();
  }

}
