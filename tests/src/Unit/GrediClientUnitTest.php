<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_gredi\GrediClient;
use Drupal\Tests\helfi_gredi\Traits\GrediClientTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Test class for GrediClient methods.
 *
 * @group helfi_gredi
 * @coversDefaultClass \Drupal\helfi_gredi\GrediClient
 */
final class GrediClientUnitTest extends UnitTestCase {

  use GrediClientTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createServiceMocks();
  }

  /**
   * Tests the data is fetched completely.
   *
   * @throws \Exception
   *
   * @covers \Drupal\helfi_gredi\GrediClient::apiCallGet
   * @covers \Drupal\helfi_gredi\GrediClient::__construct
   * @covers \Drupal\helfi_gredi\GrediClient::getAssetData
   */
  public function testGetAssetData(): void {

    $expected_response = Json::decode($this->getAssetFixture('1'));

    // Set the guzzleClient response in order to not make an actual API call.
    $this->setApiResponse('1');

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    // Act.
    $remote_data = $grediClient->getAssetData('1');

    // Assert when authenticated.
    $this->assertEquals($expected_response, $remote_data);

    // Mock to simulate unauthenticated case.
    $this->authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(FALSE);

    $this->authServiceMock
      ->expects($this->any())
      ->method('authenticate')
      ->willReturn(FALSE);

    // Act.
    $remote_data = $grediClient->getAssetData('1');

    // Assert when unauthenticated.
    $this->assertEquals(NULL, $remote_data);
  }

  /**
   * Tests the data is fetched completely.
   *
   * @throws \Exception
   *
   * @covers \Drupal\helfi_gredi\GrediClient::apiCallGet
   * @covers \Drupal\helfi_gredi\GrediClient::__construct
   * @covers \Drupal\helfi_gredi\GrediClient::getAssetData
   * @covers \Drupal\helfi_gredi\GrediClient::getFileContent
   */
  public function testGetFileContent() {
    $id = '1';

    $mock_data = $this->getAssetFixture('1');

    // Set the guzzleClient response in order to not make an actual API call.
    $this->setApiResponse('1');

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    // Assert when apiPreviewLink is found.
    $apiPreviewLink = '/test_path/preview';
    $remote_data = $grediClient->getFileContent($id, $apiPreviewLink);
    $this->assertEquals($mock_data, $remote_data);

    // Assert when apiPreviewLink is missing.
    $apiPreviewLink = '';
    $remote_data = $grediClient->getFileContent($id, $apiPreviewLink);
    $this->assertEquals(FALSE, $remote_data);
  }

  /**
   * Tests that metadata fields are completely processed.
   *
   * With metafields cached.
   *
   * @covers \Drupal\helfi_gredi\GrediClient::getMetaFields
   * @covers \Drupal\helfi_gredi\GrediClient::__construct
   */
  public function testGetMetaFieldsCache() {

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    // Mock cached data.
    $mock_data = $this->getAssetFixture('metafields');
    $this->cacheBin->data = Json::decode($mock_data);
    $this->cacheBin->method('get')->willReturn($this->cacheBin);

    // Assert when metafields are already cached.
    $remote_data = $grediClient->getMetaFields();
    $this->assertEquals(Json::decode($mock_data), $remote_data);
  }

  /**
   * Tests that metadata fields are completely processed.
   *
   * With metafields not cached.
   *
   * @covers \Drupal\helfi_gredi\GrediClient::getMetaFields
   * @covers \Drupal\helfi_gredi\GrediClient::__construct
   * @covers \Drupal\helfi_gredi\GrediClient::apiCallGet
   */
  public function testGetMetaFieldsNoCache() {

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    // Set the guzzleClient response in order to not make an actual API call.
    $this->setApiResponse('metafields');

    // Create an immutable config for the get method.
    $immutableConfigMock = $this->createMock(ImmutableConfig::class);
    $this->configFactoryMock->method('get')->willReturn($immutableConfigMock);

    $mock_data = $this->getAssetFixture('metafields');
    $expected_result = Json::decode($mock_data);
    // Assert all fields are processed when metafields are not cached.
    $remote_data = $grediClient->getMetaFields();

    $this->assertEquals(count($expected_result), count($remote_data));
  }

  /**
   * Tests that search method returns the data completely.
   *
   * @covers \Drupal\helfi_gredi\GrediClient::searchAssets
   * @covers \Drupal\helfi_gredi\GrediClient::__construct
   * @covers \Drupal\helfi_gredi\GrediClient::apiCallGet
   */
  public function testSearchAssets() {

    $grediClient = new GrediClient(
      $this->guzzleClientMock,
      $this->configFactoryMock,
      $this->authServiceMock,
      $this->loggerChannelFactoryMock,
      $this->cacheBin
    );

    $expected_result = $this->getSearchFixture('test');
    $remote_data = $grediClient->searchAssets('test');
    // Assert that we received the expected assets.
    $count = 0;
    foreach ($expected_result as $expected_value) {
      $value_found = in_array($expected_value['id'], $remote_data[$count]);
      $count++;
      $this->assertEquals(TRUE, $value_found);
    }
  }

}
