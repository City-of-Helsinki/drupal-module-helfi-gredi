<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\helfi_gredi_image\DamClientInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetFileEntityHelper.
 *
 * Abstracts out primarily file entity and system file related functionality.
 */
class AssetFileEntityHelper implements ContainerInjectionInterface {

  /**
   * Entity Field Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Gredi DAM client.
   *
   * @var \Drupal\helfi_gredi_image\DamClientInterface
   */
  protected $damClient;

  /**
   * Gredi DAM factory for wrapping media entities.
   *
   * @var \Drupal\helfi_gredi_image\Service\AssetMediaFactory
   */
  protected $assetMediaFactory;

  /**
   * Gredi DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * AssetFileEntityHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field Manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal filesystem service.
   * @param \Drupal\Core\Utility\Token $token
   *   Drupal token service.
   * @param \Drupal\helfi_gredi_image\DamClientInterface $damClient
   *   Gredi DAM client.
   * @param \Drupal\helfi_gredi_image\Service\AssetMediaFactory $assetMediaFactory
   *   Gredi DAM Asset Media Factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(
    EntityFieldManagerInterface $entityFieldManager,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    Token $token,
    DamClientInterface $damClient,
    AssetMediaFactory $assetMediaFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->damClient = $damClient;
    $this->assetMediaFactory = $assetMediaFactory;
    $this->loggerChannel = $loggerChannelFactory->get('media_gredidam');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('helfi_gredi_image.dam_client'),
      $container->get('helfi_gredi_image.asset_media.factory'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Get a destination uri from the given entity and field combo.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check the field configuration on.
   * @param string $fileField
   *   The name of the file field.
   *
   * @return string
   *   The uri to use. Defaults to public://gredidam_assets
   */
  public function getDestinationFromEntity(EntityInterface $entity, $fileField) {
    $scheme = $this->configFactory->get('system.file')->get('default_scheme');
    $file_directory = 'gredidam_assets';

    // Load the field definitions for this bundle.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    if (!empty($field_definitions[$fileField])) {
      $definition = $field_definitions[$fileField]->getItemDefinition();
      $scheme = $definition->getSetting('uri_scheme');
      $file_directory = $definition->getSetting('file_directory');
    }

    // Replace the token for file directory.
    if (!empty($file_directory)) {
      $file_directory = $this->token->replace($file_directory);
    }

    return sprintf('%s://%s', $scheme, $file_directory);
  }

  /**
   * Creates a new file for an asset.
   *
   * @param \Drupal\helfi_gredi_image\Entity\Asset $asset
   *   The asset to save a new file for.
   * @param string $destination_folder
   *   The path to save the asset into.
   *
   * @return bool|\Drupal\file\FileInterface
   *   The created file or FALSE on failure.
   */
  public function createNewFile(Asset $asset, $destination_folder) {
    // Ensure we can write to our destination directory.
    if (!$this->fileSystem
      ->prepareDirectory($destination_folder, FileSystemInterface::CREATE_DIRECTORY)) {
      $this->loggerChannel->warning(
        'Unable to save file for asset ID @asset_id on directory @destination_folder.', [
          '@asset_id' => $asset->external_id,
          '@destination_folder' => $destination_folder,
        ]
      );
      return FALSE;
    }

    // By default, we use the filename attribute as the file name. However,
    // because the actual file format may differ than the file name (specially
    // for the images which are downloaded as png), we pass the filename
    // as a parameter so it can be overridden.
    $filename = $asset->name;
    $file_contents = $this->damClient->fetchRemoteAssetData($asset, $filename);
    if ($file_contents === FALSE) {
      return FALSE;
    }

    $destination_path = sprintf('%s/%s', $destination_folder, $filename);

    $file = $this->drupalFileSaveData($file_contents, $destination_path);

    if ($file instanceof FileInterface) {
      return $file;
    }

    $this->loggerChannel->warning(
      'Unable to save file for asset ID @asset_id.', [
        '@asset_id' => $asset->external_id,
      ]
    );

    return FALSE;
  }

  /**
   * Gets a new filename for an asset based on the URL returned by the DAM.
   *
   * @param string $destination
   *   The destination folder the asset is being saved to.
   * @param string $uri
   *   The URI that was returned by the DAM API for the asset.
   * @param string $original_name
   *   The original asset filename.
   *
   * @return string
   *   The updated destination path with the new filename.
   */
  protected function getNewDestinationByUri($destination, $uri, $original_name) {
    $path = parse_url($uri, PHP_URL_PATH);
    $path = basename($path);
    $ext = pathinfo($path, PATHINFO_EXTENSION);

    $base_file_name = pathinfo($original_name, PATHINFO_FILENAME);
    return sprintf('%s/%s.%s', $destination, $base_file_name, $ext);
  }

  /**
   * Replaces the binary contents of the given file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity to replace the binary contents of.
   * @param mixed $data
   *   The contents to save.
   * @param string $destination
   *   The destination uri to save to.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity that was updated.
   */
  protected function replaceExistingFile(FileInterface $file, $data, $destination) {
    $uri = $this->fileSystem->saveData($data, $destination, FileSystemInterface::EXISTS_REPLACE);
    $file->setFileUri($uri);
    $file->setFilename($this->fileSystem->basename($destination));
    $file->save();

    return $file;
  }

  /**
   * Saves a file to the specified destination and creates a database entry.
   *
   * This method exists so the functionality can be overridden in unit tests.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string|null $destination
   *   (optional) A string containing the destination URI. This must be a stream
   *   wrapper URI. If no value or NULL is provided, a randomized name will be
   *   generated and the file will be saved using Drupal's default files scheme,
   *   usually "public://".
   *
   * @return \Drupal\file\FileInterface|false
   *   A file entity, or FALSE on error.
   */
  public function drupalFileSaveData($data, $destination = NULL) {
    /** @var \Drupal\file\FileRepositoryInterface $file_repository */
    $file_repository = \Drupal::service('file.repository');
    return $file_repository->writeData($data, $destination, FileSystemInterface::EXISTS_REPLACE);
  }

}
