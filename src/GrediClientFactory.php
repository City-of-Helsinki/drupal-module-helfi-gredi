<?php

namespace Drupal\helfi_gredi_image;

use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
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

  protected $cookieJar;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   */
  public function __construct(ClientInterface $guzzleClient) {
    $this->guzzleClient = $guzzleClient;

    $this->cookieJar = $this->getWithCredentials('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx');
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
   * @return CookieJar
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
        "customer": "' . $customer . '",
        "username": "' . $username . '",
        "password": "' . $password . '"
      }'
      ];
    }

    try {
      $response = $this->guzzleClient->request(
        "POST",
        $url,
        $data
      );

      if ($response->getStatusCode() == 200 && $response->getReasonPhrase() == 'OK') {
        $getCookie = $response->getHeader('Set-Cookie')[0];
        $subtring_start = strpos($getCookie, '=');
        $subtring_start += strlen('=');
        $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
        $result =  substr($getCookie, $subtring_start, $size);
        setcookie("JSESSIONID", $result, time() + 60 * 60 * 24, 'api4.materialbank.net');
        $cookieJar = CookieJar::fromArray([
          'JSESSIONID' => $result
        ], 'api4.materialbank.net');
        return $cookieJar;

      }
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
  }

  public function getCustomerContent($customer, $params = []): array {
    $parameters = '';

    if (!empty($params)) {
      $parameters .= '&offset=' . $params['offset'] . '&limit=' . $params['limit'];
    }
      $userContent = $this->guzzleClient->request('GET', 'https://api4.materialbank.net/api/v1/customers/' . $customer . '/contents?include=attachments' . $parameters, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->cookieJar
      ]);
      $posts = $userContent->getBody()->getContents();
      $content = [];
      foreach (Json::decode($posts) as $post) {
        if ($post['fileType'] == 'file' && $post['mimeGroup'] = 'picture') {

          $content['assets'][] = $this->getAsset($post['id'], ['meta', 'attachments'], $post['parentId']);
        }
        elseif ($post['fileType'] == 'folder') {
          $content['folders'][] = Category::fromJson($post);
        }
      }

      return $content;
  }

  public function getFolderContent($folder_id, $params = []) {
    if (empty($folder_id)) return;
    $parameters = '';
    if (!empty($params)) {
        $parameters .= '?offset=' . $params['offset'] . '&limit=' . $params['limit'];
    }
    $userContent = $this->guzzleClient->request('GET', 'https://api4.materialbank.net/api/v1/folders/' . $folder_id . '/files/?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->cookieJar
    ]);
    $posts = $userContent->getBody()->getContents();
    $contents = [];

    foreach (Json::decode($posts) as $post) {
      if ($post['folder'] == FALSE) {
        $contents['assets'][] = Asset::fromJson($post, $folder_id);
      }
      else {
        $contents['folders'][] = Category::fromJson($post);
      }
    }
    return $contents;
  }

  /**
   * Get a list of Assets given an array of Asset ID's.
   *
   * @param array $ids
   *   The Gredi DAM Asset ID's.
   * @param array $expand
   *   A list of dta items to expand on the result set.
   *
   * @return array
   *   A list of assets.
   */
  public function getMultipleAsset($ids, $expand = []) : array {
    if (empty($ids)) {
      return [];
    }

    $assets = [];
    foreach ($ids as $id) {
      if ($id == NULL) {
        continue;
      }
      $assets[] = $this->getAsset($id, $expand);
    }


    return $assets;
  }

  /**
   * Get an Asset given an Asset ID.
   *
   * @param string $id
   *   The Gredi DAM Asset ID.
   * @param array $expands
   *   The additional properties to be included.
   * @param string $folder_id
   *   Folder id.
   *
   * @return \Drupal\helfi_gredi_image\Entity\Asset
   *   The asset entity.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAsset(string $id, array $expands = [], string $folder_id = NULL): Asset {
    $required_expands = Asset::getRequiredExpands();
    $allowed_expands = Asset::getAllowedExpands();
    $expands = array_intersect(array_unique($expands + $required_expands), $allowed_expands);

    $response = $this->guzzleClient->request(
      "GET",
      'https://api4.materialbank.net/api/v1/files/' . $id . '?include=' . implode('%2C', $expands),
      [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->cookieJar
      ]
    );

    return Asset::fromJson($response->getBody()->getContents(), $folder_id);
  }

}
