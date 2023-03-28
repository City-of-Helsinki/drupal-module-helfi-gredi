<?php

namespace Drupal\helfi_gredi\Plugin\QueueWorker;

use Drupal\Core\Logger\RfcLogLevel;
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
    $media = Media::load($media_id);
    if (empty($media)) {
      \Drupal::logger('helfi_gredi')->log(RfcLogLevel::NOTICE, 'Media with id ' . $media_id . ' was not found.');
      return;
    }
    // External asset modified timestamp.
    $external_field_modified = $media->getSource()->getMetadata($media, 'modified');
    // Stored asset modified timestamp.
    $internal_field_modified = $media->get('gredi_modified')->value;
    // Set fields that needs to be updated NULL to let Media::prepareSave()
    // fill up the fields with the newest fetched data.
    $bundle = $media->getEntityType()->getBundleEntityType();

    $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();
    if ($external_field_modified > $internal_field_modified) {
      $media->set('gredi_modified', $external_field_modified);
      foreach ($field_map as $key => $field) {
        if ($key === 'original_file') {
          continue;
        }
        // Setting null will trigger media to fetch again the mapped values.
        $media->set($field, NULL);
      }
      $media->save();
      // When a new translation is present in gredi, we need to create.
      // TODO we need to loop and set null through all translations.
      // TODO we should handle the case when a new language in gredi appears and we have that lang enabled -> create the translation in Drupal.
      // TODO maybe find a way to reuse code from MediaLibrarySelectForm in regards with translation?
    }

    \Drupal::logger('helfi_gredi')
      ->notice('Metadata for Gredi asset with id ' . $media_id);
  }
}
