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
class AssetUnitTest extends UnitTestCase
{
  /**
   * Set up testing services.
   */
    public function setUp(): void
    {
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
   * Unit test for fromJson method.
   */
    public function testFromJson()
    {
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
            "id" => "original",
            "type" => "original",
            "profileUsage" => 0,
            "publicTicket" => "1615665b1ff88abc6803030601a48318",
            "publicLink" => "/NiboWEB/helsinki/getPublicFile.do?uuid=13584702&inline=false&ticket=1615665b1ff88abc6803030601a48318&type=original",
            "namesByLang" => [
              "fi" => "Originaali",
              "en" => "Original",
            ],
            "propertiesById" => [
              "nibo:image-resolution-x" => "100",
              "nibo:image-width" => "6000",
              "nibo:image-resolution-y" => "100",
              "nibo:image-height-pts" => "2880.0",
              "nibo:name" => "DSC00718_William_Velmala.JPG",
              "nibo:image-units" => "Undefined",
              "nibo:image-colorspace" => "RGB",
              "nibo:image-width-pts" => "4320.0",
              "nibo:image-height" => "4000",
              "nibo:image-height-px" => "4000",
              "nibo:image-resolution" => "100x100",
              "nibo:image-width-inches" => "60.0",
              "nibo:image-height-inches" => "40.0",
              "nibo:file-size" => "2982301",
              "nibo:image-depth" => "8-bit",
              "nibo:image-type" => "TrueColor",
              "nibo:image-orientation" => "Undefined",
              "nibo:image-class" => "DirectClass",
              "nibo:mime-type" => "image/jpeg",
              "nibo:image-format" => "JPEG (Joint Photographic Experts Group JFIF format)",
              "nibo:image-height-mm" => "1016.0",
              "nibo:image-width-px" => "6000",
              "nibo:image-width-mm" => "1524.0",
            ],
          ],
          ],
          ]);

        // Create new Asset.
        $asset = new Asset();

        // Create Asset with the json input.
        $result = $asset->fromJson($json);

        // Assert if the returned object is of type Asset.
        $this->assertInstanceOf('\Drupal\helfi_gredi_image\Entity\Asset', $result, 'Object is of type Asset!');

        $metadataHelperService = new AssetMetadataHelper(
            \Drupal::service('date.formatter'),
            \Drupal::service('helfi_gredi_image.dam_client')
        );

        // Assertions for all return cases from AssetMetadataHelper.
        $this->assertEquals('6000', $metadataHelperService->getMetadataFromAsset($result, 'width'));
        $this->assertEquals('4000', $metadataHelperService->getMetadataFromAsset($result, 'height'));
        $this->assertEquals('100x100', $metadataHelperService->getMetadataFromAsset($result, 'resolution'));
        $this->assertEquals(null, $metadataHelperService->getMetadataFromAsset($result, 'keywords'));
        $this->assertEquals(null, $metadataHelperService->getMetadataFromAsset($result, 'alt_text'));
        $this->assertEquals('2982301', $metadataHelperService->getMetadataFromAsset($result, 'size'));
        $this->assertEquals('13584702', $metadataHelperService->getMetadataFromAsset($result, 'external_id'));
        $this->assertEquals('DSC00718_William_Velmala.JPG', $metadataHelperService->getMetadataFromAsset($result, 'name'));
        // Dummy property must return NULL and shall not be created at all.
        $this->assertEquals(null, $metadataHelperService->getMetadataFromAsset($result, 'dummy'));
    }
}
