<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_gredi\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\helfi_gredi\GrediClient;
use Drupal\Tests\UnitTestCase;


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

    $mock_data = Json::decode(file_get_contents(__DIR__ . '/../../fixtures/responseGredi_14378736.json'));

    $client = $this->createMock(GrediClient::class);
    $client->expects($this->once())
      ->method('apiCallGet')
      ->with(
        'https://api4.materialbank.net/api/v1/files/14378736/'
      )->willReturn(new \GuzzleHttp\Psr7\Response());

    $response = $client->apiCallGet('https://api4.materialbank.net/api/v1/files/14378736/');

    $client->expects($this->once())
      ->method('getAssetData')
      ->with(
        '14378736'
      )->willReturn($mock_data);

    $remote_data = $client->getAssetData('14378736');

    $this->assertEquals($mock_data, $remote_data);
  }

}
