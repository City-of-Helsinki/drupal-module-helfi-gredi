<?php

namespace Drupal\helfi_gredi_image\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\helfi_gredi_image\MetaUpdater;

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
class MetaUpdaterCommands extends DrushCommands {

  /**
   * Update Gredi Meta.
   *
   * @command update_gredi:meta
   * @aliases update_gredi_meta
   */
  public function updateGrediMeta() {
    /** @var MetaUpdater $meta_updater */
    $meta_updater = new MetaUpdater();
    $meta_updater->populateMetaUpdateQueue();
    /* @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::service('queue')->get('meta_update');
    /* @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
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
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('GrediMetaData', $e);
      }
    }
    echo $counter . ' Gredi Assets metadata Updated!' . PHP_EOL;
  }

}
