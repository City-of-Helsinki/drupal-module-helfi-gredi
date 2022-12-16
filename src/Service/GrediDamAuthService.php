<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\State\StateInterface;
use Drupal\helfi_gredi_image\DamAuthServiceInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;


/**
 * Gredi DAM authentication service.
 */
class GrediDamAuthService implements DamAuthServiceInterface {

  /**
   * The client ID for the Gredi API.
   *
   * @var string|array|mixed|null
   */
  public string $customerId;

  /**
   * The username for the Gredi API.
   *
   * @var string|array|mixed|null
   */
  public string $username;

  /**
   * The password for the Gredi API.
   *
   * @var string|array|mixed|null
   */
  public string $password;

  /**
   * The customer name for the Gredi API.
   *
   * @var string|array|mixed|null
   */
  public string $customer;

  /**
   * The upload folder id for the Gredi API.
   *
   * @var string|array|mixed|null
   */
  public string $uploadFolder;

  /**
   * The current session id.
   *
   * @var string
   */
  protected string $sessionId;

  /**
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  public string $apiUrl;

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $guzzleClient;

  /**
   * CookieJar for authentication.
   *
   * @var \GuzzleHttp\Cookie\CookieJar
   */
  protected $cookieJar;

  /**
   * The base URL for the API.
   *
   * @var string
   */
  protected $baseApiUrl;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  const SESSION_ID_STATE_NAME = 'helfi_gredi_image_session';

  /**
   * Class constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   */
  public function __construct(ClientInterface $guzzleClient, StateInterface $state, ConfigFactory $configFactory) {
    $this->guzzleClient = $guzzleClient;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $config = $this->configFactory->get('helfi_gredi_image.settings');
    $this->baseApiUrl = trim($config->get('api_url') ?? '', "/");
    $this->apiUrl = $this->baseApiUrl . '/api/v1';
    $this->username = $config->get('username') ?? '';
    $this->password = $config->get('password') ?? '';
    $this->customer = $config->get('customer') ?? '';
    $this->customerId = $config->get('customer_id') ?? '';
    $this->uploadFolder = $config->get('upload_folder_id') ?? '';
    $this->sessionId = $this->getStoredSessionId() ?? '';

  }

  /**
   * {@inheritDoc}
   */
  public function getCookieJar() {
    if ($this->cookieJar instanceof CookieJar) {
      return $this->cookieJar;
    }
    $sessionId = $this->state->get(self::SESSION_ID_STATE_NAME);
    if (!empty($sessionId)) {
      $urlParts = parse_url($this->apiUrl);
      $this->cookieJar = CookieJar::fromArray([
        'JSESSIONID' => $sessionId,
      ], $urlParts['host']);

      return $this->cookieJar;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSessionId(string $session) :void {
    $this->storeSessionId($session);
    $this->sessionId = $session;
  }

  /**
   * {@inheritdoc}
   */
  function storeSessionId(string $session) :void {
    $this->state->set(self::SESSION_ID_STATE_NAME, $session);
  }

  /**
   * {@inheritdoc}
   */
  function getStoredSessionId() :string {
    return $this->state->get(self::SESSION_ID_STATE_NAME, '');
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() : bool {
    return !empty($this->sessionId);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCustomerId() {
    if (!empty($this->customerId)) {
      return $this->customerId;
    }
    $this->customerId = '';
    try {
      $url = sprintf("%s/customerIds/%s", $this->apiUrl, $this->customer);
      $apiCall = $this->guzzleClient->request('GET', $url, [
        'cookies' => $this->getCookieJar(),
      ]);
      $this->customerId = Json::decode($apiCall->getBody()->getContents())['id'];
    }
    catch (ClientException $e) {
      throw new \Exception($e->getMessage());
    }

    return $this->customerId;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function authenticate() : bool {
    $username = $this->username;
    $password = $this->password;
    $customer = $this->customer;

    if (empty($username) || empty($password) || empty($customer)) {
      throw new \Exception('Credentials not filled in');
    }
    $data = [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body' => '{
        "customer": "' . $customer . '",
        "username": "' . $username . '",
        "password": "' . $password . '"
      }',
    ];

    try {
      $url = sprintf("%s/sessions", $this->apiUrl);
      $response = $this->guzzleClient->request("POST", $url, $data);
      if ($response->getStatusCode() == 200 && $response->getReasonPhrase() == 'OK') {
        $getCookie = $response->getHeader('Set-Cookie')[0];
        $subtring_start = strpos($getCookie, '=');
        $subtring_start += strlen('=');
        $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
        $sessionId = substr($getCookie, $subtring_start, $size);

        $this->cookieJar = NULL;
        $this->setSessionId($sessionId);
        $this->getCookieJar();
        return TRUE;
      }
      else {
        throw new \Exception('Authentication failed');
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('helfi_gredi_image')->error(
        'Unable to authenticate. DAM API client returned with the following message: %message',
        [
          '%message' => $e->getMessage(),
        ]
      );
      throw new \Exception($e->getMessage());
    }
  }

}
