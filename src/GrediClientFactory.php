<?php

namespace Drupal\helfi_gredi_image;

use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFactory.
 *
 * Factory class for Client.
 */
class GrediClientFactory implements ContainerInjectionInterface {

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $guzzleClient;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   */
  public function __construct(ClientInterface $guzzleClient) {
    $this->guzzleClient = $guzzleClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Gets a base DAM Client object using the specified credentials.
   *
   * @param string $customer
   *   The customer to authenticate with.
   * @param string $username
   *   The username to authenticate with.
   * @param string $password
   *   The password to authenticate with.
   *
   * @return \Drupal\helfi_gredi_image\Client
   *   The Gredi DAM client.
   */
  public function getWithCredentials($customer, $username, $password) {

    $url = 'https://api4.materialbank.net/api/v1/sessions/';
    if (empty($data)) {
      $data = [
        'headers' => [
          'Content-Type' => 'application/json'
        ],
        'body' => '{
        "customer": "'. $customer . '",
        "username": "'. $username . '",
        "password": "'. $password . '"
      }'
      ];
    }
    try {
      $response = $this->guzzleClient->request(
        "POST",
        $url,
        $data
      );
    }
    catch (ClientException $e) {
      // For bad auth, the WebDAM API has been observed to return either
      // 400 or 403, so handle those via InvalidCredentialsException.
      $status_code = $e->getResponse()->getStatusCode();
      if ($status_code == 400 || $status_code == 403) {
        $body = (string) $e->getResponse()->getBody();
        $body = json_decode($body);

        throw new InvalidCredentialsException(
          $body->error_description . ' (' . $body->error . ').'
        );
      }
      else {
        // We've received an error status other than 400 or 403; log it
        // and move on.
        \Drupal::logger('helfi_gredi_image')->error(
          'Unable to authenticate. DAM API client returned a @code exception code with the following message: %message',
          [
            '@code' => $status_code,
            '%message' => $e->getMessage(),
          ]
        );
      }
    }
    return $response;
  }

  public function getCustomerContent($customer) {
    $loginSession = $this->getWithCredentials('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx');

    if ($loginSession->getStatusCode() == 200 && $loginSession->getReasonPhrase() == 'OK') {
      $getCookie = $loginSession->getHeader('Set-Cookie')[0];
      $subtring_start = strpos($getCookie, '=');
      $subtring_start += strlen('=');
      $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
      $result =  substr($getCookie, $subtring_start, $size);

      $cookieJar = CookieJar::fromArray([
        'JSESSIONID' => $result
      ], 'api4.materialbank.net');

      $userContent = $this->guzzleClient->request('GET', 'https://api4.materialbank.net/api/v1/customers/6/contents', [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $cookieJar
      ]);
      $posts = $userContent->getBody()->getContents();
      $content = [];
      foreach (Json::decode($posts) as $post) {
        if ($post['fileType'] == 'file' && $post['mimeGroup'] = 'picture') {
          $content[] = $post;
        }
      }
      return $content;
    }
    return false;
  }

}
