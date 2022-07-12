<?php

namespace Drupal\helfi_gredi_image;

/**
 * Interface GredidamInterface.
 *
 * Defines the Gredi dam interface.
 */
interface GredidamInterface {

  /**
   * Get customer content from API call.
   *
   * @param int $customer
   *   Customer id.
   * @param array $params
   *   Get params.
   *
   * @return array
   *   Return the customer content.
   */
  public function getCustomerContent(int $customer, array $params = []);

  /**
   * Get folder content from API call.
   *
   * @param int $folder_id
   *   Folder id.
   * @param array $params
   *   Folder parameters.
   *
   * @return array|mixed
   *   Return folder content.
   */
  public function getFolderContent(int $folder_id, array $params = []);

  /**
   * Get multiple assets from API call.
   *
   * @param int $ids
   *   Assets id.
   * @param array $expand
   *   Parameters for include in API call.
   *
   * @return array|mixed
   *   Return multiple assets.
   */
  public function getMultipleAsset($ids, array $expand = []);

  /**
   * Get asset from API call.
   *
   * @param int $assetId
   *   Asset id.
   * @param array $expands
   *   Parameters for include in API call.
   *
   * @return array|mixed
   *   Return asset.
   */
  public function getAsset($assetId, array $expands = ['meta', 'attachments']);

}
