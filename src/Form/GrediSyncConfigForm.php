<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @var LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * GrediSyncConfigForm constructor.
   *
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

  protected function getEditableConfigNames() {
    return [
      'helfi_gredi.settings'
    ];
  }

  public function getFormId() {
    return 'gredi_sync_config';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['sync'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Synchronize Gredi Assets with Gredi DAM'),
    ];

    $form['sync']['interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Sync interval'),
      '#default_value' => 24,
      '#min' => 6,
      '#field_suffix' => 'Hours',
      '#description' => $this->t('Choose the interval on which assets will be automatically synced.'
      . '<br>' . 'Defaults to 24 hours.'),
    ];
    $form['sync']['sync_button'] = [
      '#type' => 'submit',
      '#value' => 'Sync All Gredi Assets',
      '#submit' => ['::syncGrediAssets']
    ];
    $form['sync']['save_button'] = [
      '#type' => 'submit',
      '#value' => 'Save',
      '#weight' => 0
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->configFactory->getEditable('helfi_gredi.settings');
    $interval = $form_state->getValues()['interval'];
    $current_config = $config->get();
    $current_config['cron_interval'] = $interval;
    $config->setData($current_config);
    $config->save();

    \Drupal::messenger()->addMessage('Configuration successfully saved.');
  }

  /**
   * @return void
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
      \Drupal::messenger()->addStatus(t('Successfully synced ' . $count . ' assets'));

      // Store the last sync time to use it at cron.
      \Drupal::state()->set('helfi_gredi.last_run', \Drupal::time()->getCurrentTime());
    }
    catch(\Exception $e) {
      $this->loggerFactory->error(t('Error on syncing asset: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

}
