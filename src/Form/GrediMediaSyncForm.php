<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerManager;
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
   * GrediSyncForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManager $queueWorkerManager
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   */
  public function __construct(
    QueueWorkerManager $queueWorkerManager,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->queueWorkerManager = $queueWorkerManager;
    $this->loggerFactory = $loggerChannelFactory->get('helfi_gredi');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_worker'),
      $container->get('logger.factory')
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
    ];

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
      \Drupal::messenger()->addStatus(t('Gredi Asset synced successfully.'));
    }
    catch(\Exception $e) {
      $this->loggerFactory->error(t('Error on syncing asset: @error', [
        '@error' => $e->getMessage(),
      ]));
    }

  }

}
