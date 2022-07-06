<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_gredi_image\GredidamInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetMetadataHelper.
 *
 * Deals with reading and manipulating metadata for assets.
 */
class AssetMetadataHelper implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Drupal date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A configured API object.
   *
   * @var \Drupal\helfi_gredi_image\GredidamInterface|\Drupal\helfi_gredi_image\Client
   *   $gredidam
   */
  protected $gredidam;

  /**
   * Specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields = [];

  /**
   * AssetImageHelper constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   A Drupal date formatter service.
   * @param \Drupal\helfi_gredi_image\GredidamInterface|\Drupal\helfi_gredi_image\Client $gredidam
   *   A configured API object.
   */
  public function __construct(DateFormatterInterface $dateFormatter, GredidamInterface $gredidam) {
    $this->dateFormatter = $dateFormatter;
    $this->gredidam = $gredidam;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('helfi_gredi_image.gredidam')
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
        $this->gredidam->getSpecificMetadataFields()
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
    $fields = [
      'external_id' => $this->t('External ID'),
      'name' => $this->t('Filename'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'resolution' => $this->t('Resolution'),
      'keywords' => $this->t('Keywords'),
      'alt-text' => $this->t('Alt text'),
      'media_image' => $this->t('Image'),
      'size' => $this->t('Filesize (kb)'),
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
    $specificMetadataFields = $this->getSpecificMetadataFields();
    if (array_key_exists($name, $specificMetadataFields)) {
      if (is_array($asset->metadata[$name]) && !empty($asset->metadata[$name])) {
        return reset($asset->metadata[$name]);
      }

      return !empty($asset->metadata[$name]) ? $asset->metadata[$name] : NULL;
    }

    switch ($name) {
      case 'height':
        return $asset->metadata['height'] ?? NULL;
      case 'width':
        return $asset->metadata['width'] ?? NULL;
      case 'resoluion':
        return $asset->metadata['resolution'] ?? NULL;
      case 'keywords':
        return $asset->metadata['keywords'] ?? NULL;
      case 'alt-text':
        return $asset->metadata['alt-text'] ?? NULL;
      case 'size':
        return $asset->metadata['size'] ?? NULL;



      default:
        // The key should be the local property name and the value should be the
        // DAM provided property name.
        $property_name_mapping = [
          'external_id' => 'id',
          'name' => 'name',
        ];
        if (array_key_exists($name, $property_name_mapping)) {
          $property_name = $property_name_mapping[$name];
          return $asset->{$property_name} ?? NULL;
        }
    }

    return NULL;
  }

}
