<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\PhpSerialize;

/**
 * Gredi DAM Asset Data service implementation.
 */
class AssetData implements ContainerInjectionInterface {

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The serializer object.
   *
   * @var \Drupal\Component\Serialization\PhpSerialize
   *   The serialization variable.
   */
  protected $serializer;

  /**
   * Constructs a new asset data service.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param \Drupal\Component\Serialization\PhpSerialize $serializer
   *   The serialization parameter.
   */
  public function __construct(Connection $connection, PhpSerialize $serializer) {
    $this->connection = $connection;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'), $container->get('serialization.phpserialize'));
  }

  /**
   * Check if the given asset is different than what is stored.
   *
   * @param \Drupal\helfi_gredi_image\Entity\Asset $asset
   *   The current version of the asset.
   *
   * @return bool
   *   TRUE if the given asset is a different version than what has been stored.
   */
  public function isUpdatedAsset(Asset $asset) {
    $latest_known_upload_date = $this->get($asset->external_id, 'file_upload_date');
    $actual_upload_date = strtotime($asset->file_upload_date);
    // Using loose equality to allow int/string comparison.
    return $latest_known_upload_date != $actual_upload_date;
  }

  /**
   * Returns data stored for an asset.
   *
   * @param int $assetID
   *   The ID of the asset that data is associated with.
   * @param string $name
   *   (optional) The name of the data key.
   *
   * @return mixed|array
   *   The requested asset data, depending on the arguments passed:
   *     - If $name was provided then the stored value is returned, or NULL if
   *       no value was found.
   *     - If no $name was provided then all data will be returned for the given
   *       asset if found.
   */
  public function get(int $assetID = NULL, string $name = NULL) {
    $query = $this->connection->select('gredidam_assets_data', 'ad')->fields(
        'ad'
      );
    if (isset($assetID)) {
      $query->condition('asset_id', $assetID);
    }
    if (isset($name)) {
      $query->condition('name', $name);
    }
    $result = $query->execute();

    // A specific value for a specific asset ID was requested.
    if (isset($assetID) && isset($name)) {
      $result = $result->fetchAllAssoc('asset_id');
      if (isset($result[$assetID])) {
        return $result[$assetID]->serialized ?
          $this->serializer->decode($result[$assetID]->value) : $result[$assetID]->value;
      }
      return NULL;
    }

    $return = [];

    // If only specific assets was requested.
    if (isset($assetID) || isset($name)) {
      $key = isset($assetID) ? 'name' : 'asset_id';

      foreach ($result as $record) {
        $return[$record->{$key}] = $record->serialized ?
          $this->serializer->decode($record->value) : $record->value;
      }
      return $return;
    }

    // Everything was requested.
    foreach ($result as $record) {
      $return[$record->asset_id][$record->name] = $record->serialized ?
        $this->serializer->decode($record->value) : $record->value;
    }

    return $return;
  }

  /**
   * Stores data for an asset.
   *
   * @param int $assetID
   *   The ID of the asset to store data against.
   * @param string $name
   *   The name of the data key.
   * @param mixed $value
   *   The value to store. Non-scalar values are serialized automatically.
   */
  public function set(int $assetID, string $name, $value) {
    $serialized = (int) !is_scalar($value);
    if ($serialized) {
      $value = $this->serializer->encode($value);
    }
    $this->connection->merge('gredidam_assets_data')->keys(
      [
        'asset_id' => $assetID,
        'name' => $name,
      ]
    )->fields(
      [
        'value' => $value,
        'serialized' => $serialized,
      ]
    )->execute();
  }

  /**
   * Deletes data stored for an asset.
   *
   * @param int|array $assetID
   *   (optional) The ID of the asset the data is associated with. Can also
   *   be an array to delete the data of multiple assets.
   * @param string $name
   *   (optional) The name of the data key. If omitted, all data associated with
   *   $assetID.
   */
  public function delete($assetID = NULL, $name = NULL) {
    $query = $this->connection->delete('gredidam_assets_data');
    // Cast scalars to array so we can consistently use an IN condition.
    if (isset($assetID)) {
      $query->condition('asset_id', (array) $assetID, 'IN');
    }
    if (isset($name)) {
      $query->condition('name', (array) $name, 'IN');
    }
    $query->execute();
  }

}
