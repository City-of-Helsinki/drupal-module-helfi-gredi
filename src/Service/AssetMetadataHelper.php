<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\helfi_gredi_image\DamClientInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetMetadataHelper.
 *
 * Deals with reading and manipulating metadata for assets.
 */
class AssetMetadataHelper implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Gredi DAM config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Drupal date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A configured API object.
   *
   * @var \Drupal\helfi_gredi_image\DamClientInterface
   */
  protected $damClient;

  /**
   * Specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields = [];

  /**
   * AssetImageHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   A Drupal date formatter service.
   * @param \Drupal\helfi_gredi_image\DamClientInterface $damClient
   *   A configured API object.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    DateFormatterInterface $dateFormatter,
    DamClientInterface $damClient) {
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('media.type.gredi_dam_assets');
    $this->dateFormatter = $dateFormatter;
    $this->damClient = $damClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('helfi_gredi_image.dam_client')
    );
  }

  /**
   * Set the available specific metadata fields.
   *
   * <code>
   * [
   *   'assettype' => [
   *     'label' => 'Asset type',
   *     'type' => 'string',
   *   ],
   *   'author' => [
   *     'label' => 'Author',
   *     'type' => 'string',
   *   ]
   * ]
   * </code>
   *
   * @param array $fields
   *   Fields contains an array.
   */
  public function setSpecificMetadataFields(array $fields = []) {
    $this->specificMetadataFields = $fields;
  }

  /**
   * Get the available specific metadata fields.
   *
   * @return array
   *   An array contain specific metadata fields.
   */
  public function getSpecificMetadataFields() {
    if (empty($this->specificMetadataFields)) {
      $this->setSpecificMetadataFields(
        $this->damClient->getSpecificMetadataFields()
      );
    }

    return $this->specificMetadataFields;
  }

  /**
   * Get the available metadata attribute labels.
   *
   * @return array
   *   An array of possible metadata attributes keyed by their ID.
   */
  public function getMetadataAttributeLabels() {
    // TODO reasses this list?
    $fields = [
      'external_id' => $this->t('External ID'),
      'keywords' => $this->t('Keywords'),
      'alt_text' => $this->t('Alt text'),
      'created' => $this->t('Created'),
      'modified' => $this->t('Modified'),
      'media_image' => $this->t('Image'),
    ];

    // Add specific metadata fields to fields array.
    $specificMetadataFields = $this->getSpecificMetadataFields();
    if (!empty($specificMetadataFields)) {
      foreach ($specificMetadataFields as $id => $field) {
        $fields[$id] = $field['label'];
      }
    }

    return $fields;
  }

  /**
   * Gets a metadata item from the given asset.
   *
   * @param \Drupal\helfi_gredi_image\Entity\Asset $asset
   *   The asset to get metadata from.
   * @param string $name
   *   The name of the metadata item to retrieve.
   *
   * @return mixed
   *   Result will vary based on the metadata item.
   */
  public function getMetadataFromAsset(Asset $asset, $name) {

    switch ($name) {

      case 'keywords':
        return $asset->keywords;

      case 'alt_text':
        return $asset->alt_text;

      case 'created':
        return $asset->created;

      case 'external_id':
        return $asset->external_id;
    }

    return NULL;
  }

  /**
   * Function to populate meta_update queue with items to update.
   */
  public function populateMetadataUpdateQueue(): void {
    /** @var array $results */
    $results = $this->getAssetsToUpdate();

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::queue('meta_update');
    foreach ($results as $key => $value) {
      /** @var \Drupal\media\Entity\Media $gredi_asset */
      $gredi_asset = Media::load($value);
      /** @var \stdClass $item */
      $item = new \stdClass();
      $item->media_id = $gredi_asset->id();
      $item->external_id = $gredi_asset->get('gredi_asset_id')->getString();
      $queue->createItem($item);
    }

    echo count($results) . " assets added to the queue.";
  }

  /**
   * Function to perform Gredi asset metadata update.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The Drupal media entity to update.
   * @param \Drupal\helfi_gredi_image\Entity\Asset $gredi_asset
   *   The Gredi DAM asset.
   */
  public function performMetadataUpdate(Media $media, Asset $gredi_asset): void {

    /** @var array $mapping */
    $mapping = $this->getMapping();

    foreach ($mapping as $gredi_field => $drupal_field) {
      if ($gredi_field == 'media_image') {
        continue;
      }
      if (is_array($gredi_asset->{$gredi_field})) {
        foreach ($gredi_asset->{$gredi_field} as $lang => $value) {
          if ($media->getTranslation($lang)->get($drupal_field)->getString() != $value) {
            $media->getTranslation($lang)->set($drupal_field, $value);
          }
        }
      }
      elseif ($gredi_asset->{$gredi_field} != $media->get($drupal_field)->getString()) {
        $media->set($drupal_field, $gredi_asset->{$gredi_field});
      }
    }
    $media->save();
  }

  /**
   * Function to get list of Gredi DAM Assets to update.
   *
   * @return array
   *   List of Gredi DAM Assets.
   */
  private function getAssetsToUpdate(): array {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('media')
      ->condition('bundle', 'gredi_dam_assets');
    /** @var array $results */
    $results = $query->execute();

    return $results;
  }

  /**
   * Function to get mapping between Drupal entity and Gredi DAM asset fields.
   *
   * @return array
   *   Mapping.
   */
  private function getMapping(): array {
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config;
    /** @var array $original_data */
    $original_data = $config->getOriginal();

    return $original_data['field_map'];
  }

}
