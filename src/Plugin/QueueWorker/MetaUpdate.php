<?php

namespace Drupal\helfi_gredi\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "gredi_asset_update",
 *   title = @Translation("Updates Gredi image asset"),
 *   cron = {"time" = 90}
 * )
 */
class MetaUpdate extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  public LoggerChannel $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('helfi_gredi');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($media_id) {
    $media = Media::load($media_id);
    if (empty($media)) {
      return;
    }
    // External asset modified timestamp.
    $external_field_modified = $media->getSource()->getMetadata($media, 'modified');
    if ($media->get('gredi_removed')->value) {
      $this->logger->warning($this->t('Gredi asset id @asset_id no longer found.', [
        '@asset_id' => $media->get('gredi_asset_id')->value,
      ]));
      return;
    }
    if ($media->get('gredi_autosync')->value === 0) {
      return;
    }

    if (is_null($external_field_modified)) {
      throw new \Exception('Failed to retrieve asset data');
    }
    // Stored asset modified timestamp.
    $internal_field_modified = $media->get('gredi_modified')->value;
    $isModified = $external_field_modified > $internal_field_modified;

    $lang_codes = $media->getSource()->getMetadata($media, 'lang_codes_corrected');
    $hasNewTranslation = FALSE;
    foreach ($lang_codes as $lang_code) {

      if (!$media->hasTranslation($lang_code)) {
        $hasNewTranslation = TRUE;
        break;
      }
    }

    if ($hasNewTranslation || $isModified) {
      $media->getSource()->syncMediaFromGredi($media);
    }
  }

}
