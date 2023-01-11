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
      $sortOrder = current($sorts)['order'] == 'DESC' ? '-' : '+';
      if (current($sorts)['field'][0] == 'modified') {
        $sortBy = current($sorts)['field'][0];
      }
    }

    // Only condition field supported now is 'search'.
    $search_value = '';
    foreach ($condition_groups as $condition_group) {
      foreach ($condition_group['conditions'] as $condition) {
        if (!isset($condition['field'][0])
          || $condition['field'][0] != 'search') {
          continue;
        }
        $search_value = $condition['value'];
      }
    }
    try {
      $remote_data = $this->client->searchAssets($search_value, $sortBy, $sortOrder, $event->getLimit(), $event->getOffset());
    }
    catch (\Exception $e) {
      \Drupal::logger('helfi_gredi')->error($e->getMessage());
      \Drupal::messenger()->addError(t('Failed to retrieve asset list'));
      $remote_data = [];
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
        ]);
        /** @var \Drupal\helfi_gredi\Plugin\media\Source\GrediAsset $source */
        $source = $result->_entity->getSource();
        if (!empty($result->object)) {
          $source->setAssetData($result->object);
        }
        else {
          $source->setAssetData([]);
        }
      }
    }
  }

}
