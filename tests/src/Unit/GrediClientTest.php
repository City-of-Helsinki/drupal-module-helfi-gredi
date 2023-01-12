<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\helfi_gredi\GrediAuthService;
use Drupal\helfi_gredi\GrediClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Test class for GrediClient methods.
 *
 * @group helfi_gredi
 * @coversDefaultClass \Drupal\helfi_gredi\GrediClient
 */
final class GrediClientTest extends UnitTestCase {

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

    $id = '14378736';
    $url = 'https://api4.materialbank.net/api/v1/';

    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/responseGredi_14378736.json');
    $mock = new Response(200, ['Content-Type' => 'application/json'], $mock_data);
    $expected_response = Json::decode($mock_data);

    // Mocking the constructor services.
    $authServiceMock = $this->createMock(GrediAuthService::class);
    $guzzleClientMock = $this->createMock(Client::class);
    $configFactoryMock = $this->createMock(ConfigFactory::class);
    $loggerChannelFactoryMock = $this->createMock(LoggerChannelFactory::class);
    $cacheStatic = $this->createMock(CacheBackendInterface::class);

    $loggerChannelFactoryMock->method('get')->willReturn(new LoggerChannel('helfi_gredi'));
    $authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    $authServiceMock->apiUrl = $url;

    $authServiceMock
      ->expects($this->any())
      ->method('authenticate')
      ->willReturn(TRUE);

    // Set the guzzleClient response in order to not make an actual API call.
    $guzzleClientMock->method('__call')->willReturn($mock);

    $grediClient = new GrediClient(
      $guzzleClientMock,
      $configFactoryMock,
      $authServiceMock,
      $loggerChannelFactoryMock,
      $cacheStatic
    );

    // Act.
    $remote_data = $grediClient->getAssetData($id);

    // Assert when authenticated.
    $this->assertEquals($expected_response, $remote_data);

    $authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(FALSE);

    $authServiceMock
      ->expects($this->any())
      ->method('authenticate')
      ->willReturn(FALSE);

    // Act.
    $remote_data = $grediClient->getAssetData($id);

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
    $id = '14378736';
    $url = 'https://api4.materialbank.net/api/v1/';
    $apiPreviewLink = '/api/v1/files/14378736/contents/preview';

    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/responseGredi_14378736.json');
    $mock = new Response(200, ['Content-Type' => 'application/json'], $mock_data);

    // Mocking the constructor services.
    $authServiceMock = $this->createMock(GrediAuthService::class);
    $guzzleClientMock = $this->createMock(Client::class);
    $configFactoryMock = $this->createMock(ConfigFactory::class);
    $loggerChannelFactoryMock = $this->createMock(LoggerChannelFactory::class);
    $cacheStaticMock = $this->createMock(CacheBackendInterface::class);

    $loggerChannelFactoryMock->method('get')->willReturn(new LoggerChannel('helfi_gredi'));
    $authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    $authServiceMock->apiUrl = $url;

    $authServiceMock
      ->expects($this->any())
      ->method('authenticate')
      ->willReturn(TRUE);

    // Set the guzzleClient response in order to not make an actual API call.
    $guzzleClientMock->method('__call')->willReturn($mock);

    $grediClient = new GrediClient(
      $guzzleClientMock,
      $configFactoryMock,
      $authServiceMock,
      $loggerChannelFactoryMock,
      $cacheStaticMock
    );

    // Act.
    $remote_data = $grediClient->getFileContent($id, $apiPreviewLink);

    // Assert when apiPreviewLink is found.
    $this->assertEquals($mock_data, $remote_data);

    $apiPreviewLink = '';

    // Act.
    $remote_data = $grediClient->getFileContent($id, $apiPreviewLink);

    // Assert when apiPreviewLink is missing.
    $this->assertEquals(FALSE, $remote_data);
  }

  /**
   * Tests that metadata fields are completely processed.
   *
   * With metafields cached.
   *
   * @covers \Drupal\helfi_gredi\GrediClient::getMetaFields
   */
  public function testGetMetaFieldsCache() {
    // Mocking the constructor services.
    $authServiceMock = $this->createMock(GrediAuthService::class);
    $guzzleClientMock = $this->createMock(Client::class);
    $configFactoryMock = $this->createMock(ConfigFactory::class);
    $loggerChannelFactoryMock = $this->createMock(LoggerChannelFactory::class);
    $cacheStaticMock = $this->createMock(CacheBackendInterface::class);

    $loggerChannelFactoryMock->method('get')
      ->willReturn(new LoggerChannel('helfi_gredi'));

    $url = 'https://api4.materialbank.net/api/v1/';
    $authServiceMock->apiUrl = $url;

    $grediClient = new GrediClient(
      $guzzleClientMock,
      $configFactoryMock,
      $authServiceMock,
      $loggerChannelFactoryMock,
      $cacheStaticMock
    );

    // Mock the response in order to not make an actual API call.
    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/responseGredi_metafields.json');
    $cacheStaticMock->data = Json::decode($mock_data);
    $cacheStaticMock->method('get')->willReturn($cacheStaticMock);

    // Act.
    $remote_data = $grediClient->getMetaFields();

    // Assert when metafields are already cached.
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

    // Mocking the constructor services.
    $authServiceMock = $this->createMock(GrediAuthService::class);
    $guzzleClientMock = $this->createMock(Client::class);
    $configFactoryMock = $this->createMock(ConfigFactory::class);
    $loggerChannelFactoryMock = $this->createMock(LoggerChannelFactory::class);
    $cacheStaticMock = $this->createMock(CacheBackendInterface::class);

    $loggerChannelFactoryMock->method('get')
      ->willReturn(new LoggerChannel('helfi_gredi'));

    $url = 'https://api4.materialbank.net/api/v1/';
    $authServiceMock->apiUrl = $url;

    $grediClient = new GrediClient(
      $guzzleClientMock,
      $configFactoryMock,
      $authServiceMock,
      $loggerChannelFactoryMock,
      $cacheStaticMock
    );

    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/responseGredi_metafields.json');

    // Set the guzzleClient response in order to not make an actual API call.
    $mock = new Response(200, ['Content-Type' => 'application/json'], $mock_data);
    $guzzleClientMock->method('__call')->willReturn($mock);

    // Clear cache.
    $cacheStaticMock->data = [];
    $cacheStaticMock->method('get')->willReturn($cacheStaticMock);

    $authServiceMock
      ->expects($this->once())
      ->method('getCustomerId')
      ->willReturn('6');

    $authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    // Create an immutable config for the get method.
    $immutableConfigMock = $this->createMock(ImmutableConfig::class);
    $configFactoryMock->method('get')->willReturn($immutableConfigMock);

    // Act.
    $remote_data = $grediClient->getMetaFields();

    $expected_result = Json::decode($mock_data);
    // Assert all fields are processed when metafields are not cached.
    $this->assertEquals(count($expected_result), count($remote_data));

  }

}
