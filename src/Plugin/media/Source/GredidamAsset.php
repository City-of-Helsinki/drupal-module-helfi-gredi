<?php

namespace Drupal\helfi_gredi_image\Plugin\media\Source;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\helfi_gredi_image\Service\GrediDamClient;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Gredi DAM assets.
 *
 * @MediaSource(
 *   id = "gredidam_asset",
 *   label = @Translation("Gredi DAM asset"),
 *   description = @Translation("Provides business logic and metadata for
 *   assets stored on Gredi DAM."),
 *   allowed_field_types = {"string"},
 * )
 */
class GredidamAsset extends Image {

  /**
   * The API assets array.
   *
   * @var array
   */
  protected $assetData = [];

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The gredidam client service.
   *
   * @var \Drupal\helfi_gredi_image\Service\GrediDamClient
   */
  protected $damClient;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * GredidamAsset constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    ConfigFactoryInterface $config_factory,
    GrediDamClient $damClient,
    ImageFactory $imageFactory,
    FileSystemInterface $fileSystem,
    LanguageManagerInterface $languageManager,
    TimeInterface $timeManager,
    DateFormatter $dateFormatter,
    FileRepositoryInterface $fileRepository) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory,
      $imageFactory,
      $fileSystem
    );

    $this->damClient = $damClient;
    $this->languageManager = $languageManager;
    $this->timeManager = $timeManager;
    $this->dateFormatter = $dateFormatter;
    $this->fileRepository = $fileRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('helfi_gredi_image.dam_client'),
      $container->get('image.factory'),
      $container->get('file_system'),
      $container->get('language_manager'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('file.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => 'field_media_image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    $fields = [];
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    try {
      $damMetadataFields = $this->damClient->getMetaFields();
      foreach ($damMetadataFields as $damField) {
        if (isset($damField['namesByLang'][$lang_code])) {
          $label = $damField['namesByLang'][$lang_code];
        }
        else {
          $label = current($damField['namesByLang']);
        }
        $fields[$damField['id']] = $label;
      }
    }
    catch (\Exception $e) {

    }

    $fields['created'] = $this->t('Created timestamp');
    $fields['modified'] = $this->t('Modified timestamp');
    $fields['original_file'] = $this->t('Original image');

    return $fields;
  }

  /**
   * Gets the metadata for the given entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to get metadata from.
   * @param string $attribute_name
   *   The metadata item to get the value of.
   *
   * @return mixed|null
   *   The metadata value or NULL if unset.
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Most of attributes requires data from API.
    $attr_with_fallback = [
      'default_name',
      'name',
      'thumbnail_uri',
    ];
    if (!in_array($attribute_name, $attr_with_fallback) && empty($this->assetData)) {
      try {
        $this->assetData = $this->damClient->getAssetData($media->get('gredi_asset_id')->value);
      }
      catch (\Exception $e) {
        $this->messenger()->addError('Failed to fetch asset data');
        return NULL;
      }
    }
    switch ($attribute_name) {
      case 'name':
      case 'default_name':
        if (!$media->isNew()) {
          return $media->getName();
        }
        if (!empty($this->assetData['name'])) {
          return $this->assetData['name'];
        }
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        if (!$media->isNew()) {
          return parent::getMetadata($media, $attribute_name);
        }

        $default_thumbnail_filename = $this->pluginDefinition['default_thumbnail_filename'];
        $fallback = $this->configFactory->get('media.settings')->get('icon_base_uri') . '/' . $default_thumbnail_filename;

        if (empty($this->assetData)) {
          return $fallback;
        }
        // Fetching asset thumbnail or from local.
        try {
          $assetId = $this->assetData['id'];
          $assetName = $this->assetData['name'];
          $assetModified = $this->assetData['modified'];
          // Create subfolders by month.
          $current_timestamp = $this->timeManager->getCurrentTime();
          $date_output = $this->dateFormatter->format($current_timestamp, 'custom', 'd/M/Y');
          $date = str_replace('/', '-', substr($date_output, 3, 8));

          // Asset name contains id and last updated date.
          $asset_name = $assetId . '_' . strtotime($assetModified) . substr($assetName, strrpos($assetName, "."));
          $directory = 'public://gredidam/thumbs/' . $date;
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          $location = $directory . '/' . $asset_name;
          if (!file_exists($location)) {
            $fileContent = $this->damClient->getFileContent($assetId, $this->assetData['apiPreviewLink']);
            $this->fileSystem->saveData($fileContent, $location, FileSystemInterface::EXISTS_REPLACE);
          }

          return $location;
        }
        catch (\Exception $e) {
          $this->messenger()->addError('Error fetching and saving thumbnail');
          return $fallback;
        }

      case 'original_file':
        return $this->getOriginalFile();

      case 'modified':
        return strtotime($this->assetData['modified']) ?? NULL;

      case 'created':
        return strtotime($this->assetData['created']) ?? NULL;

      case 'lang_codes':
        return array_keys($this->assetData['namesByLang']);

      default:
        $metaAttributes = $this->getMetadataAttributes();
        // If field is not found for current entity language,
        // try returning the default lang value.
        $fallbackValue = NULL;
        if (!isset($metaAttributes[$attribute_name])) {
          return NULL;
        }
        if (!isset($this->assetData['metaById'])) {
          return NULL;
        }
        $lang_code = $media->language()->getId();
        $fallbackLangCode = $this->languageManager->getCurrentLanguage()->getId();
        // Trying to find the attr id in the metaById,
        // as they come as custom:meta-field-1285_fi.
        foreach ($this->assetData['metaById'] as $attr_name_key => $value) {
          if (strpos($attr_name_key, 'custom:meta-field-') !== 0) {
            continue;
          }
          $attr_id_and_lang = str_replace('custom:meta-field-', '', $attr_name_key);
          [$attr_id, $attr_lang_code] = explode('_', $attr_id_and_lang);
          if ($attr_id != $attribute_name) {
            continue;
          }
          if ($attr_lang_code == $fallbackLangCode) {
            $fallbackValue = $value;
          }
          if ($attr_lang_code != $lang_code) {
            continue;
          }
          return $value;
        }

        return $fallbackValue;
    }
  }

  /**
   * Sets the asset data.
   *
   * @param array $data
   *   The asset data.
   */
  public function setAssetData(array $data) {
    $this->assetData = $data;
  }

  /**
   * Get the asset data.
   *
   * @return array
   *   Return the asset data.
   */
  public function getAssetData() : array {
    return $this->assetData;
  }

  /**
   * Retrieves the original file from API.
   *
   * @return \Drupal\file\FileInterface|null
   *   Return the file entity or null if it does not exists.
   */
  public function getOriginalFile() : FileInterface|NULL {
    try {
      $assetId = $this->assetData['id'];
      $assetName = $this->assetData['name'];
      $fileContent = $this->damClient->getFileContent($assetId, $this->assetData['apiContentLink']);

      // Create subfolders by month.
      $current_timestamp = $this->timeManager->getCurrentTime();
      $date_output = $this->dateFormatter->format($current_timestamp, 'custom', 'd/M/Y');
      $date = str_replace('/', '-', substr($date_output, 3, 8));

      // Create month folder.
      $directory = 'public://gredidam/original/' . $date;

      $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $location = $directory . '/' . $assetName;

      return $this->fileRepository->writeData($fileContent, $location);

    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
