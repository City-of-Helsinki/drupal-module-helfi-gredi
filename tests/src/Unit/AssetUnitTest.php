<?php

namespace Drupal\Tests\helfi_gredi_image\Unit;

use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for helfi_gredi_image Entity/Asset.
 *
 * @group helfi_gredi_image
 */
class AssetUnitTest extends UnitTestCase {

  /**
   * Set up for fromJson testing.
   *
   * @return void
   */
  public function setUp():void {
    parent::setUp();

    $config_map = [
      'gredi_dam.settings' => [
        'domain' => 'https://api4.materialbank.net/api/v1/sessions',
      ],
    ];

    // Get a stub for the config.factory service.
    $config_factory = $this->getConfigFactoryStub($config_map);
    $date_formatter = $this->getMockBuilder('Drupal\Core\Datetime\DateFormatter')
      ->disableOriginalConstructor()
      ->getMock();
    $methods = get_class_methods('Drupal\helfi_gredi_image\Service\GrediDamClient');
    $dam_client = $this->getMockBuilder('Drupal\helfi_gredi_image\Service\GrediDamClient')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();

    $container = new ContainerBuilder();
    // Set the config.factory in the container also.
    $container->set('config.factory', $config_factory);
    $container->set('date.formatter', $date_formatter);
    $container->set('helfi_gredi_image.dam_client', $dam_client);

    \Drupal::setContainer($container);
  }

  /**
   * Unit test for FromJson method.
   *
   * @return void
   */
  public function testFromJson() {
    $json = json_encode([
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
      "attachments" => [
        [
          "propertiesById" => [
            "nibo:image-width" => "1024",
            "nibo:image-height" => "768",
          ],
        ],
      ],
    ]);

    //Create new Asset.
    $asset = new Asset();

    // Create Asset with the json input
    $result = $asset->fromJson($json);

    // Assert if the returned object is of type Asset.
    $this->assertInstanceOf('\Drupal\helfi_gredi_image\Entity\Asset', $result, 'Object is of type Asset!');

    $metadataHelperService = new AssetMetadataHelper(
      \Drupal::service('date.formatter'),
      \Drupal::service('helfi_gredi_image.dam_client')
    );

    // Assertions for all return cases from AssetMetadataHelper.
    $this->assertEquals('1024', $metadataHelperService->getMetadataFromAsset($result, 'width'));
    $this->assertEquals('768', $metadataHelperService->getMetadataFromAsset($result, 'height'));
    // Resolution not available in the created asset => assertion fail.
    $this->assertEquals('768', $metadataHelperService->getMetadataFromAsset($result, 'resolution'));
    $this->assertEquals(NULL, $metadataHelperService->getMetadataFromAsset($result, 'keywords'));
    $this->assertEquals(NULL, $metadataHelperService->getMetadataFromAsset($result, 'alt_text'));
    $this->assertEquals('2982301', $metadataHelperService->getMetadataFromAsset($result, 'size'));
    // Asset creates two ids: external_id and id => must be verified if needed both.
    // For input 'id' assertion success.
    $this->assertEquals('13584702', $metadataHelperService->getMetadataFromAsset($result, 'id'));
    // For input 'external_id' assertion fail.
    $this->assertEquals('13584702', $metadataHelperService->getMetadataFromAsset($result, 'external_id'));

    $this->assertEquals('DSC00718_William_Velmala.JPG', $metadataHelperService->getMetadataFromAsset($result, 'name'));

    // Dummy property must return NULL and shall not be created at all.
    $this->assertEquals(NULL, $metadataHelperService->getMetadataFromAsset($result,'dummy'));
  }

}
