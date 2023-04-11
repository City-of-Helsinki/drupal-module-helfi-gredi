<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Traits;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\helfi_gredi\GrediAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Provides shared functionality for GrediClient tests.
 */
trait GrediClientTestTrait {

  /**
   * Gredi authentication service.
   *
   * @var \Drupal\helfi_gredi\GrediAuthService
   */
  protected $authServiceMock;

  /**
   * GuzzleClient service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $guzzleClientMock;

  /**
   * ConfigFactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactoryMock;

  /**
   * LoggerChannel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerChannelFactoryMock;

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBin;

  /**
   * Type manager interface service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $typeManagerMock;

  /**
   * Mocks the constructor services.
   */
  public function createServiceMocks() {

    $url = 'https://api4.materialbank.net/api/v1/';

    // Mocking the constructor services.
    $this->authServiceMock = $this->createMock(GrediAuthService::class);
    $this->guzzleClientMock = $this->createMock(Client::class);
    $this->configFactoryMock = $this->createMock(ConfigFactory::class);
    $this->loggerChannelFactoryMock = $this->createMock(LoggerChannelFactory::class);
    $this->cacheBin = $this->createMock(CacheBackendInterface::class);
    $this->typeManagerMock = $this->createMock(EntityTypeManagerInterface::class);

    $this->loggerChannelFactoryMock->method('get')->willReturn(new LoggerChannel('helfi_gredi'));

    $this->authServiceMock->apiUrl = $url;

    $this->authServiceMock
      ->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    $this->authServiceMock
      ->expects($this->any())
      ->method('authenticate')
      ->willReturn(TRUE);
  }

  /**
   * Creates an API response.
   *
   * @param string|array $response_data
   *   The response data.
   *   Could be a string representing the id of a stored fixture.
   *   Or an array with data to be set for the response.
   */
  public function setApiResponse($response_data) {
    if (is_string($response_data)) {
      $mock_data = $this->getAssetFixture($response_data);
      $this->guzzleClientMock
        ->expects($this->any())
        ->method('__call')
        ->willReturn(new Response(200, ['Content-Type' => 'application/json'], $mock_data));
    }
    else {
      $mock_data = Json::encode($response_data);
      $this->guzzleClientMock
        ->expects($this->any())
        ->method('__call')
        ->willReturn(new Response(200, ['Content-Type' => 'application/json'], $mock_data));
    }

  }

  /**
   * Return a specific asset fixture.
   *
   * @param string $id
   *   The id of the asset data fixture.
   *
   * @return false|string
   *   The asset data or false if not found.
   */
  public function getAssetFixture($id) {
    return file_get_contents(__DIR__ . sprintf('/../../fixtures/assetData_%s.json', $id));
  }

  /**
   * Method that searches in fixtures data.
   *
   * @param string $search
   *   The search by value.
   *
   * @return array
   *   Returns an array of fixtures.
   */
  public function getSearchFixture($search = '') {
    $fixtures = array_diff(scandir(__DIR__ . '/../../fixtures'), ['.', '..']);
    $result = [];
    foreach ($fixtures as $fixture) {
      $id = str_replace(['assetData_', '.json'], '', $fixture);
      if (intval($id)) {
        $assetData = Json::decode($this->getAssetFixture($id));
        if (str_contains($assetData['name'], $search)) {
          $result[] = $assetData;
        }
      }
    }
    $this->setApiResponse($result);
    return $result;
  }

}
