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
class GrediSyncForm extends FormBase {

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

    $form['asset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Gredi Asset'),
    ];
    $form['asset']['asset_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset ID'),
      '#default_value' => $media->id(),
      '#disabled' => TRUE
    ];
    $form['asset']['asset_sync'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync asset with Gredi API'),
    ];

    $form_state->set($media->getSource()->getPluginId(), $media);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, $media = NULL) {
    $media_id = $form_state->get($media->getSource()->getPluginId())->id();
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
