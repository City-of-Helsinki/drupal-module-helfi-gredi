<?php

namespace Drupal\Tests\helfi_gredi_image\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepository;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Plugin\EntityBrowser\Widget\Gredidam;
use Drupal\helfi_gredi_image\Service\CreateTestSetUp;
use Drupal\media\Entity\Media;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;


/**
 * Tests for Gredidam widget for helfi_gredi_image
 *
 * @group helfi_gredi_image
 */
class GredidamUnitTest extends UnitTestCase
{
  /**
   * @var \Drupal\helfi_gredi_image\Service\CreateTestSetUp
   */
  protected $sut; //subject under test.

  /**
   * @return void
   */
  public function setUp()
  {
    //My subject under test.
    $this->sut = new CreateTestSetUp();

    // Mocking the services.
    $entity_manager = $this->prophesize(EntityTypeManagerInterface::class);
   // $entity_repository = $this->prophesize(EntityTypeRepository::class);
    $entity_storage =  $this->prophesize(EntityStorageInterface::class);

    // Doing some magic.
    $entity_storage->create(Argument::any())->will(function($args, $mock){
      return $args;
    });

    $entity_manager->getStorage('media')->willReturn($entity_storage);
    //$entity_repository->getEntityTypeFromClass(Media::class)->willReturn('media');
    $entity_manager->getEntityTypeFromClass(Media::class)->willReturn('media');

     // Putting the mocked services in the Drupal service container.
    $container = new ContainerBuilder();
    $container->set('entity.manager', $entity_manager->reveal());
    \Drupal::setContainer($container);
}

  /**
   * Check if the prepareEntities method creates media entity.
   *
   * @return void
   */
  public function testPrepareEntities() {

    $asset = json_encode([
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

//    // Create mock for Gredidam constructor.
//    $gredidam_mock = $this->getMockBuilder(Gredidam::class)
//      ->disableOriginalConstructor()
//      ->getMock();

//    // Create reflection for protected function prepareEntities.
//    $ref_add = new \ReflectionMethod($gredidam_mock, 'prepareEntities');
//    $ref_add->setAccessible(TRUE);

    // Create form_state by asset id.
  //  $form_state = (new FormState())->setUserInput(['assets' => [$asset->id]]);

    $result = $this->sut->setMedia($asset);


    $this->assertInstanceOf('\Drupal\media\Entity\Media', $result);
//    $this->expectOutputString('');
//    var_dump($result);
   // $this->assertEquals($entity, $ref_add->invokeArgs($gredidam, [[],$form_state]));
  }

  /**
   * @return void
   */
  public function tearDown() {
    parent::tearDown();
  }

}
