<?php

namespace Drupal\helfi_gredi_image\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\helfi_gredi_image\Service\AssetImageHelper;
use Drupal\helfi_gredi_image\Service\AssetMediaFactory;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;
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
   * The asset that we're going to render details for.
   *
   * @var \Drupal\helfi_gredi_image\Entity\Asset|null
   */
  protected $currentAsset;

  /**
   * Gredi DAM asset image helper service.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetImageHelper
   */
  protected $assetImageHelper;

  /**
   * Gredi DAM asset metadata helper service.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetMetadataHelper
   */
  protected $assetMetadataHelper;

  /**
   * Gredi DAM Asset Media Factory service.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetMediaFactory
   */
  protected $assetMediaFactory;

  protected $assetData = [];

  /** @var ImageFactory */
  protected $imageFactory;

  /** @var FileSystemInterface */
  protected $fileSystem;

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
    AssetImageHelper $assetImageHelper,
    AssetMetadataHelper $assetMetadataHelper,
    AssetMediaFactory $assetMediaFactory,
    ImageFactory $imageFactory,
    FileSystemInterface $fileSystem) {
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

    $this->assetImageHelper = $assetImageHelper;
    $this->assetMetadataHelper = $assetMetadataHelper;
    $this->assetMediaFactory = $assetMediaFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Fieldset with configuration options not needed.
    hide($form);
    return $form;
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
      $container->get('helfi_gredi_image.asset_image.helper'),
      $container->get('helfi_gredi_image.asset_metadata.helper'),
      $container->get('helfi_gredi_image.asset_media.factory'),
      $container->get('image.factory'),
      $container->get('file_system')
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $submitted_config = array_intersect_key(
      $form_state->getValues(),
      $this->configuration
    );

    foreach ($submitted_config as $config_key => $config_value) {
      $this->configuration[$config_key] = $config_value;
    }

    // For consistency, always use the default source_field field name.
    $default_field_name = $this->defaultConfiguration()['source_field'];
    // Check if it already exists so it can be used as a shared field.
    $storage = $this->entityTypeManager->getStorage('field_storage_config');
    $existing_source_field = $storage->load('media.' . $default_field_name);

    // Set or create the source field.
    if ($existing_source_field) {
      // If the default field already exists, return the default field name.
      $this->configuration['source_field'] = $default_field_name;
    }
    else {
      // Default source field name does not exist, so create a new one.
      $field_storage = $this->createSourceFieldStorage();
      $field_storage->save();
      $this->configuration['source_field'] = $field_storage->getName();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createSourceFieldStorage() {
    $default_field_name = $this->defaultConfiguration()['source_field'];

    // Create the field.
    return $this->entityTypeManager->getStorage('field_storage_config')->create(
      [
        'entity_type' => 'media',
        'field_name' => $default_field_name,
        'type' => reset($this->pluginDefinition['allowed_field_types']),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return $this->assetMetadataHelper->getMetadataAttributeLabels();
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
    switch ($attribute_name) {
      case 'name':
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
        if (empty($this->assetData)) {
          $default_thumbnail_filename = $this->pluginDefinition['default_thumbnail_filename'];
          return $this->configFactory->get('media.settings')->get('icon_base_uri') . '/' . $default_thumbnail_filename;
        }
        // Fetching asset thumbnail or from local.
        try {
          $assetId = $this->assetData['id'];
          $assetName = $this->assetData['name'];
          $assetModified = $this->assetData['modified'];
          // Create subfolders by month.
          $current_timestamp = \Drupal::time()->getCurrentTime();
          $date_output = \Drupal::service('date.formatter')->format($current_timestamp, 'custom', 'd/M/Y');
          $date = str_replace('/', '-', substr($date_output, 3, 8));

          // Asset name contains id and last updated date.
          $asset_name = $assetId . '_' . strtotime($assetModified) . substr($assetName, strrpos($assetName, "."));
          $directory = 'public://gredidam/thumbs/' . $date;
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          $location = $directory . '/' .$asset_name;
          if (!file_exists($location)) {
            /** @var \Drupal\helfi_gredi_image\Service\GrediDamClient $client */
            $client = \Drupal::service('helfi_gredi_image.dam_client');
            $fileContent = $client->getFileContent($assetId, $this->assetData['apiPreviewLink']);
            $this->fileSystem->saveData($fileContent, $location, FileSystemInterface::EXISTS_REPLACE);
          }

          return $location;
        }
        catch (\Exception $e) {
          $this->messenger()->addError('Error fetching and saving thumbnail');
          return '';
        }

      case 'original_file':
//        if (!$media->isNew()) {
//          return $media->get($this->configuration['source_field'])->entity;
//        }

        return $this->getOriginalFile();
    }

    // TODO add more cases for metadata !?
    //        case 'keywords':
    //      case 'alt_text':
    //      case 'created':
    //      case 'modified':


    // TODO - refactor this !?
    if ($this->currentAsset === NULL) {
      $asset = $this->assetMediaFactory->get($media)->getAsset();
      $this->currentAsset = $asset;
    }

    // If we don't have the asset, we can't return additional metadata.
    if ($this->currentAsset === NULL) {
      return NULL;
    }

    return $this->assetMetadataHelper->getMetadataFromAsset($this->currentAsset, $attribute_name);
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

  public function getAssetData() {
    return $this->assetData;
  }


  public function getOriginalFile() {
    if (empty($this->assetData)) {
      return NULL;
    }
    try {
      $assetId = $this->assetData['id'];
      $assetName = $this->assetData['name'];
      $assetModified = $this->assetData['modified'];
      /** @var \Drupal\helfi_gredi_image\Service\GrediDamClient $service */
      $client = \Drupal::service('helfi_gredi_image.dam_client');
      $fileContent = $client->getFileContent($assetId, $this->assetData['apiContentLink']);

      // Create subfolders by month.
      $current_timestamp = \Drupal::time()->getCurrentTime();
      $date_output = \Drupal::service('date.formatter')->format($current_timestamp, 'custom', 'd/M/Y');
      $date = str_replace('/', '-', substr($date_output, 3, 8));

      // Create month folder.
      $directory = 'public://gredidam/original/' . $date;

      $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $location = $directory . '/' . $assetName;


      /** @var \Drupal\file\FileRepositoryInterface $file_repository */
      $file_repository = \Drupal::service('file.repository');
      $file = $file_repository->writeData($fileContent, $location);

      return $file;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}


