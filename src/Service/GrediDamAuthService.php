<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_gredi_image\GrediDamAuthServiceInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;

/**
 * Gredi DAM authentication service.
 */
class GrediDamAuthService implements GrediDamAuthServiceInterface {

  /**
   * Client id to identify the Gredi Dam client.
   *
   * @var string
   */
  const CUSTOMER = 'helsinki';

  /**
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  private $baseUrl;

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
   * Customer ID.
   *
   * @var mixed
   */
  protected $customerId;

  /**
   * Gredi DAM Username.
   *
   * @var string
   */
  protected $grediUsername;

  /**
   * Gredi DAM Password.
   *
   * @var string
   */
  protected $grediPassword;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

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
  public function __construct(ClientInterface $guzzleClient, AccountInterface $account) {
    $this->guzzleClient = $guzzleClient;
    $this->user = $account;
    $this->cookieJar = $this->loginWithCredentials();
    $this->customerId = $this->getClientId();
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfig(): ImmutableConfig {
    return \Drupal::config('gredi_dam.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getCookieJar(): ?CookieJar {
    return $this->cookieJar;
  }

  /**
   * {@inheritDoc}
   */
  public function getCustomerId() {
    return $this->customerId;
  }

  /**
   * {@inheritDoc}
   */
  public function getGrediUsername() {
    return User::load($this->user->id())->field_gredi_dam_username->getValue()[0]['value'] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getGrediPassword() {
    return User::load($this->user->id())->field_gredi_dam_password->getValue()[0]['value'] ?? NULL;
  }

  /**
   * Gets a base DAM Client object using the specified credentials.
   *
   * @return \GuzzleHttp\Cookie\CookieJar|bool
   *   The Gredi DAM client.
   */
  public function loginWithCredentials() {
    $config = self::getConfig();
    $this->baseUrl = $config->get('domain');
    $cookieDomain = parse_url($this->baseUrl)['host'];
    $username = $this->getGrediUsername();
    $password = $this->getGrediPassword();
    if (isset($username) && isset($password)) {

      $data = [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'body' => '{
        "customer": "' . self::CUSTOMER . '",
        "username": "' . $username . '",
        "password": "' . $password . '"
      }',
      ];
      try {
        $response = $this->guzzleClient->request(
          "POST",
          $this->baseUrl . '/sessions',
          $data
        );

        if ($response->getStatusCode() == 200 && $response->getReasonPhrase() == 'OK') {
          $getCookie = $response->getHeader('Set-Cookie')[0];
          $subtring_start = strpos($getCookie, '=');
          $subtring_start += strlen('=');
          $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
          $result = substr($getCookie, $subtring_start, $size);

          return CookieJar::fromArray([
            'JSESSIONID' => $result,
          ], $cookieDomain);
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

      }
    }
    else {
      return NULL;
    }
  }

  /**
   * Function to retrieve customer ID.
   *
   * @return mixed
   *   Customer ID.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getClientId() {
    $apiCall = $this->guzzleClient->request('GET', $this->baseUrl . '/customerIds/' . self::CUSTOMER, [
      'cookies' => $this->cookieJar,
    ]);

    return Json::decode($apiCall->getBody()->getContents())['id'];
  }

}
