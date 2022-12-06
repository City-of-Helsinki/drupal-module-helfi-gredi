<?php

namespace Drupal\helfi_gredi_image\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
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
   * GrediDamConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    parent::__construct($config_factory);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client')
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

    $form['auth']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('domain'),
      '#description' => $this->t('The base URL for the API v1. ex: https://api4.domain.net/api/v1/'),
      '#required' => TRUE,
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('user'),
      '#required' => TRUE,
    ];

    $form['auth']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Gredi DAM Password'),
      '#default_value' => $config->get('pass'),
      '#required' => TRUE,
    ];

    $form['auth']['customer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer path'),
      '#default_value' => $config->get('customer'),
      '#description' => $this->t('Customer path based on which customer id is fetched.'),
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
      '#value' => $this->t('Save DAM configuration'),
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
    // Validations for the form input values.
    $domain = Xss::filter($form_state->getValue('domain'));
    if (!$domain) {
      $form_state->setErrorByName(
        'domain',
        $this->t('Provided domain is not valid.')
      );
      return;
    }

    $user = Xss::filter($form_state->getValue('username'));
    if (!$user) {
      $form_state->setErrorByName(
        'username',
        $this->t('Provided username is not valid.')
      );
      return;
    }

    $pass = Xss::filter($form_state->getValue('password'));
    if (!$pass) {
      $form_state->setErrorByName(
        'password',
        $this->t('Provided password is not valid.')
      );
      return;
    }

    $customer = Xss::filter($form_state->getValue('customer'));
    if (!$customer) {
      $form_state->setErrorByName(
        'customer',
        $this->t('Provided customer name is not valid.')
      );
      return;
    }


    // Validation for the API login.
    // Set the configuration for authentication.
//    $this->config('helfi_gredi_image.settings')
//      ->set('domain', $form_state->getValue('domain_value'))
//      ->set('user', $form_state->getValue('drupal_gredidam_user'))
//      ->set('pass', $form_state->getValue('drupal_gredidam_password'))
//      ->set('client', $form_state->getValue('drupal_gredidam_client'))
//      ->set('num_assets_per_page', $form_state->getValue('num_assets_per_page'))
//      ->save();

    // Retrieve client ID based on customer ID(name) from API.
    /** @var \Drupal\helfi_gredi_image\Service\GrediDamAuthService $auth_service */
    $auth_service = \Drupal::service('helfi_gredi_image.auth_service');

    $auth_service->setUsername($user);
    $auth_service->setPassword($pass);
    $auth_service->setCustomer($customer);
    // Clear existing customer id to fetch new one.
    $auth_service->setCustomerId('');

    try {
      $auth_service->authenticate();
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'username',
        $this->t('Authentication failed - @error', ['@error' => $e->getMessage()])
      );

      return;
    }

    try {
      $customerId = $auth_service->getCustomerId();
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'username',
        $this->t('Customer id fetching failed - @error', ['@error' => $e->getMessage()])
      );

      return;
    }

    $form_state->set('customerId', $customerId);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $customerId = $form_state->get('customerId');

    $this->config('helfi_gredi_image.settings')
      ->set('domain', $form_state->getValue('domain'))
      ->set('user', $form_state->getValue('username'))
      ->set('pass', $form_state->getValue('password'))
      ->set('customer', $form_state->getValue('customer'))
      ->set('customer_id', $customerId)
      ->set('num_assets_per_page', $form_state->getValue('num_assets_per_page'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
