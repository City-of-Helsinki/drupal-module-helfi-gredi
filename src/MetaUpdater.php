<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\media\Entity\Media;

/**
 * Class MetaUpdater.
 */
class MetaUpdater {

  /**
   * Function to populate meta_update queue with items to update.
   */
  public function populateMetadataUpdateQueue(): void {
    /** @var array $results */
    $results = $this->getAssetsToUpdate();

    /* @var QueueInterface $queue */
    $queue = \Drupal::service('queue')->get('meta_update');
    foreach ($results as $key => $value) {
      /** @var Media $gredi_asset */
      $gredi_asset = Media::load($value);
      /** @var \stdClass $item */
      $item = new \stdClass();
      $item->media_id = $value;
      $item->external_id = $gredi_asset->get('field_external_id')->getString();
      $queue->createItem($item);
    }
  }

  /**
   * Function to perform Gredi asset metadata update.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The Drupal media entity to update.
   * @param \stdClass $gredi_asset
   *   The Gredi DAM asset.
   *
   * @return void
   */
  public function performMetadataUpdate(Media $media, \stdClass $gredi_asset): void {
    /** @var array $mapping */
    $mapping = $this->getMapping();
    foreach ($mapping as $gredi_field => $drupal_field) {
      if ($gredi_asset->{$gredi_field} != $media->get($drupal_field)->getString()) {
        $media->set($drupal_field, $gredi_asset->{$gredi_field});
      }
    }
    $media->save();
  }

  /**
   * Function to get list of Gredi DAM Assets to update.
   *
   * @return array
   *   List of Gredi DAM Assets.
   */
  private function getAssetsToUpdate(): array {
    /** @var QueryInterface $query */
    $query = \Drupal::entityQuery('media')
      ->condition('bundle', 'gredi_dam_assets');
    /** @var array $results */
    $results = $query->execute();

    return $results;
  }

  /**
   * Function to get mapping between Drupal entity and Gredi DAM asset fields.
   *
   * @return array
   *   Mapping.
   */
  private function getMapping(): array {
    /** @var ImmutableConfig $config */
    $config = \Drupal::config('media.type.gredi_dam_assets');
    /** @var array $original_data */
    $original_data = $config->getOriginal();

    return $original_data['field_map'];
  }
}
