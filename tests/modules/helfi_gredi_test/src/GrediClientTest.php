<?php

namespace Drupal\helfi_gredi_test;

use Drupal\Component\Serialization\Json;
use Drupal\helfi_gredi\GrediClient;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GrediClientTest.
 *
 * Mocked class of GrediClient.
 */
class GrediClientTest extends GrediClient {

  /**
   * {@inheritdoc}
   */
  public function apiCallGet($apiUri, $queryParams = []) : ResponseInterface {

    return new Response(200, ['Content-Type' => 'application/json'],
      file_get_contents(__DIR__ . sprintf('/../../../fixtures/assetData_%s.json', 'all')));
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetData(string $id): array | NULL {
    return Json::decode(file_get_contents(__DIR__ . sprintf('/../../../fixtures/assetData_%s.json', 'all')));
  }

  /**
   * {@inheritdoc}
   */
  public function getFileContent($assetId, $downloadUrl) : FALSE|string {
    return file_get_contents(__DIR__ . sprintf('/../../../fixtures/assetData_%s.json', 'all'));
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaFields(): array {
    return Json::decode(file_get_contents(__DIR__ . sprintf('/../../../fixtures/assetData_%s.json', 'metafields')));
  }

  /**
   * {@inheritdoc}
   */
  public function searchAssets($search = '', $sortBy = '', $sortOrder = '', $limit = 10, $offset = 0): array {
    $items = Json::decode(
      file_get_contents(__DIR__ . sprintf('/../../../fixtures/assetData_%s.json', 'all'))
    );
    $result = [];
    foreach ($items as $item) {
      $asset = (array) $item;
      $result[] = $asset;
    }

    return $result;
  }

}
