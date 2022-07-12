<?php

namespace Drupal\helfi_gredi_image;

/**
 * Interface GredidamInterface.
 *
 * Defines the Gredi dam interface.
 */
interface GredidamInterface {

  /**
   * Passes method calls through to the DAM client object.
   *
   * @param string $name
   *   The name of the method to call.
   * @param array $arguments
   *   An array of arguments.
   *
   * @return mixed
   *   Returns whatever the dam client returns.
   */
  public function __call($name, array $arguments);

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
  public function getCustomerContent($customer, $params = []);

  /**
   *  Get folder content from API call.
   *
   * @param int $folder_id
   *   Folder id.
   *
   * @param array $params
   *   Folder parameters.
   *
   * @return array|mixed
   *   Return folder content.
   */
  public function getFolderContent($folder_id, $params = []);

  /**
   * Get multiple assets from API call.
   *
   * @param int $ids
   *   Assets id.
   *
   * @param array $expand
   *   Parameters for include in API call.
   *
   * @return array|mixed
   *   Return multiple assets.
   */
  public function getMultipleAsset($ids, $expand = []);

  /**
   * Get asset from API call.
   *
   * @param int $assetId
   *    Asset id.
   *
   * @param array $expands
   *   Parameters for include in API call.
   *
   * @return array|mixed
   *    Return asset.
   */
  public function getAsset($assetId, $expands = ['meta', 'attachments']);

}

