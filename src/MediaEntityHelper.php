<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\helfi_gredi_image\Service\AssetFileEntityHelper;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class MediaEntityHelper.
 *
 * Functionality related to working with the Media entity that assets are tied
 * to. The intent is to make it easier to test and rework the behavior without
 * having everything in a singular class.
 */
class MediaEntityHelper {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Gredi DAM asset data service.
   *
   * @var \Drupal\helfi_gredi_image\AssetData
   */
  protected $assetData;

  /**
   * Gredi DAM client.
   *
   * @var \Drupal\helfi_gredi_image\GredidamClient
   */
  protected $grediDamClient;

  /**
   * Gredi DAM asset file helper service.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetFileEntityHelper
   */
  protected $assetFileHelper;

  /**
   * The media entity that is being wrapped.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $mediaEntity;

  /**
   * MediaEntityHelper constructor.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to wrap.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\helfi_gredi_image\AssetDataInterface $assetData
   *   Gredi DAM asset data service.
   * @param \Drupal\helfi_gredi_image\GrediDamClient $grediDamClient
   *   Gredi DAM client.
   * @param \Drupal\helfi_gredi_image\Service\AssetFileEntityHelper $assetFileHelper
   *   Gredi DAM file entity helper service.
   */
  public function __construct(MediaInterface $media, EntityTypeManagerInterface $entityTypeManager, AssetDataInterface $assetData, GrediDamClient $grediDamClient, AssetFileEntityHelper $assetFileHelper) {
    $this->entityTypeManager = $entityTypeManager;
    $this->assetData = $assetData;
    $this->grediDamClient = $grediDamClient;
    $this->assetFileHelper = $assetFileHelper;

    $this->mediaEntity = $media;
  }

  /**
   * Returns an associated file or creates a new one.
   *
   * @return false|\Drupal\file\FileInterface
   *   A file entity or FALSE on failure.
   */
  public function getFile($field, $opt) {
    // If there is already a file on the media entity then we should use that.
    $file = $this->getExistingFile($field, $opt);

    // If there is an error fetching the asset, rely on existing file.
    try {
      $asset = $this->getAsset();
    }
    catch (GuzzleException $exception) {
      return $file;
    }

    // If we're getting an updated version of the asset we need to grab a new
    // version of the file.
    if ($asset !== NULL) {
      $is_different_version = $this->assetData->isUpdatedAsset($asset);

      if (empty($file) || $is_different_version) {
        $destination_folder = $this->getAssetFileDestination();
        $file = $this->assetFileHelper->createNewFile($asset, $destination_folder);

        if ($file) {
          $this->assetData->set($asset->external_id, 'file_upload_date', strtotime($asset->file_upload_date));
        }
      }
    }

    return $file;
  }

  /**
   * Attempts to load an existing file entity from the given media entity.
   *
   * @return \Drupal\file\FileInterface|false
   *   A loaded file entity or FALSE if none could be found.
   */
  public function getExistingFile($field, $opt) {
    try {
      if ($fid = $this->getExistingFileId($field, $opt)) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
      }
    }
    catch (\Exception $x) {
      $file = FALSE;
    }

    return !empty($file) ? $file : FALSE;
  }

  /**
   * Gets the existing file ID from the given Media entity.
   *
   * @return int|false
   *   The existing file ID or FALSE if one was not found.
   */
  public function getExistingFileId($field, $opt) {
    return $this->getFieldPropertyValue($field, $opt) ?? FALSE;
  }

  /**
   * Gets the file field being used to store the asset.
   *
   * @return false|string
   *   The name of the file field on the media bundle or FALSE on failure.
   */
  public function getAssetFileField() {
    try {
      /** @var \Drupal\media\Entity\MediaType $bundle */
      $bundle = $this->entityTypeManager->getStorage('media_type')
        ->load($this->mediaEntity->bundle());

      $field_map = !empty($bundle) ? $bundle->getFieldMap() : FALSE;

    }
    catch (\Exception $x) {
      return FALSE;
    }

    return empty($field_map['file']) ? FALSE : $field_map['file'];
  }

  /**
   * Get the asset from a media entity.
   *
   * @return \Drupal\helfi_gredi_image\Entity\Asset|null
   *   The asset or NULL on failure.
   */
  public function getAsset() {
    $assetId = $this->getAssetId();
    if (empty($assetId)) {
      return NULL;
    }
    return $this->grediDamClient->getAsset($assetId, ['meta', 'attachments']);
  }

  /**
   * Get the asset ID for the given media entity.
   *
   * @return string|false
   *   The asset ID or FALSE on failure.
   */
  public function getAssetId() {
    $sourceField = $this->mediaEntity->getSource()
      ->getSourceFieldDefinition($this->mediaEntity->get('bundle')->entity)
      ->getName();

    return $this->getFieldPropertyValue($sourceField) ?? FALSE;
  }

  /**
   * Gets the destination path for Gredi DAM assets.
   *
   * @return string
   *   The final folder to store the asset locally.
   */
  public function getAssetFileDestination() {
    return $this->assetFileHelper->getDestinationFromEntity($this->mediaEntity,
      $this->getAssetFileField());
  }

  /**
   * Gets the value of a field without knowing the key to use.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $opt
   *   The field name.
   *
   * @return null|mixed
   *   The field value or NULL.
   */
  protected function getFieldPropertyValue($fieldName, $opt = 'asset_id') {
    if ($this->mediaEntity->hasField($fieldName)) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $this->mediaEntity->{$fieldName}->first();

      if (!empty($item)) {
        return $item->{$opt};
      }
    }

    return NULL;
  }

}
