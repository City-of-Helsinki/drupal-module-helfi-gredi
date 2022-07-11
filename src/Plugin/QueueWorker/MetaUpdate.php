<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;
use Drupal\helfi_gredi_image\GrediClientFactory;
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

  /** @var GrediClientFactory */
  private $grediClientFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $guzzle_http_client = new Client();
    $this->grediClientFactory = new GrediClientFactory($guzzle_http_client);
  }
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $external_gredi_asset = $this->grediClientFactory->getAsset($data->external_id);
    $internal_gredi_asset = Media::load($data->media_id);

    \Drupal::logger('GrediMetaData')->notice('Metadata for Gredi asset with id  ' . $data->id);
  }

}
