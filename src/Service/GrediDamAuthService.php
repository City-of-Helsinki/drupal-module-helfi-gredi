<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_gredi_image\DamAuthServiceInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;

/**
 * Gredi DAM authentication service.
 */
class GrediDamAuthService implements DamAuthServiceInterface {

  /**
   * The client ID for the Gredi DAM API.
   */
  public string $customerId;

  public string $username;

  public string $password;

  public string $customer;

  protected string $sessionId;

  const SESSION_ID_STATE_NAME = 'helfi_gredi_image_session';

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
   * Class constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   HTTP client.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function __construct(ClientInterface $guzzleClient) {
    $this->guzzleClient = $guzzleClient;
    $config = $this->getConfig();
    $this->baseApiUrl = trim($config->get('api_url') ?? '', "/");
    $this->apiUrl = $this->baseApiUrl . '/api/v1';
    $this->username = $config->get('username') ?? '';
    $this->password = $config->get('password') ?? '';
    $this->customer = $config->get('customer') ?? '';
    $this->customerId = $config->get('customer_id') ?? '';
    $this->sessionId = $this->getStoredSessionId() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return \Drupal::config('helfi_gredi_image.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getCookieJar() {
    if ($this->cookieJar instanceof CookieJar) {
      return $this->cookieJar;
    }
    // TODO inject service with dep injection.
    $sessionId = \Drupal::state()->get(self::SESSION_ID_STATE_NAME);
    if (!empty($sessionId)) {
      $urlParts = parse_url($this->apiUrl);
      $this->cookieJar = CookieJar::fromArray([
        'JSESSIONID' => $sessionId,
      ], $urlParts['host']);

      return $this->cookieJar;
    }

    return NULL;
  }

  public function setSessionId(string $session) :void {
    $this->storeSessionId($session);
    $this->sessionId = $session;
  }

  protected function storeSessionId(string $session) :void {
    // TODO inject service with dep injection.
    \Drupal::state()->set(self::SESSION_ID_STATE_NAME, $session);
  }

  protected function getStoredSessionId() :string {
    // TODO inject service with dep injection.
    return \Drupal::state()->get(self::SESSION_ID_STATE_NAME, '');
  }

  public function getSessionId() {
    return $this->sessionId;
  }

  public function isAuthenticated() {
    return !empty($this->sessionId);
  }

  /**
   * {@inheritDoc}
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
   * Gets a base DAM Client object using the specified credentials.
   *
   * @return \GuzzleHttp\Cookie\CookieJar|bool
   *   The Gredi DAM client.
   */
  public function loginWithCredentials() {
    $cookieDomain = parse_url($this->apiUrl);
    $username = $this->username;
    $password = $this->password;
    $customer = $this->customer;

    if (isset($username) && isset($password) && $customer) {
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
          $result = substr($getCookie, $subtring_start, $size);

          $this->cookieJar = CookieJar::fromArray([
            'JSESSIONID' => $result,
          ], $cookieDomain['host']);

          return $this->cookieJar;
        }
      }
      catch (ClientException $e) {
        $status_code = $e->getResponse()->getStatusCode();

        \Drupal::logger('helfi_gredi_image')->error(
          'Unable to authenticate. DAM API client returned a @code exception code with the following message: %message',
          [
            '@code' => $status_code,
            '%message' => $e->getMessage(),
          ]
        );
        return $status_code;
      }
    }
    else {
      return NULL;
    }
  }

  public function authenticate() {
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


  /**
   * Check if the user auth are correct.
   *
   * @return bool
   *   TRUE if user is logged in FALSE otherwise.
   */
  public function checkLogin() {
    // TODO we should store the session ID in the Drupal user session instead of always checking for valid session (for auth users, not cli/cron)
    // TODO if an api call throws auth error (401), we should than try a new login. and if that try fails, throw the error
    return is_int($this->loginWithCredentials()) && $this->loginWithCredentials() == 401;
  }

  /**
   * Getter method for guzzleClient.
   *
   * @return \GuzzleHttp\ClientInterface
   *   Return this guzzle client.
   */
  public function getGuzzleClient() : ClientInterface {
    return $this->guzzleClient;
  }


}
