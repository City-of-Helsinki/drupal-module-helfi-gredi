<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Queue\QueueWorkerManager;
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
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Gredi client service.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  protected $grediClient;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeManager;

  /**
   * GrediMediaSyncForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManager $queueWorkerManager
   *   Queue worker service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\helfi_gredi\GrediClient $grediClient
   *   Gredi client service.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   Language service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger service.
   * @param \Drupal\Component\Datetime\TimeInterface $timeManager
   *   Time manager service.
   */
  public function __construct(
    QueueWorkerManager $queueWorkerManager,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    EntityTypeManagerInterface $entityTypeManager,
    GrediClient $grediClient,
    LanguageManager $languageManager,
    Messenger $messenger,
    TimeInterface $timeManager
  ) {
    $this->queueWorkerManager = $queueWorkerManager;
    $this->loggerFactory = $loggerChannelFactory->get('helfi_gredi');
    $this->entityTypeManager = $entityTypeManager;
    $this->grediClient = $grediClient;
    $this->languageManager = $languageManager;
    $this->messenger = $messenger;
    $this->timeManager = $timeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_worker'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('helfi_gredi.dam_client'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('datetime.time')
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
    /** @var \Drupal\media\MediaInterface $media */
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
      $this->t('Gredi value'),
    ];
    $table_rows = [];

    $field_map = $media->getSource()->getMetaFieldsMapping($media);

    $gredi_lang_codes = $media->getSource()->getMetadata($media, 'lang_codes_corrected') ?? [];
    $site_languages = \Drupal::languageManager()->getLanguages();
    foreach ($site_languages as $language) {
      if (!in_array($language->getId(), $gredi_lang_codes)) {
        continue;
      }
      try {
        /** @var \Drupal\media\MediaInterface $mediaTrans */
        $mediaTrans = $media->getTranslation($language->getId());
        $temporaryTrans = FALSE;
      }
      catch (\Exception $e) {
        $mediaTrans = $media->addTranslation($language->getId());
        $temporaryTrans = TRUE;
      }

      $field_map['name'] = 'name';
      foreach ($field_map as $key => $field) {
        if (!$media->hasField($field)) {
          continue;
        }
        $table_rows[] = [
          $mediaTrans->get($field)->getFieldDefinition()->getLabel(),
          $mediaTrans->language()->getName(),
          $mediaTrans->get($field)->getString(),
          $mediaTrans->getSource()->getMetadata($mediaTrans, $key),
        ];
      }
      if ($temporaryTrans) {
        $media->removeTranslation($language->getId());
      }
    }

    $form['asset']['fields'] = [
      '#theme' => 'table',
      '#title' => $this->t('Metadata'),
      '#header' => $table_header,
      '#rows' => $table_rows,
    ];

    $user = \Drupal::currentUser();
    $form['asset']['asset_pull'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync asset from Gredi'),
      '#access' => $user->hasPermission('sync from gredi'),
    ];

    $form['asset']['asset_push'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync asset into Gredi'),
      '#submit' => ['::syncAssetToGredi'],
      '#access' => $user->hasPermission('sync to gredi'),
    ];

    if ($media->get('gredi_removed')->value) {
      if (empty($form_state->getUserInput())) {
        \Drupal::messenger()->addWarning(t('Gredi remote asset no longer exists.'));
      }
      $form['asset']['asset_pull']['#disabled'] = TRUE;
      $form['asset']['asset_push']['#value'] = $this->t('Upload asset into Gredi');
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
      $media = Media::load($media_id);
      // @todo Should add try catch because in case of catch in getMetadata we will still print the message.
      if ($media->getSource()->syncMediaFromGredi($media)) {
        $this->messenger()->addStatus($this->t('All field translations were synced from Gredi.'));
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error(t('Error on syncing asset: @error', [
        '@error' => $e->getMessage(),
      ]));
      $this->messenger()->addError($this->t('Failed to sync. Please check the logs.'));
    }
  }

  /**
   * Sending updated information of an asset to Gredi API.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function syncAssetToGredi(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\media\MediaInterface $media */
    $media = Media::load($form_state->getStorage()['media_id']);

    $is_update = empty($media->get('gredi_removed')->value);
    try {
      $assetId = $media->getSource()->sendAssetToGredi($media, $is_update);
      if (!$is_update && $assetId) {
        $media->set('gredi_asset_id', $assetId);
        $media->set('gredi_modified', $this->timeManager->getRequestTime());
        $media->set('gredi_removed', FALSE);
        $media->save();
        $this->messenger->addWarning('In order for all translations fields to be sent a new sync into Gredi is required.');
      }
      $this->messenger->addStatus(t('Asset successfully updated.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError(t('Asset was not updated. Check logs.'));
      $this->loggerFactory->error(t('@error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

}
