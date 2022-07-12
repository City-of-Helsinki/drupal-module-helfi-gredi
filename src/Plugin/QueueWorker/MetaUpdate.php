<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;
use Drupal\helfi_gredi_image\GrediClientFactory;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;
use GuzzleHttp\Client;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "meta_update",
 *   title = @Translation("Meta Update")
 * )
 */
class MetaUpdate extends QueueWorkerBase {

  /**
   * Gredi client.
   *
   * @var \Drupal\helfi_gredi_image\GrediClientFactory
   */
  private $grediClientFactory;

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

    $guzzle_http_client = new Client();
    $this->grediClientFactory = new GrediClientFactory($guzzle_http_client);
    $this->metadataHelper = \Drupal::service('helfi_gredi_image.asset_metadata.helper');
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\helfi_gredi_image\Entity\Asset $external_gredi_asset */
    $external_gredi_asset = $this->grediClientFactory->getAsset($data->external_id);
    /** @var \Drupal\media\Entity\Media $internal_gredi_asset */
    $internal_gredi_asset = Media::load($data->media_id);
    $this->metadataHelper->performMetadataUpdate($internal_gredi_asset, $external_gredi_asset);
    \Drupal::logger('GrediMetaData')->notice('Metadata for Gredi asset with id  ' . $data->id);
  }

}
