<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\helfi_gredi\GrediAuthService;
use Drupal\helfi_gredi\GrediClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;


/**
 * @group helfi_gredi
 * @coversDefaultClass \Drupal\helfi_gredi\GrediClient
 */
final class GrediClientTest extends UnitTestCase {

  /**
   * Tests if the data is completely fetched.
   *
   * @covers ::getAssetData
   */
  public function testGetAssetData(): void {

    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/responseGredi_14378736.json');

    $client = $this->getMockBuilder(GrediClient::class)
      ->disableOriginalConstructor()
      ->getMock();

    $auth = $this->getMockBuilder(GrediAuthService::class)
      ->disableOriginalConstructor()
      ->getMock();

    $mock = $this->getMockBuilder(Response::class)
      ->disableOriginalConstructor()
      ->getMock();

    $auth->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(true);
    $auth->expects($this->never())
      ->method('authenticate')
      ->willReturn(true);

    $mock->expects($this->any())
      ->method('getStatusCode')
      ->will($this->returnValue(200));

    $mock->expects($this->any())
      ->method('getReasonPhrase')
      ->will($this->returnValue('OK'));

    $mock->expects($this->any())
      ->method('getHeader')
      ->with('Content-Type')
      ->will($this->returnValue(['application/json']));

    $mock->expects($this->any())
      ->method('getBody')
      ->willReturn(Utils::streamFor($mock_data));

    $client->expects($this->once())
      ->method('apiCallGet')
      ->with('https://api4.materialbank.net/api/v1/files/14378736/', [])
      ->willReturn($mock);

    $client->expects($this->once())
      ->method('getAssetData')
      ->with('14378736');

    $remote_data = $client->getAssetData('14378736');

    $this->assertEquals(Json::decode($mock_data), $remote_data);
  }

}
