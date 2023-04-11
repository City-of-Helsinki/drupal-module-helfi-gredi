<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\helfi_gredi\GrediAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Gredi module configuration form.
 */
class GrediConfigForm extends ConfigFormBase {

  /**
   * Client interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Auth service.
   *
   * @var \Drupal\helfi_gredi\GrediAuthService
   */
  protected $authService;

  /**
   * GrediConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client.
   * @param \Drupal\helfi_gredi\GrediAuthService $authService
   *   Auth service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, GrediAuthService $authService) {
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
      $container->get('helfi_gredi.auth_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gredi_config';
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('helfi_gredi.settings');

    $form['auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Authentication'),
    ];

    $form['auth']['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('api_url'),
      '#description' => $this->t('The base URL for the API v1. ex: https://api4.domain.net/api/v1 .
      Can be defined with env var GREDI_API_URL.'),
      '#required' => TRUE,
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Can be defined with env var GREDI_USERNAME.'),
      '#default_value' => $config->get('username'),
      '#required' => TRUE,
    ];

    $form['auth']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Can be defined with env var GREDI_PASSWORD.'),
      '#default_value' => $config->get('password'),
      '#required' => TRUE,
    ];

    $form['auth']['customer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer path'),
      '#default_value' => $config->get('customer'),
      '#description' => $this->t('Customer path based on which customer id is fetched. Can be defined with env var GREDI_CUSTOMER.'),
      '#required' => TRUE,
    ];

    $form['auth']['customer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer ID'),
      '#default_value' => $config->get('customer_id'),
      '#description' => $this->t('This will be fetched upon submission. Can be defined with env var GREDI_CUSTOMER_ID.'),
      '#disabled' => TRUE,
    ];

    $form['auth']['upload_folder_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload folder ID'),
      '#default_value' => $config->get('upload_folder_id'),
      '#description' => $this->t('The Gredi folder ID on which files will be uploaded. Can be defined with env var GREDI_UPLOAD_FOLDER_ID.'),
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

  /**
   * Submit handler for gredi config form.
   *
   * @param array $form
   *   Form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('helfi_gredi.settings');
    $customerId = $form_state->get('customerId');

    $config->set('api_url', $form_state->getValue('api_url'));
    $config->set('username', $form_state->getValue('username'));
    $config->set('password', $form_state->getValue('password'));
    $config->set('customer', $form_state->getValue('customer'));
    $config->set('upload_folder_id', $form_state->getValue('upload_folder_id'));
    $config->set('customer_id', $customerId);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
