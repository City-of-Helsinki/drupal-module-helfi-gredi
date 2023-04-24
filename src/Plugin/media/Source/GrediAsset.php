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
   *
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
    if (!empty($media->gredi_folder)) {
      switch ($attribute_name) {
        case 'gredi_asset_id':
          return $media->get('gredi_asset_id')->value;

        case 'name':
          if (!empty($this->assetData['name'])) {
            return $this->assetData['name'];
          }
          return parent::getMetadata($media, 'default_name');

        case 'default_name':
          return parent::getMetadata($media, 'default_name');

        case 'thumbnail_uri':
          if (!$media->isNew()) {
            return parent::getMetadata($media, $attribute_name);
          }

          // @todo return a folder icon from the module maybe? instead of media default thumnail.
          $default_thumbnail_filename = $this->pluginDefinition['default_thumbnail_filename'];
          $fallback = $this->configFactory->get('media.settings')
              ->get('icon_base_uri') . '/' . $default_thumbnail_filename;
          return $fallback;
      }
      return NULL;
    }
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
            // @todo This might not be the best place to save.
            $media->set('gredi_removed', TRUE);
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
        $languages = $this->languageManager->getLanguages();
        $site_lang_codes = array_keys($languages);
        foreach ($site_lang_codes as $idx => $lang_code) {
          $correct_key = array_search($lang_code, $this->langMappingsCorrection);
          if ($correct_key !== FALSE) {
            $site_lang_codes[$idx] = $correct_key;
          }
        }
        $gredi_lang_codes = array_keys($this->assetData['namesByLang']);
        return array_intersect($gredi_lang_codes, $site_lang_codes);

      case 'lang_codes_corrected':
        $lang_codes = array_keys($this->assetData['namesByLang']);
        foreach ($lang_codes as $idx => $lang_code) {
          if (isset($this->langMappingsCorrection[$lang_code])) {
            $lang_codes[$idx] = $this->langMappingsCorrection[$lang_code];
          }
        }
        $languages = $this->languageManager->getLanguages();
        $site_lang_codes = array_keys($languages);
        return array_intersect($lang_codes, $site_lang_codes);

      // @todo should we get other fields like description?
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
        // @todo figure out a way that when creating a new translation in drupal
        // the Gredi value, from the table from
        // Gredi Asset tab to display correct value
        // as it is now it follows the source values
        // if the language is not avaialable in gredi.
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
          // API uses 'SE' lang code for Swedish,
          // so we use this hardcoded mapping.
          if (isset($this->langMappingsCorrection[$attr_lang_code])) {
            $attr_lang_code = $this->langMappingsCorrection[$attr_lang_code];
          }
          if ($attr_id != $attribute_name) {
            continue;
          }
          if ($attr_lang_code == $fallbackLangCode) {
            // @todo decide if we want to return default lang value for empty translations.
            $fallbackValue = $value;
          }
          if ($attr_lang_code != $lang_code) {
            continue;
          }

          return $value;
        }

        // @todo decide if we want to return default lang value for empty translations.
        // return $fallbackValue;
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
   * Retrieves the mapping of the Gredi meta fields.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return mixed
   *   Returns the mapping of the Gredi meta fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMetaFieldsMapping(MediaInterface $media) {
    $field_map = \Drupal::entityTypeManager()->getStorage('media_type')
      ->load($media->getSource()->getPluginId())->getFieldMap();

    $notMetaFields = ['original_file'];
    foreach ($notMetaFields as $notMetaField) {
      if (isset($field_map[$notMetaField])) {
        unset($field_map[$notMetaField]);
      }
    }

    return $field_map;
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
   *   The media entity.
   *
   * @return bool
   *   Returns true if the asset exists on gredi, false otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function syncMediaFromGredi(MediaInterface $media) : bool {
    // External asset modified timestamp.
    $external_field_modified = $media->getSource()->getMetadata($media, 'modified');
    if ($media->get('gredi_removed')->value) {
      $this->logger->warning($this->t('Gredi asset id @asset_id no longer found.', [
        '@asset_id' => $media->get('gredi_asset_id')->value,
      ]));
      return FALSE;
    }

    $field_map = $media->getSource()->getMetaFieldsMapping($media);

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
        $translation->set($field, NULL);
      }
      $translation->save();
    }
    $this->logger->notice($this->t('Synced metadata for Gredi asset id @id', ['@id' => $media->id()]));

    return TRUE;
  }

  /**
   * Creates the structure accepted by the API for sending meta fields.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param bool $is_update
   *   The operation being done. Initial upload or update asset.
   *
   * @return array
   *   The meta fields array.
   */
  public function getMetafieldsForUpdate(MediaInterface $media, bool $is_update = FALSE) {
    $field_map = $media->getSource()->getMetaFieldsMapping($media);

    if ($media->isNew() || !$is_update) {
      // We don't have asset data, so getMetadata lang_codes won't work.
      $apiLanguages = [$media->language()->getId()];
    }
    else {
      $apiLanguages = $media->getSource()->getMetadata($media, 'lang_codes');
    }
    $langMappingsCorrection = $media->getSource()->langMappingsCorrection;

    $meta_fields = [];

    foreach ($field_map as $key => $field) {
      foreach ($apiLanguages as $apiLanguage) {
        $drupalLanguage = $apiLanguage;
        if (array_key_exists($apiLanguage, $langMappingsCorrection)) {
          $drupalLanguage = $langMappingsCorrection[$apiLanguage];
        }
        if ($media->hasTranslation($drupalLanguage)) {
          $translated_media = $media->getTranslation($drupalLanguage);
          $meta_fields['custom:meta-field-' . $key . '_' . $apiLanguage] = $translated_media->get($field)->value;
        }
      }
    }

    return $meta_fields;
  }

  /**
   * Sends assets to Gredi API.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param bool $is_update
   *   The operation being done. Initial upload or update asset.
   *
   * @return string|null
   *   External Gredi asset id for initial upload
   *   Null in case of update existing asset.
   */
  public function sendAssetToGredi(MediaInterface $media, bool $is_update) {
    $requestData = [];

    $meta_fields = $media->getSource()->getMetafieldsForUpdate($media, $is_update);
    $fieldData = [
      "name" => $media->getName(),
      "propertiesById" => [],
      "metaById" => $meta_fields,
    ];

    // This is the case of syncing an asset.
    if ($is_update) {
      $requestData['assetId'] = $media->getSource()->getMetadata($media, 'gredi_asset_id');
    }
    else {
      $fid = $media->get($media->getSource()->getConfiguration()['source_field'])->target_id;
      $file = File::load($fid);

      $fieldData = [
        "name" => basename($file->getFileUri()),
        "fileType" => "nt:file",
        "propertiesById" => [],
        "metaById" => $meta_fields,
      ];
      $requestData['file'] = base64_encode(file_get_contents($file->getFileUri()));
      $requestData['mime'] = $file->getMimeType();
    }

    $requestData['fieldData'] = json_encode($fieldData, JSON_FORCE_OBJECT);

    return $this->grediClient->uploadImage($requestData, $is_update);
  }

}
