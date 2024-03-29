<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi\EventSubscriber;

use Drupal\helfi_gredi\GrediClient;
use Drupal\media\Entity\Media;
use Drupal\views\ResultRow;
use Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for gredi media library view.
 */
final class RemoteDataSubscriber implements EventSubscriberInterface {

  /**
   * The client.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  public GrediClient $client;

  /**
   * Constructs a new ViewsRemoteDataSubscriber object.
   *
   * @param \Drupal\helfi_gredi\GrediClient $client
   *   The client.
   */
  public function __construct(GrediClient $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RemoteDataQueryEvent::class => 'onQuery',
      RemoteDataLoadEntitiesEvent::class => 'onLoadEntities',
    ];
  }

  /**
   * Subscribes to populate the view results.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataQueryEvent $event
   *   The event.
   */
  public function onQuery(RemoteDataQueryEvent $event): void {
    $supported_bases = ['gredi_asset'];
    $base_tables = array_keys($event->getView()->getBaseTables());
    if (count(array_intersect($supported_bases, $base_tables)) === 0) {
      return;
    }
    $condition_groups = $event->getConditions();
    $sorts = $event->getSorts();

    $sortOrder = '';
    $sortBy = '';
    if (!empty($sorts)) {
      $sortOrder = current($sorts)['order'] == 'DESC' ? '-' : '';
      $sortBy = (current($sorts)['field'][0] == 'orderByName') ? 'orderByName' : 'orderByLastUsed';
      if (empty($sortBy)) {
        $sortOrder = '';
      }
    }

    // Only condition field supported now is 'search'.
    $search_value = '';
    $folderId = NULL;
    foreach ($condition_groups as $condition_group) {
      foreach ($condition_group['conditions'] as $condition) {
        $field_name = NULL;
        if (!empty($condition['field'][0])) {
          $field_name = $condition['field'][0];
        }
        elseif (!empty($condition['field'][1])) {
          $field_name = $condition['field'][1];
        }
        if (empty($field_name)) {
          continue;
        }
        switch ($field_name) {
          case 'search':
            $search_value = $condition['value'];
            break;

          case 'gredi_folder_id':
            $folderId = $condition['value'];
            break;
        }
      }
    }
    try {
      if (empty($search_value) && empty($folderId)) {
        $folderId = $this->client->getRootFolderId();
      }
      else if (!empty($search_value) && $folderId == $this->client->getRootFolderId()) {
        // Search in root folder should always be across all assets.
        $folderId = NULL;
      }
      $remote_data = $this->client->searchAssets($search_value, $folderId, $sortBy, $sortOrder, $event->getLimit(), $event->getOffset());
    }
    catch (\Exception $e) {
      \Drupal::logger('helfi_gredi')->error($e->getMessage());
      \Drupal::messenger()->addError(t('Failed to retrieve asset list. You may hit Apply filters to try again.'));
      return;
    }

    if (empty($remote_data)) {
      \Drupal::messenger()->addWarning(t('No results found.'));
      return;
    }
    foreach ($remote_data as $item) {
      $event->addResult(new ResultRow($item));
    }
  }

  /**
   * Subscribes to populate entities against the results.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent $event
   *   The event.
   */
  public function onLoadEntities(RemoteDataLoadEntitiesEvent $event): void {
    $supported_bases = ['gredi_asset'];
    $base_tables = array_keys($event->getView()->getBaseTables());
    if (count(array_intersect($supported_bases, $base_tables)) > 0) {
      foreach ($event->getResults() as $key => $result) {
        $result->_entity = Media::create([
          'mid' => $result->id,
          'bundle' => 'gredi_asset',
          'name' => $result->name,
          'gredi_asset_id' => [
            'value' => $result->id,
          ],
          // @todo this might now work in future.
          'gredi_folder' => $result->folder,
        ]);
        /** @var \Drupal\helfi_gredi\Plugin\media\Source\GrediAsset $source */
        $source = $result->_entity->getSource();
        if (!empty($result->object)) {
          $source->setAssetData($result->object);
        }
        else {
          // In folder search there's no object property returned,
          // even if we request it
          // so we do this hack :( to convert to array the initial result.
          $array = json_decode(json_encode($result), TRUE);
          $source->setAssetData($array);
        }
      }
    }
  }

}
