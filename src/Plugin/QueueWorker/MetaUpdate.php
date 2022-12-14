<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "meta_update",
 *   title = @Translation("Meta Update"),
 *   cron = {"time" = 90}
 * )
 */
class MetaUpdate extends QueueWorkerBase {

  /**
   * DAM client.
   *
   * @var \Drupal\helfi_gredi_image\DamClientInterface
   */
  private $damClient;

  /**
   * Metadata helper.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetMetadataHelper
   */
  private AssetMetadataHelper $metadataHelper;

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
    $this->damClient = \Drupal::service('helfi_gredi_image.dam_client');
    $this->metadataHelper = \Drupal::service('helfi_gredi_image.asset_metadata.helper');
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\helfi_gredi_image\Entity\Asset $external_gredi_asset */
    $external_gredi_asset = $this->damClient->getAsset($data->external_id);
    /** @var \Drupal\media\Entity\Media $internal_gredi_asset */
    $internal_gredi_asset = Media::load($data->media_id);
    // TODO refactor this to check for modified timestamp, not to save and invalidate cache every cron run.
    // TODO We might want to use source method to force the update
    // TODO check Media::prepareSave and how it fills the values for fields.
    // TODO We might want to clear the values so that Media::prepareSave kicks in.
    $this->metadataHelper->performMetadataUpdate($internal_gredi_asset, $external_gredi_asset);
    \Drupal::logger('GrediMetaData')->notice('Metadata for Gredi asset with id ' . $data->media_id);
  }

}
