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

    $form['domain'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Base URL detail'),
    ];

    $form['domain']['domain_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gredi DAM Base URL'),
      '#default_value' => $config->get('domain'),
      '#description' => $this->t('example: demo.gredidam.fi'),
      '#required' => TRUE,
    ];

    $form['drupal_auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Gredi DAM Drupal Account'),
    ];

    $form['drupal_auth']['drupal_gredidam_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Gredi DAM Username'),
      '#default_value' => $config->get('user'),
      '#description' => $this->t('drupaluser'),
      '#required' => TRUE,
    ];

    $form['drupal_auth']['drupal_gredidam_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Gredi DAM Password'),
      '#default_value' => $config->get('pass'),
      '#description' => $this->t('passexample'),
      '#required' => TRUE,
    ];

    $form['drupal_auth']['drupal_gredidam_client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Gredi DAM Client Name'),
      '#default_value' => $config->get('client'),
      '#description' => $this->t('clientName'),
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
    $domain = Xss::filter($form_state->getValue('domain_value'));
    if (!$domain) {
      $form_state->setErrorByName(
        'domain_value',
        $this->t('Provided domain is not valid.')
      );
      return;
    }

    $user = Xss::filter($form_state->getValue('drupal_gredidam_user'));
    if (!$user) {
      $form_state->setErrorByName(
        'drupal_gredidam_user',
        $this->t('Provided username is not valid.')
      );
      return;
    }

    $pass = Xss::filter($form_state->getValue('drupal_gredidam_password'));
    if (!$pass) {
      $form_state->setErrorByName(
        'drupal_gredidam_password',
        $this->t('Provided password is not valid.')
      );
      return;
    }

    $client = Xss::filter($form_state->getValue('drupal_gredidam_client'));
    if (!$client) {
      $form_state->setErrorByName(
        'drupal_gredidam_client',
        $this->t('Provided client name is not valid.')
      );
      return;
    }
    // Validation for the API login.
    // Set the configuration for authentication.
    $this->config('helfi_gredi_image.settings')
      ->set('domain', $form_state->getValue('domain_value'))
      ->set('user', $form_state->getValue('drupal_gredidam_user'))
      ->set('pass', $form_state->getValue('drupal_gredidam_password'))
      ->set('client', $form_state->getValue('drupal_gredidam_client'))
      ->set('num_assets_per_page', $form_state->getValue('num_assets_per_page'))
      ->save();

    // Retrieve client ID based on customer ID(name) from API.
    /** @var \Drupal\helfi_gredi_image\Service\GrediDamAuthService $auth_service */
    $auth_service = \Drupal::service('helfi_gredi_image.auth_service');
    try {
      $url = sprintf("%s/customerIds/%s", $form_state->getValue('domain_value'), $form_state->getValue('drupal_gredidam_client'));

      // A valid CookieJar is received on OK loginWithCredentials.
      if ($auth_service->getCookieJar() instanceof CookieJarInterface) {
        $apiCall = $auth_service->getGuzzleClient()->request('GET', $url, [
          'cookies' => $auth_service->getCookieJar(),
        ]);
        $client_id = Json::decode($apiCall->getBody()->getContents())['id'];
        $auth_service->setClientId($client_id);
      }
      else {
        $client_id = NULL;
      }

    }
    catch (ClientException $e) {
      $statusCode = $e->getResponse()->getStatusCode();
      if ($statusCode === 401) {
        throw new \Exception($statusCode);
      }
    }

    // Save the client_id.
    $this->config('helfi_gredi_image.settings')->set('client_id', $client_id)->save();

    $client_id = $this->config('helfi_gredi_image.settings')->get('client_id');
    if ($client_id === NULL) {
      $form_state->setErrorByName(
        'drupal_gredidam_client',
        $this->t('Provided client name is not valid.')
      );
    }

  }

}
