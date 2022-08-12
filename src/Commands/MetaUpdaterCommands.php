<?php

namespace Drupal\helfi_gredi_image\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MetaUpdaterCommands extends DrushCommands implements ContainerInjectionInterface {

  /**
   * Metadata helper service.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetMetadataHelper
   */
  private AssetMetadataHelper $metadataHelper;

  /**
   * Constructor.
   */
  public function __construct(AssetMetadataHelper $metadataHelper) {
    parent::__construct();
    $this->metadataHelper = $metadataHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_gredi_image.asset_metadata.helper')
    );
  }

  /**
   * Populate Gredi metadata queue.
   *
   * @command populate_gredi:meta
   * @aliases populate_gredi_meta
   */
  public function populateGrediMetadataUpdateQueue() {
    $this->metadataHelper->populateMetadataUpdateQueue();
  }

  /**
   * Update Gredi metadata.
   *
   * @command update_gredi:meta
   * @aliases update_gredi_meta
   */
  public function updateGrediMetadata() {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::queue('meta_update');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('meta_update');

    $counter = 0;
    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        usleep(35000);
        $counter++;
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        watchdog_exception('GrediMetaData', $e);
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('GrediMetaData', $e);
      }
    }
    echo sprintf("%d Gredi asset metadata updated!", $counter) . PHP_EOL;
  }

}
