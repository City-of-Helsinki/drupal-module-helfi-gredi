<?php

namespace Drupal\helfi_gredi_image\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;

/**
 * The asset entity describing the asset object shared by Gredi DAM.
 *
 * @phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName
 */
class Asset implements EntityInterface, \JsonSerializable {

  const ATTACHMENT_TYPE_ORIGINAL = 'original';

  /**
   * The ID of the asset.
   *
   * @var string
   */
  public $id;

  /**
   * The ID of the asset.
   *
   * @var string
   */
  public $external_id;

  /**
   * The Parent ID of the asset.
   *
   * @var string
   */
  public $parentId;

  /**
   * The filename of the asset.
   *
   * @var string
   */
  public $name;

  /**
   * The date the asset has been created (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $created;

  /**
   * The latest date the asset has been updated (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $modified;

  /**
   * The links to download the asset.
   *
   * @var array
   */
  public $attachments;

  /**
   * The asset's metadata.
   *
   * @var array
   */
  public $metadata;

  /**
   * The asset's keywords.
   *
   * @var string
   */
  public $keywords;

  /**
   * The asset's alt_text.
   *
   * @var string
   */
  public $alt_text;

  /**
   * The asset's size.
   *
   * @var string
   */
  public $size;

  /**
   * The asset's mimeType.
   *
   * @var string
   */
  public $mimeType;

  /**
   * Api content link.
   *
   * @var string
   */
  public $apiContentLink;

  /**
   * Preview link.
   *
   * @var string
   */
  public $apiPreviewLink;

  /**
   * Upload date.
   *
   * @var string
   */
  public $file_upload_date;

  /**
   * A list of allowed values for the "expand" query attribute.
   *
   * @return string[]
   *   The exhaustive list of allowed "expand" values.
   */
  public static function getAllowedExpands(): array {
    return [
      'basic',
      'image',
      'meta',
      'attachments',
    ];
  }

  /**
   * The default expand query attribute.
   *
   * These attributes are mandatory for some later process.
   *
   * @return string[]
   *   The list of expands properties which must be fetched along the asset.
   */
  public static function getRequiredExpands(): array {
    return [
      'meta',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromJson($json) {
    if (is_string($json)) {
      $json = Json::decode($json);
    }
    $locationToCheck = 'public://gredidam/thumbs';
    \Drupal::service('file_system')->prepareDirectory($locationToCheck, FileSystemInterface::CREATE_DIRECTORY);

    $properties = [
      'id',
      'parentId',
      'name',
      'created',
      'modified',
      'attachments',
      'object',
      'apiContentLink',
      'apiPreviewLink',
    ];
    // Copy all the simple properties.
    $asset = new self();

    // Get metafields.
    $metaProperties = [];
    $metaFields = \Drupal::service('helfi_gredi_image.dam_client')->getMetaFields();
    $mappingFields = \Drupal::service('helfi_gredi_image.dam_client')->mapMetaData();

    // Assign the id for each metafield.
    foreach ($metaFields as $fields) {
      $metaProperties[$fields['id']] = array_keys($fields['namesByLang']);
    }
    // Check all the translations from the API.
    if (isset($json['metaById'])) {

      foreach ($metaProperties as $key => $languages) {
        // Check if mapping is available for the field.
        if (in_array($key, array_keys($mappingFields))) {
          // Go through all field translations.
          foreach ($languages as $language) {
            // Build the format from the API field.
            $buildMetaField = 'custom:meta-field-' . $key . '_' . $language;
            if (isset($json['metaById'][$buildMetaField])) {
              $field_name = $mappingFields[$key];
              $asset->$field_name[$language] = $json['metaById'][$buildMetaField];
            }
          }
        }
      }
    }

    foreach ($properties as $property) {
      if (isset($json[$property])) {
        if (isset($json['attachments']) && $property === 'attachments') {
          foreach ($json['attachments'] as $attachment) {
            if ($attachment['type'] === self::ATTACHMENT_TYPE_ORIGINAL) {
              $asset->created = \DateTime::createFromFormat('Y-m-d\TH:i:s.u+', $json['created'])->format('Y-m-d H:i:s');
              $asset->mimeType = $attachment['propertiesById']['nibo:mime-type'];
            }
          }
        }

        elseif (isset($json['object']) && $property === 'object') {
          $attachment = $json['object'];
          $asset->keywords = NULL;
          $asset->created = \DateTime::createFromFormat('Y-m-d\TH:i:s.u+', $json['created']);
          $asset->alt_text = NULL;
          $asset->mimeType = $attachment['propertiesById']['nibo:mime-type'];
          $asset->apiContentLink = $attachment['apiContentLink'];
          $asset->apiPreviewLink = $attachment['apiPreviewLink'];
        }
        elseif ($property == 'id') {
          $asset->id = $json['id'];
          $asset->external_id = $json['id'];
        }
        else {
          $asset->{$property} = $json[$property];
        }
      }
    }

    // TODO The folder should have some subfolders, to prevent too many images in a fodler
    // TODO it should have in the name the asset id + lastupdated timestamp.
    // TODO it should also check for existing images so that we don't fetch always the image.
    // TODO we should decide how we can clean up the old images (maybe use cache data binary instead of saving as image)
    $location = 'public://gredidam/thumbs/' . $asset->name;
    $fileContent = \Drupal::service('helfi_gredi_image.dam_client')->fetchRemoteAssetData($asset, $asset->name, FALSE);
    $asset->apiPreviewLink = \Drupal::service('helfi_gredi_image.asset_file.helper')->drupalFileSaveData($fileContent, $location)->createFileUrl();

    return $asset;
  }

  /**
   * Function to get remote asset base url.
   *
   * @return string
   *   Remote asset base url.
   */
  public static function getAssetRemoteBaseUrl(): string {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::service('config.factory');
    $module_config = $config_factory->get('helfi_gredi_image.settings');
    $base_url = trim($module_config->get('domain'));
    $base_url_parts = parse_url($base_url);
    return sprintf("%s://%s", $base_url_parts['scheme'], $base_url_parts['host']);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize() {
    return [
      'id' => $this->id,
      'parentId' => $this->parentId,
      'name' => $this->name,
      'created' => $this->created,
      'modified' => $this->modified,
      'attachments' => $this->attachments,
      'apiContentLink' => $this->apiContentLink,
      'apiPreviewLink' => $this->apiPreviewLink,
    ];
  }

}
