<?php

namespace Drupal\helfi_gredi_image;

use Drupal\helfi_gredi_image\Entity\Asset;

/**
 * Gredi DAM client interface.
 */
interface GrediDamClientInterface {

  /**
   * Get folders and assets from Customer id.
   *
   * @param array $params
   *   Parameters.
   *
   * @return array
   *   Customer content.
   */
  public function getCustomerContent(array $params = []): array;

  /**
   * Get assets and sub-folders from folders.
   *
   * @param int $folder_id
   *   Folder ID.
   * @param array $params
   *   Parameters.
   *
   * @return array|null
   *   Content.
   */
  public function getFolderContent(int $folder_id, array $params = []): ?array;

  /**
   * Get a list of Assets given an array of Asset ID's.
   *
   * @param array $ids
   *   The Gredi DAM Asset ID's.
   * @param array $expand
   *   A list of dta items to expand on the result set.
   *
   * @return array
   *   A list of assets.
   */
  public function getMultipleAsset(array $ids, array $expand = []): array;

  /**
   * Get an Asset given an Asset ID.
   *
   * @param string $id
   *   The Gredi DAM Asset ID.
   * @param array $expands
   *   The additional properties to be included.
   * @param string|null $folder_id
   *   Folder id.
   *
   * @return \Drupal\helfi_gredi_image\Entity\Asset
   *   The asset entity.
   */
  public function getAsset(string $id, array $expands = [], string $folder_id = NULL): Asset;

  /**
   * Get a list of metadata.
   *
   * @return array
   *   A list of metadata fields.
   */
  public function getSpecificMetadataFields(): array;

}
