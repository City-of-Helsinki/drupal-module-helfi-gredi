<?php

namespace Drupal\helfi_gredi_image\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\helfi_gredi_image\Service\AssetImageHelper;
use Drupal\helfi_gredi_image\Service\AssetMediaFactory;
use Drupal\helfi_gredi_image\Service\AssetMetadataHelper;
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
class GredidamAsset extends MediaSourceBase {

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
    AssetMediaFactory $assetMediaFactory) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory
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
      $container->get('helfi_gredi_image.asset_media.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => 'gredi_asset_id',
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
//    switch ($attribute_name) {
//      case 'default_name':
//        return 'media:' . $media->bundle() . ':' . $media->uuid();
//
//      case 'thumbnail_uri':
//        $default_thumbnail_filename = $this->pluginDefinition['default_thumbnail_filename'];
//        return $this->configFactory->get('media.settings')->get('icon_base_uri') . '/' . $default_thumbnail_filename;
//    }
//
//    [$asset_id, $version_id] = array_values($this->getSourceFieldValue($media));
//
//    if (empty($asset_id)) {
//      return NULL;
//    }
//    if ($version_id === NULL) {
//      $version_id = '';
//    }
//
//    $asset = $this->assetData;
//    if ($asset === []) {
//      try {
//        $asset = $this->clientFactory->getSiteClient()->getAsset($asset_id, $version_id);
//      }
//      catch (\Exception $exception) {
//        $this->damLoggerChannel->error(sprintf(
//            'Following error occurred while trying to get asset from dam. Asset: %s, error: %s',
//            $asset_id,
//            $exception->getMessage()
//          )
//        );
//        return NULL;
//      }
//    }

    switch ($attribute_name) {
      case 'name':
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        // TODO if media exists

        if (!empty($this->assetData)) {
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
            // Create month folder.
            /** @var \Drupal\Core\File\FileSystemInterface $file_service */
            $file_service = \Drupal::service('file_system');

            $directory = sprintf('public://gredidam/thumbs/' . $date);

            $file_service->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

            $location = sprintf('public://gredidam/thumbs/%s/%s', $date, $asset_name);
            /** @var \Drupal\helfi_gredi_image\Service\GrediDamClient $service */
            $client = \Drupal::service('helfi_gredi_image.dam_client');
            $fileContent = $client->getFileContent($assetId, $this->assetData['apiPreviewLink']);

            /** @var \Drupal\Core\File\FileUrlGeneratorInterface $url_generator */
            $url_generator = \Drupal::service('file_url_generator');
            //          $url = $url_generator->generate($location)->toString();
            if (!file_exists($location)) {
              $file_service->saveData($fileContent, $location, FileSystemInterface::EXISTS_REPLACE);
            }

            return $location;
          }
          catch (\Exception $e) {
            return '';
          }


        }
        else {
          return '';
        }
        return $this->assetImageHelper->getThumbnail(
          $this->assetMediaFactory->get($media)->getFile('field_media_image', 'target_id')
        );
    }
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

}


