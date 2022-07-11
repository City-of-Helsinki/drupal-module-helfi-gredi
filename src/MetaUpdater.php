<?php

namespace Drupal\helfi_gredi_image;

use Drupal\media\Entity\Media;

/**
 * Class MetaUpdater.
 */
class MetaUpdater {

  /**
   * Function to send notifications by e-mail.
   *
   * @param \Drupal\media\Entity\Media $media
   *   News Alert node.
   */
  public function populateMetaUpdateQueue(Media $media) {
    $result = $this->getAssetsToUpdate();

    /* @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::service('queue')->get('meta_update');
    while ($row = $result->fetchAssoc()) {
      $item = new \stdClass();
      $item->id = $row->get('field_gredidam_asset_id')->getValue();
      $queue->createItem($item);
    }
  }

  /**
   * Function to get list of Gredi DAM Assets to update.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   List of Gredi DAM Assets.
   */
  private function getAssetsToUpdate() {
    $query = \Drupal::entityQuery('media')
      ->condition('type', 'gredi_dam_assets');
    $results = $query->execute();

    return $results;
  }

}
