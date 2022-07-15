<?php

namespace Drupal\Tests\helfi_gredi_image\Unit;

use Drupal\helfi_gredi_image\Entity\Asset;
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
  public function setUp() {

    parent::setUp();

    $config_map = [
      'gredi_dam.settings' => [
        'domain' => 'https://api4.materialbank.net/api/v1/sessions',
      ],
    ];

    // Get a stub for the config.factory service.
    $this->config_factory = $this->getConfigFactoryStub($config_map);

    $container = new ContainerBuilder();
    // Set the config.factory in the container also.
    $container->set('config.factory', $this->config_factory);

    \Drupal::setContainer($container);
  }

  /**
   * Unit test for FromJson method.
   *
   * @return void
   */
  public function testFromJson(){

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
    ]);

    //Create new Asset.
    $asset = new Asset();

    // Create Asset with the json input
    $result = $asset->fromJson($json);

    // Transform the result to string to be able to compare.
    $res_equals = json_encode($result);


    // Assert if the returned object is of type Asset
    $this->assertInstanceOf('\Drupal\helfi_gredi_image\Entity\Asset', $result, 'Object is of type Asset!');
    // Assert if the properties from the Asset are identical with the JSON input === FAILING CASE
    $this->assertEquals($res_equals, $json);
  }

}
