<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "gredi_asset_update",
 *   title = @Translation("Updates Gredi image asset"),
 *   cron = {"time" = 90}
 * )
 */
class MetaUpdate extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($media_id) {
    if ($media_id) {
      $media = Media::load($media_id);
      // Stored asset id.
      $asset_id = $media->gredi_asset_id->value;
      /** @var \Drupal\helfi_gredi_image\GrediDamClient $damClient */
      $damClient = \Drupal::service('helfi_gredi_image.dam_client');
      $asset_data = $damClient->getAssetData($asset_id);
      // External asset modified timestamp.
      $external_field_modified = strtotime($asset_data['modified']);
      // Stored asset modified timestamp.
      $internal_field_modified = $media->gredi_modified->value;
      // Set fields that needs to be updated NULL to let Media::prepareSave()
      // fill up the fields with the newest fetched data.
      if ($external_field_modified > $internal_field_modified) {
        $media->set('field_alt_text', NULL);
        $media->set('field_keywords', NULL);
        $media->save();
      }

      \Drupal::logger('helfi_gredi_image')
        ->notice('Metadata for Gredi asset with id ' . $media_id);
    }
  }

}
