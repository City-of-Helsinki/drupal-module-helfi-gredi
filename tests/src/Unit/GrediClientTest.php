<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
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
   * @covers::apiCallGet
   * @covers::__construct
   * @covers ::getAssetData
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
      $loggerChannelFactoryMock
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

}
