<?php

namespace Drupal\helfi_gredi_image\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\new_dependency_test\Service;

/**
 * The asset entity describing the asset object shared by Gredi DAM.
 *
 * @phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName
 */
class Asset implements EntityInterface, \JsonSerializable {

  const ATTACHMENT_TYPE_ORIGINAL = 'original';
  const ATTACHMENT_TYPE_PREVIEW = 'preview';
  const ATTACHMENT_TYPE_THUMBNAIL = 'thumbnail';

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
   * The external ID of the asset.
   *
   * @var string
   */
  public $linkFileId;

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
   * The asset's width.
   *
   * @var string
   */
  public $width;

  /**
   * The asset's height.
   *
   * @var string
   */
  public $height;

  /**
   * The asset's resoluion.
   *
   * @var string
   */
  public $resolution;

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
  public $previewLink;

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
   * Gredi DAM supported file formats.
   *
   * @todo Get these values from Config.
   * @todo Check if the values should be translatable.
   *
   * @return string[]
   *   An array of supported file formats.
   */
  public static function getFileFormats(): array {
    return [
      0 => 'All',
      'IMAGE' => 'Image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromJson($json) {
    if (is_string($json)) {
      $json = Json::decode($json);
    }

    $properties = [
      'id',
      'parentId',
      'linkFileId',
      'name',
      'created',
      'modified',
      'attachments',
      'object',
      'apiContentLink',
    ];
    $remote_asset_url = self::getAssetRemoteBaseUrl();
    // Copy all the simple properties.
    $asset = new self();

    // Get metafields.
    $metaProperties = [];
    $metafields = \Drupal::service('helfi_gredi_image.dam_client')->getMetaFields();
    // Assign the id for each metafield.
    foreach ($metafields as $fields) {
      $metaProperties[$fields['namesByLang']['en']] = [
        'id' => $fields['id'],
        'lang' => array_keys($fields['namesByLang']),
      ];
    }

    // Check all the translations from the API.
    if (isset($json['metaById'])) {
      // Keywords field.
      foreach ($metaProperties['Keywords']['lang'] as $language) {
        $buildMetaField = 'custom:meta-field-' . $metaProperties['Keywords']['id'] . '_' . $language;
        if (isset($json['metaById'][$buildMetaField])) {
          $asset->keywords[$language] = $json['metaById'][$buildMetaField];
        }
      }
      // Alt text field.
      foreach ($metaProperties['Alt text']['lang'] as $language) {
        $buildMetaField = 'custom:meta-field-' . $metaProperties['Alt text']['id'] . '_' . $language;
        if (isset($json['metaById'][$buildMetaField])) {
          $asset->alt_text[$language] = $json['metaById'][$buildMetaField];
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
              $asset->previewLink = $remote_asset_url . $json['apiPreviewLink'];
            }
          }
        }

        elseif (isset($json['object']) && $property === 'object') {
          $attachment = $json['object'];
          $asset->keywords = NULL;
          $asset->created = \DateTime::createFromFormat('Y-m-d\TH:i:s.u+', $json['created']);
          $asset->alt_text = NULL;
          $asset->mimeType = $attachment['propertiesById']['nibo:mime-type'];
          $asset->previewLink = $remote_asset_url . $attachment['apiPreviewLink'];
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
      'linkFileId' => $this->linkFileId,
      'name' => $this->name,
      'created' => $this->created,
      'modified' => $this->modified,
      'attachments' => $this->attachments,
    ];
  }

}
