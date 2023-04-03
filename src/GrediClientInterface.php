<?php

namespace Drupal\helfi_gredi;

use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Gredi DAM client interface.
 */
interface GrediClientInterface {

  /**
   * Search for assets by params.
   *
   * @param array $params
   *   Params var.
   * @param int $limit
   *   Limit.
   * @param int $offset
   *   Offset.
   *
   * @return array
   *   A list of assets.
   */
  public function searchAssets(array $params, $limit, $offset): array;

  /**
   * Upload image to DAM.
   *
   * @param \Drupal\file\Entity\File $image
   *   Image to upload.
   * @param array $inputs
   *   An array of fields values for upload.
   * @param string $method
   *   The request method.
   * @param bool $is_update
   *   If the method is used for syncing assets or for uploading image.
   *
   * @param \Drupal\media\MediaInterface
   *
   * @return string|null
   *   ID of the newly created DAM asset.
   */
  public function uploadImage(array $inputs, MediaInterface $media, bool $is_update): ?string;

  /**
   * Retrieves metadata fields.
   *
   * @return array
   *   Return array of fields.
   */
  public function getMetaFields(): array;

  /**
   * Method that handles the get requests for the API.
   *
   * @param string $apiUri
   *   The external api URI.
   * @param array $queryParams
   *   Additional parameters for the request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Return the request response.
   */
  public function apiCallGet(string $apiUri, array $queryParams = []): ResponseInterface;

  /**
   * Retrieves data from the API for a specific asset.
   *
   * @param string $id
   *   The id for a specific asset.
   *
   * @return array
   *   Return asset data.
   *
   * @throws \Exception
   */
  public function getAssetData(string $id): array|NULL;

  /**
   * Retrieves content of a file from the API.
   *
   * @param string $assetId
   *   The id for a specific asset.
   * @param string $downloadUrl
   *   The url for the request.
   *
   * @return false|string
   *   Return the file content or false if an error occurs.
   *
   * @throws \Exception
   */
  public function getFileContent(string $assetId, string $downloadUrl) : FALSE|string;

}
