<?php

namespace Drupal\helfi_gredi_image;

use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
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
class GrediDamClient implements ContainerInjectionInterface {

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
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  protected $baseUrl = "https://api4.materialbank.net/api/v1";

  /**
   * The version of this client. Used in User-Agent string for API requests.
   *
   * @var string
   */
  const CLIENTVERSION = "2.x";

  /**
   * The Gredi DAM client service.
   *
   * @var \Drupal\helfi_gredi_image\GrediDamClient
   */
  protected $grediDamClientFactory;

  /**
   * Datastore for the specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields;

  /**
   * Config Factory var.
   *
   * @var ConfigFactoryInterface
   */
  protected $config;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   * @param ConfigFactoryInterface $config
   *   Config factory var.
   */
  public function __construct(ClientInterface $guzzleClient, ConfigFactoryInterface $config) {
    $this->guzzleClient = $guzzleClient;
    $this->config = $config;
    $this->cookieJar = $this->loginWithCredentials();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * Gets a base DAM Client object using the specified credentials.
   *
   * @return \GuzzleHttp\Cookie\CookieJar
   *   The Gredi DAM client.
   */
  public function loginWithCredentials() {
    $url = 'https://api4.materialbank.net/api/v1/sessions/';

    $customer = 'helsinki';
    $config = $this->config->get('gredi_dam.settings');
    $username = $config->get('user');
    $password = $config->get('pass');
    if (empty($data)) {
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
    }

    try {
      $response = $this->guzzleClient->request(
        "POST",
        $config->get('domain'),
        $data
      );

      if ($response->getStatusCode() == 200 && $response->getReasonPhrase() == 'OK') {
        $getCookie = $response->getHeader('Set-Cookie')[0];
        $subtring_start = strpos($getCookie, '=');
        $subtring_start += strlen('=');
        $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
        $result = substr($getCookie, $subtring_start, $size);
        setcookie("JSESSIONID", $result, time() + 60 * 60 * 24, 'api4.materialbank.net');
        $cookieJar = CookieJar::fromArray([
          'JSESSIONID' => $result,
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

  /**
   * Get folders and assets from Customer id.
   *
   * @param int $customer
   *   Customer.
   * @param array $params
   *   Parameters.
   *
   * @return array
   *   Customer content.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCustomerContent(int $customer, array $params = []): array {
    $parameters = '';

    if (!empty($params)) {
      $parameters .= '&offset=' . $params['offset'] . '&limit=' . $params['limit'];
    }
    $userContent = $this->guzzleClient->request('GET', 'https://api4.materialbank.net/api/v1/customers/' . $customer . '/contents?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->cookieJar,
    ]);
    $posts = $userContent->getBody()->getContents();
    $content = [];
    foreach (Json::decode($posts) as $post) {
      if ($post['fileType'] == 'file' && $post['mimeGroup'] = 'picture') {
        $expands = ['meta', 'attachments'];
        $content['assets'][] = $this->getAsset($post['id'], $expands, $post['parentId']);
      }
      elseif ($post['fileType'] == 'folder') {
        $content['folders'][] = Category::fromJson($post);
      }
    }

    return $content;
  }

  /**
   * Get assets and sub-folders from folders.
   *
   * @param int $folder_id
   *   Folder ID.
   * @param array $params
   *   Parameters.
   *
   * @return array|void
   *   Content.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFolderContent(int $folder_id, array $params = []) {
    if (empty($folder_id)) {
      return;
    }
    $parameters = '';
    if (!empty($params)) {
      $parameters .= '?offset=' . $params['offset'] . '&limit=' . $params['limit'];
    }
    $userContent = $this->guzzleClient->request('GET', 'https://api4.materialbank.net/api/v1/folders/' . $folder_id . '/files/?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->cookieJar,
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
  public function getMultipleAsset(array $ids, array $expand = []): array {
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
        'cookies' => $this->cookieJar,
      ]
    );

    return Asset::fromJson($response->getBody()->getContents(), $folder_id);
  }

  /**
   * Load subcategories by Category link or parts (used in breadcrumb).
   *
   * @param \Drupal\helfi_gredi_image\Entity\Category $category
   *   Category object.
   *
   * @return \Drupal\helfi_gredi_image\Entity\Category[]
   *   A list of sub-categories (ie: child categories).
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCategoryData(Category $category): array {

    $url = $this->baseUrl . '/folders/{id}/files/';
    // If category is not set, it will load the root category.
    if (isset($category->links->categories)) {
      $url = $category->links->categories;
    }
    elseif (!empty($category->parts)) {
      $cats = "";
      foreach ($category->parts as $part) {
        $cats .= "/" . $part;
      }
      $url .= $cats;
    }

    $response = $this->guzzleClient->request(
      "GET",
      $url,
      [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->grediDamClientFactory->getWithCredentials('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx'),
      ]
    );
    $category = Category::fromJson((string) $response->getBody());
    return $category;
  }

  /**
   * Get a list of metadata.
   *
   * @return array
   *   A list of metadata fields.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getSpecificMetadataFields(): array {
    $fields = [
      'external_id' => [
        'label' => 'External ID',
        'type' => 'string',
      ],
      'name' => [
        'label' => 'Filename',
        'type' => 'string',
      ],
      'width' => [
        'label' => 'Width',
        'type' => 'string',
      ],
      'height' => [
        'label' => 'Height',
        'type' => 'string',
      ],
      'resolution' => [
        'label' => 'Resolution',
        'type' => 'string',
      ],
      'keywords' => [
        'label' => 'Keywords',
        'type' => 'text_long',
      ],
      'alt_text' => [
        'label' => 'Alt text',
        'type' => 'string',
      ],
      'size' => [
        'label' => 'Filesize (kb)',
        'type' => 'string',
      ],
    ];

    $this->specificMetadataFields = [];
    foreach ($fields as $key => $field) {
      $this->specificMetadataFields[$key] = $field;
    }
    return $this->specificMetadataFields;
  }

}
