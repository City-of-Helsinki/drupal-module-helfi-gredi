<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config form for syncing Gredi assets with Gredi API.
 */
class GrediSyncConfigForm extends ConfigFormBase {

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
   * GrediSyncConfigForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManager $queueWorkerManager
   *   Queue worker service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(
    QueueWorkerManager $queueWorkerManager,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($config_factory);
    $this->queueWorkerManager = $queueWorkerManager;
    $this->loggerFactory = $loggerChannelFactory->get('helfi_gredi');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_worker'),
      $container->get('logger.factory'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'helfi_gredi.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gredi_sync_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('helfi_gredi.settings');

    $form['sync'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Synchronize local data from Gredi DAM during cron'),
    ];

    $form['sync']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('sync.enabled') ?? FALSE,
    ];

    $form['sync']['interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Interval'),
      '#default_value' => $config->get('sync.interval') ?? 24,
      '#min' => 6,
      '#step' => 1,
      '#field_suffix' => 'Hours',
      '#description' => $this->t('Interval on which all local assets marked with autosync will be synced from Gredi.'),
    ];

    $form['sync']['save_button'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    $form['sync']['sync_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync All Gredi Assets'),
      '#submit' => ['::syncGrediAssets'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('helfi_gredi.settings');
    $values['interval'] = $form_state->getValue('interval', 24);
    $values['enabled'] = $form_state->getValue('enabled', FALSE);
    $config->set('sync', $values);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit function for syncing multiple gredi assets.
   */
  public function syncGrediAssets() {

    try {
      /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
      $query = \Drupal::entityQuery('media')
        ->condition('bundle', 'gredi_asset');
      /** @var array $results */
      $results = $query->execute();
      $queue_worker = $this->queueWorkerManager->createInstance('gredi_asset_update');
      $count = 0;
      foreach ($results as $value) {
        $count++;
        $queue_worker->processItem($value);
      }
      \Drupal::messenger()->addStatus($this->t('Successfully synced @count assets'), [
        '@count' => $count,
      ]);

      // Store the last sync time to use it at cron.
      \Drupal::state()->set('helfi_gredi.last_run', \Drupal::time()->getCurrentTime());
    }
    catch (\Exception $e) {
      $this->loggerFactory->error(t('Error on syncing asset: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

}
