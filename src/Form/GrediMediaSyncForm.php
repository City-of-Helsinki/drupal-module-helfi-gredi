<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\file\Entity\File;
use Drupal\helfi_gredi\GrediClient;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync class for synchronizing Gredi Asset with Gredi API.
 */
class GrediMediaSyncForm extends FormBase {

  /**
   * Queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueWorkerManager;

  /**
   * Logger channel.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The Gredi client service.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  protected $grediClient;

  /**
   * GrediSyncForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManager $queueWorkerManager
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   * @param \Drupal\helfi_gredi\GrediClient $grediClient
   */
  public function __construct(
    QueueWorkerManager $queueWorkerManager,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    GrediClient $grediClient
  ) {
    $this->queueWorkerManager = $queueWorkerManager;
    $this->loggerFactory = $loggerChannelFactory->get('helfi_gredi');
    $this->grediClient = $grediClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_worker'),
      $container->get('logger.factory'),
      $container->get('helfi_gredi.dam_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gredi_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $media = NULL) {
    /** @var $media \Drupal\media\MediaInterface */
    $form['asset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Gredi Asset'),
    ];
    $form['asset']['asset_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Gredi asset ID'),
      '#markup' => $media->getSource()->getMetadata($media, 'gredi_asset_id'),
    ];

    $table_header = [
        $this->t('Field'),
        $this->t('Language'),
        $this->t('Drupal value'),
        $this->t('Gredi value')
    ];
    $table_rows = [];

    $bundle = $media->getEntityType()->getBundleEntityType();
    $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();
    // TODO should we show all languages here, or leave as it is with current language?
    // TODO depends on if we sync from gredi / to gredi all languages - now it seems that this is what we are doing
    // TODO
    // $translated_langs = $media->getTranslationLanguages();
    foreach ($field_map as $key => $field) {
      if ($key === 'original_file') {
        continue;
      }
      if (!$media->hasField($field)) {
        continue;
      }
      $table_rows[] = [
        $media->get($field)->getFieldDefinition()->getLabel(),
        $media->language()->getName(),
        $media->get($field)->getString(),
        $media->getSource()->getMetadata($media, $key),
      ];
    }
    $form['asset']['fields'] = [
      '#theme' => 'table',
      '#title' => $this->t('Metadata'),
      '#header' => $table_header,
      '#rows' => $table_rows,
    ];

    $form['asset']['asset_pull'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync asset from Gredi'),
    ];

    $form['asset']['asset_push'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync asset into Gredi'),
      '#submit' => ['::syncAssetToGredi'],
    ];

    if (!$media->get('active_external_asset')->value) {
      \Drupal::messenger()->addWarning(t('External asset no longer exists.'));
      $form['asset']['asset_pull']['#disabled'] = TRUE;
      $form['asset']['asset_push']['#disabled'] = TRUE;
    }

    $form_state->set('media_id', $media->id());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $media_id = $form_state->get('media_id');
    try {
      $queue_worker = $this->queueWorkerManager->createInstance('gredi_asset_update');
      $queue_worker->processItem($media_id);
    }
    catch(\Exception $e) {
      $this->loggerFactory->error(t('Error on syncing asset: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Sending updated information of an asset to Gredi API.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function syncAssetToGredi(array &$form, FormStateInterface $form_state) {
      /** @var \Drupal\media\MediaInterface $media */
      $media = Media::load($form_state->getStorage()['media_id']);

      if ($media->get('active_external_asset')->value === FALSE) {
        // TODO : disable the buttons for sync to gredi and from gredi.
        $this->messenger()->addWarning(
          'This asset no longer corresponds with any Gredi asset ID.
          Please re-fetch the asset using media library.');
        return;
      }

      $bundle = $media->getEntityType()->getBundleEntityType();
      $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
        ->load($media->getSource()->getPluginId())->getFieldMap();

      $inputs = [];
      $apiLanguages = $media->getSource()->getMetadata($media, 'lang_codes');
      $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();

      foreach ($field_map as $key => $field) {
        if ($key === 'original_file') {
          continue;
        }
        foreach ($apiLanguages as $apiLanguage) {
          if ($apiLanguage === 'se') {
            $apiLanguage = 'sv';
          }
          if ($apiLanguage === $currentLanguage) {
            $inputs[$apiLanguage][$field] = $media->$field->value;
            continue;
          }
          if ($media->hasTranslation($apiLanguage)) {
            $translated_media = $media->getTranslation($apiLanguage);
            $inputs[$apiLanguage][$field] = $translated_media->$field->value;
          }
        }
      }
      try {
        $fid = $media->field_media_image->target_id;
        $file = File::load($fid);
        $this->grediClient->uploadImage($file, $inputs, $media, 'PUT', TRUE);
        \Drupal::messenger()->addStatus(t('Asset successfully updated.'));
      }
      catch(\Exception $e) {
        \Drupal::messenger()->addError(t('Asset was not updated. Check logs.'));
        \Drupal::logger('helfi_gredi')->error(t('@error', [
          '@error' => $e->getMessage(),
        ]));
      }
    }

}
