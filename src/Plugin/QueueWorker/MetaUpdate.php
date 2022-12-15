<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;

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
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($media_id) {
    $media = Media::load($media_id);
    // TODO refactor this to check for modified timestamp, not to save and invalidate cache every cron run.
    // TODO We might want to use source method to force the update
    // TODO check Media::prepareSave and how it fills the values for fields.
    // TODO We might want to clear the values so that Media::prepareSave kicks in.
//    $this->metadataHelper->performMetadataUpdate($internal_gredi_asset, $external_gredi_asset);
    \Drupal::logger('helfi_gredi_image')->notice('Metadata for Gredi asset with id ' . $data->media_id);
  }

}
