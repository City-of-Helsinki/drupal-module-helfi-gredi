<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Traits;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
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
   * @param string $path
   *   The fixture path.
   */
  public function setApiResponse($path) {
    $mock_data = file_get_contents(__DIR__ . $path);

    $this->guzzleClientMock
      ->expects($this->any())
      ->method('__call')
      ->willReturn(new Response(200, ['Content-Type' => 'application/json'], $mock_data));
  }

}
