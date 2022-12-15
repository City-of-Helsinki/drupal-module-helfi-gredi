<?php

namespace Drupal\helfi_gredi_image\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\helfi_gredi_image\Service\GrediDamAuthService;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Gredi DAM module configuration form.
 */
class GrediDamConfigForm extends ConfigFormBase {

  const NUM_ASSETS_PER_PAGE = 12;

  /**
   * Client interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Drupal\helfi_gredi_image\Service\GrediDamAuthService
   */
  protected $authService;

  /**
   * GrediDamConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, GrediDamAuthService $authService) {
    parent::__construct($config_factory);
    $this->httpClient = $http_client;
    $this->authService = $authService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('helfi_gredi_image.auth_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gredi_dam_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'helfi_gredi_image.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('helfi_gredi_image.settings');

    $form['auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Authentication'),
    ];

    $form['auth']['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('api_url'),
      '#description' => $this->t('The base URL for the API v1. ex: https://api4.domain.net/api/v1'),
      '#required' => TRUE,
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('username'),
      '#required' => TRUE,
    ];

    $form['auth']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('password'),
      '#required' => TRUE,
    ];

    $form['auth']['customer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer path'),
      '#default_value' => $config->get('customer'),
      '#description' => $this->t('Customer path based on which customer id is fetched.'),
      '#required' => TRUE,
    ];

    $form['auth']['customer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer ID'),
      '#default_value' => $config->get('customer_id'),
      '#description' => $this->t('This will be fetched upon submission.'),
      '#disabled' => TRUE,
    ];

    $form['auth']['upload_folder_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload folder ID'),
      '#default_value' => $config->get('upload_folder_id'),
      '#description' => $this->t('The Gredi folder ID on which files will be uploaded.'),
      '#required' => TRUE,
    ];

    $form['entity_browser'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Gredi DAM entity browser settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['entity_browser']['num_assets_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Assets per page'),
      '#default_value' => $config->get('num_assets_per_page') ?? self::NUM_ASSETS_PER_PAGE,
      '#description' => $this->t(
        'The number of assets to be shown per page in the entity browser can be set using this field. Default is set to @num_assets_per_page assets.',
        [
          '@num_assets_per_page' => self::NUM_ASSETS_PER_PAGE,
        ]
      ),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Validate that the provided values are valid or nor.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state instance.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate authentication and fetch customer id.
    $this->authService->apiUrl = $form_state->getValue('api_url');
    $this->authService->username = $form_state->getValue('username');
    $this->authService->password = $form_state->getValue('password');
    $this->authService->customer = $form_state->getValue('customer');
    $this->authService->uploadFolder = $form_state->getValue('upload_folder_id');
    // Clear existing customer id to fetch new one.
    $this->authService->customerId = '';

    try {
      $this->authService->authenticate();
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'username',
        $this->t('Authentication failed - @error', ['@error' => $e->getMessage()])
      );
      return;
    }

    try {
      $customerId = $this->authService->getCustomerId();
      $form_state->set('customerId', $customerId);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'username',
        $this->t('Customer id fetching failed - @error', ['@error' => $e->getMessage()])
      );
      return;
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $customerId = $form_state->get('customerId');

    $this->config('helfi_gredi_image.settings')->setData(
      [
        'api_url' => $form_state->getValue('api_url'),
        'username' => $form_state->getValue('username'),
        'password' => $form_state->getValue('password'),
        'customer' => $form_state->getValue('customer'),
        'upload_folder_id' => $form_state->getValue('upload_folder_id'),
        'customer_id' => $customerId,
        'num_assets_per_page' => $form_state->getValue('num_assets_per_page'),
      ]
    )
    ->save();
    parent::submitForm($form, $form_state);
  }

}
