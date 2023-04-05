<?php

namespace Drupal\helfi_gredi\Plugin\media\Source;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\helfi_gredi\GrediClient;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Gredi DAM assets.
 *
 * @MediaSource(
 *   id = "gredi_asset",
 *   label = @Translation("Gredi asset"),
 *   description = @Translation("Provides business logic and metadata for
 *   assets stored on Gredi DAM."),
 *   allowed_field_types = {"string"},
 * )
 */
class GrediAsset extends Image {

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
   * The gredi client service.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  protected $grediClient;

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
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Lang codes mismatch between api and site.
   * Key is code lang in Gredi and value is code lang in Drupal.
   *
   * @var string[]
   */
  public $langMappingsCorrection = [
    'se' => 'sv',
  ];

  /**
   * GrediAsset constructor.
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
    GrediClient $grediClient,
    ImageFactory $imageFactory,
    FileSystemInterface $fileSystem,
    LanguageManagerInterface $languageManager,
    TimeInterface $timeManager,
    DateFormatter $dateFormatter,
    FileRepositoryInterface $fileRepository,
    StreamWrapperManager $streamWrapperManager,
    LoggerChannelFactory $loggerFactory) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory,
      $imageFactory,
      $fileSystem,
      $loggerFactory
    );

    $this->grediClient = $grediClient;
    $this->languageManager = $languageManager;
    $this->timeManager = $timeManager;
    $this->dateFormatter = $dateFormatter;
    $this->fileRepository = $fileRepository;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->logger = $loggerFactory->get('helfi_gredi');
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
      $container->get('helfi_gredi.dam_client'),
      $container->get('image.factory'),
      $container->get('file_system'),
      $container->get('language_manager'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('file.repository'),
      $container->get('stream_wrapper_manager'),
      $container->get('logger.factory')
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
      $damMetadataFields = $this->grediClient->getMetaFields();
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
    // Most of the attributes requires data from API.
    $attr_with_fallback = [
      'default_name',
      'name',
      'thumbnail_uri',
      'gredi_asset_id',
    ];
    $is_gredi_attr = !in_array($attribute_name, $attr_with_fallback);
    $removed_from_gredi = !empty($media->get('gredi_removed')->value);
    if ($is_gredi_attr && $removed_from_gredi) {
      return NULL;
    }

    // Fetch asset data if not already fetched.
    if ($is_gredi_attr && empty($this->assetData)) {
      try {
        $this->assetData = $this->grediClient->getAssetData($media->get('gredi_asset_id')->value);
      }
      catch (\Exception $e) {
        // The api return 400 Bad request instead of 404 when asset not found.
        if ($e->getCode() === 400) {
          if (empty($media->get('gredi_removed')->value)) {
            // This might not be the best place to save.
            $media->set('gredi_removed', FALSE);
            $media->save();
          }
          return NULL;
        }
        $this->messenger()->addError('Failed to fetch asset data');
        return NULL;
      }
    }

    switch ($attribute_name) {
      case 'gredi_asset_id':
        return $media->get('gredi_asset_id')->value;

      case 'name':
        if (!empty($this->assetData['name'])) {
          return $this->assetData['name'];
        }
        if (!$media->isNew()) {
          return $media->label();
        }
        return parent::getMetadata($media, 'default_name');

      case 'default_name':
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

          // Get the file storage uri_scheme.
          $field_storage = $this->entityTypeManager->getStorage('field_storage_config')
            ->load('media.field_media_image')->getSettings();
          $scheme = $field_storage['uri_scheme'];
          $uri = $this->streamWrapperManager->getViaScheme($scheme)->getUri();

          $directory = $uri . '/gredi/thumbs/' . $date;
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          $location = $directory . '/' . $asset_name;
          if (!file_exists($location)) {
            $fileContent = $this->grediClient->getFileContent($assetId, $this->assetData['apiPreviewLink']);
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

      case 'lang_codes_corrected':
        $lang_codes = array_keys($this->assetData['namesByLang']);
        foreach ($lang_codes as $idx => $lang_code) {
          if (isset($this->langMappingsCorrection[$lang_code])) {
            $lang_codes[$idx] = $this->langMappingsCorrection[$lang_code];
          }
        }
        return $lang_codes;

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
        // TODO figure out a way that when creating a new translation in drupal
        // TODO the Gredi value, from the table from Gredi Asset tab to display correct value
        // TODO as it is now it follows the source values if the language is not avaialable in gredi
        $lang_code = $media->language()->getId();
        $fallbackLangCode = $this->languageManager->getDefaultLanguage()->getId();
        // Trying to find the attr id in the metaById,
        // as they come as custom:meta-field-1285_fi.
        foreach ($this->assetData['metaById'] as $attr_name_key => $value) {
          if (strpos($attr_name_key, 'custom:meta-field-') !== 0) {
            continue;
          }
          $attr_id_and_lang = str_replace('custom:meta-field-', '', $attr_name_key);
          [$attr_id, $attr_lang_code] = explode('_', $attr_id_and_lang);
          // API uses 'SE' lang code for Swedish, so we use this hardcoded mapping.
          if (isset($this->langMappingsCorrection[$attr_lang_code])) {
            $attr_lang_code = $this->langMappingsCorrection[$attr_lang_code];
          }
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

        return NULL;
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
      $fileContent = $this->grediClient->getFileContent($assetId, $this->assetData['apiContentLink']);

      // Create subfolders by month.
      $current_timestamp = $this->timeManager->getCurrentTime();
      $date_output = $this->dateFormatter->format($current_timestamp, 'custom', 'd/M/Y');
      $date = str_replace('/', '-', substr($date_output, 3, 8));

      // Get the file storage uri_scheme.
      $field_storage = $this->entityTypeManager->getStorage('field_storage_config')
        ->load('media.field_media_image')->getSettings();
      $scheme = $field_storage['uri_scheme'];
      $uri = $this->streamWrapperManager->getViaScheme($scheme)->getUri();
      $directory = $uri . '/gredi/original/' . $date;

      $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $location = $directory . '/' . $assetName;

      return $this->fileRepository->writeData($fileContent, $location);

    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Method for syncing assets from drupal with external gredi assets.
   *
   * @param \Drupal\media\MediaInterface $media
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function syncMediaFromGredi(MediaInterface $media) : bool {
    // External asset modified timestamp.
    $external_field_modified = $media->getSource()->getMetadata($media, 'modified');
    if ($media->get('gredi_removed')->value) {
      $this->logger->warning($this->t('Gredi asset id @asset_id no longer found.', [
        '@asset_id' => $media->get('gredi_asset_id')->value]));
      return FALSE;
    }
    $bundle = $media->getEntityType()->getBundleEntityType();

    $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();

    $media->set('gredi_modified', $external_field_modified);
    $apiLanguages = $media->getSource()->getMetadata($media, 'lang_codes_corrected');
    $siteLanguages = array_keys(\Drupal::languageManager()->getLanguages());

    foreach ($apiLanguages as $apiLangCode) {
      if (!in_array($apiLangCode, $siteLanguages)) {
        continue;
      }
      try {
        /** @var \Drupal\media\MediaInterface $translation */
        $translation = $media->getTranslation($apiLangCode);
      }
      catch (\Exception $e) {
        $translation = $media->addTranslation($apiLangCode);
        $source_field_name = $media->getSource()
          ->getConfiguration()['source_field'];
        if ($translation->get($source_field_name)
          ->getFieldDefinition()
          ->isTranslatable()) {
          $translation->set($source_field_name, $media->get($source_field_name)->getValue());
        }
      }

      $name = $media->getSource()->getMetadata($media, 'name');
      // @todo if name changes, should we rename the file also?
      $translation->set('name', $name);
      // Set fields that needs to be updated NULL to let Media::prepareSave()
      // fill up the fields with the newest fetched data.
      foreach ($field_map as $key => $field) {
        // Skip the original_file field.
        if ($key === 'original_file') {
          continue;
        }
        $translation->set($field, NULL);
      }
      $translation->save();
    }
    $this->logger->notice($this->t('Synced metadata for Gredi asset id @id', ['@id' => $media->id()]));

    return TRUE;
  }

  /**
   * Prepare meta fields values for upload/sync.
   *
   * @param bool $is_update
   * @param \Drupal\media\MediaInterface $media
   * @param $inputs
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareMetafieldsUpload(bool $is_update, MediaInterface $media, $inputs) {

    $bundle = $media->getEntityType()->getBundleEntityType();
    $field_map = $this->entityTypeManager->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();

    if ($is_update) {
      $meta_fields = [];
      foreach ($field_map as $key => $field) {
        if (is_int($key)) {
          foreach ($inputs as $lang_code => $value) {
            // API uses 'SE' for 'SV' lang code.
            if (array_key_exists($lang_code, $this->langMappingsCorrection))  {
              $lang_code = $this->langMappingsCorrection[$lang_code];
            }
            $meta_fields += [
              'custom:meta-field-' . $key . '_' . $lang_code => $value[$field]
            ];
          }
        }
      }
    }
    else {
      $meta_fields = [];
      foreach ($field_map as $key => $field) {
        if (is_int($key)) {
          $meta_fields += [
            'custom:meta-field-' . $key . '_' . $inputs['langcode'] => $inputs[$field]
          ];
        }
      }
    }

    return $meta_fields;
  }

  public function getMetaFieldsForGrediUpdate(MediaInterface $media) {
    $bundle = $media->getEntityType()->getBundleEntityType();
    $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();

    $inputs = [];
    $apiLanguages = $media->getSource()->getMetadata($media, 'lang_codes');
    $langMappingsCorrection = $media->getSource()->langMappingsCorrection;
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($field_map as $key => $field) {
      if ($key === 'original_file') {
        continue;
      }
      foreach ($apiLanguages as $apiLanguage) {
        if (array_key_exists($apiLanguage, $langMappingsCorrection)) {
          $apiLanguage = $langMappingsCorrection[$apiLanguage];
        }
        if ($apiLanguage === $currentLanguage) {
          $inputs[$apiLanguage][$field] = $media->get($field)->value;
          continue;
        }
        if ($media->hasTranslation($apiLanguage)) {
          $translated_media = $media->getTranslation($apiLanguage);
          $inputs[$apiLanguage][$field] = $translated_media->get($field)->value;
        }
      }
    }

    // An associative array containing field values by lang code keys.
    return $inputs;
  }

  public function sendMetafieldsUpload(MediaInterface $media, array|NULL $inputs, bool $is_update) {

    $requestData = [];

    // This is the case of syncing an asset.
    if ($is_update) {
      // Create the array containing meta fields values by lang code.
      $inputs = $media->getSource()->getMetaFieldsForGrediUpdate($media);
      // Process meta fields based on the action that we do (update asset or initial upload).
      $meta_fields = $media->getSource()->prepareMetafieldsUpload($is_update, $media, $inputs);

      $fieldData = [
        "name" => $media->getName(),
        "propertiesById" => [],
        "metaById" => $meta_fields
      ];

      $requestData['fieldData'] = json_encode($fieldData, JSON_FORCE_OBJECT);
      $requestData['url'] = sprintf("/files/%s",
        $media->getSource()->getMetadata($media, 'gredi_asset_id'));

      return $requestData;
    }

    // This is the case of initial upload.
    // The inputs vary by the location from where the upload is being done.
    $meta_fields = $inputs ? $media->getSource()->prepareMetafieldsUpload($is_update, $media, $inputs) : NULL;
    // Get the mime type of the file.
    $fid = $media->get($media->getSource()->getConfiguration()['source_field'])->target_id;
    $file = File::load($fid);

    $fieldData = [
      "name" => basename($file->getFileUri()),
      "fileType" => "nt:file",
      "propertiesById" => [],
      "metaById" => $meta_fields
    ];

    $requestData['fieldData'] = json_encode($fieldData, JSON_FORCE_OBJECT);
    $requestData['file'] = base64_encode(file_get_contents($file->getFileUri()));
    $requestData['mime'] = $file->getMimeType();

    return $requestData;
  }


}
