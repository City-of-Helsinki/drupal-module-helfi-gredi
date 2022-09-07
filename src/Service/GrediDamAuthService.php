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
   * Client id to identify the Gredi DAM client.
   *
   * @var string
   */
  const CUSTOMER = "helsinki";

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
   * The current user account.
   *
   * @var \Drupal\user\Entity\User
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
    $this->user = User::load($account->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfig(): ImmutableConfig {
    return \Drupal::config('helfi_gredi_image.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getCookieJar() {
    if ($this->cookieJar) {
      return $this->cookieJar;
    }

    return $this->loginWithCredentials();
  }

  /**
   * {@inheritDoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCustomerId() {
    $config = self::getConfig();
    $this->baseUrl = $config->get('domain');
    try {
      $url = sprintf("%s/customerIds/%s", $this->baseUrl, self::CUSTOMER);
      $apiCall = $this->guzzleClient->request('GET', $url, [
        'cookies' => $this->getCookieJar(),
      ]);
      return Json::decode($apiCall->getBody()->getContents())['id'];
    }
    catch (ClientException $e) {
      $statusCode = $e->getResponse()->getStatusCode();
      if ($statusCode === 401) {
        throw new \Exception($statusCode);
      }
    }
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
    $cookieDomain = parse_url($this->baseUrl);
    $username = $this->getUsername();
    $password = $this->getPassword();

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
        $url = sprintf("%s/sessions", $this->baseUrl);
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

  /**
   * Check if the user auth are correct.
   *
   * @return bool
   *   TRUE if user is logged in FALSE otherwise.
   */
  public function checkLogin() {
    return is_int($this->loginWithCredentials()) && $this->loginWithCredentials() == 401;
  }

  /**
   * {@inheritDoc}
   */
  public function getUsername(): ?string {
    $user_field = $this->user->field_gredi_dam_username;
    if ($user_field !== NULL) {
      return $user_field->getString() ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getPassword(): ?string {
    $pass_field = $this->user->field_gredi_dam_password;
    if ($pass_field !== NULL) {
      return $pass_field->getString() ?? NULL;
    }
    return NULL;
  }

}
