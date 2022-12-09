<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi_image\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\helfi_gredi_image\Service\GrediDamClient;
use Drupal\media\Entity\Media;
use Drupal\views\ResultRow;
use Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RemoteDataSubscriber implements EventSubscriberInterface {

  /**
   * The client.
   *
   * @var \Drupal\helfi_gredi_image\Service\GrediDamClient
   */
  public GrediDamClient $client;

  /**
   * Constructs a new ViewsRemoteDataSubscriber object.
   *
   * @param \Drupal\helfi_gredi_image\Service\GrediDamClient $client
   *   The client.
   */
  public function __construct(GrediDamClient $client) {
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
    $supported_bases = ['gredidam_assets'];
    $base_tables = array_keys($event->getView()->getBaseTables());
    if (count(array_intersect($supported_bases, $base_tables)) > 0) {
      $remote_data = $this->client->searchAssets();
      foreach ($remote_data as $item) {
        $event->addResult(new ResultRow($item));
      }
    }
  }

  /**
   * Subscribes to populate entities against the results.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent $event
   *   The event.
   */
  public function onLoadEntities(RemoteDataLoadEntitiesEvent $event): void {
    $supported_bases = ['gredidam_assets'];
    $base_tables = array_keys($event->getView()->getBaseTables());
    if (count(array_intersect($supported_bases, $base_tables)) > 0) {
      foreach ($event->getResults() as $key => $result) {
        $result->_entity = Media::create([
          'mid' => $result->id,
          'bundle' => 'gredi_dam_assets',
          'name' => $result->name,
          'gredi_asset_id' => [
            'value' => $result->id,
          ],
        ]);
        /** @var \Drupal\helfi_gredi_image\Plugin\media\Source\GredidamAsset $source */
        $source = $result->_entity->getSource();
        if (!empty($result->object)) {
          $source->setAssetData($result->object);
        }
        else {
          $source->setAssetData('');
        }
      }
    }
  }
}
