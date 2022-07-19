<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\GrediDamClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFactory.
 *
 * Factory class for Client.
 */
class GrediDamClient implements ContainerInjectionInterface, GrediDamClientInterface {

  /**
   * The customer of the Gredi DAM API.
   *
   * @var string
   */
  const CUSTOMER = "helsinki";

  /**
   * The version of this client. Used in User-Agent string for API requests.
   *
   * @var string
   */
  const CLIENTVERSION = "2.x";

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
   * Datastore for the specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields;

  /**
   * Config Factory var.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  private $baseUrl;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
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
  public function loginWithCredentials(): ?CookieJar {
    $config = $this->config->get('gredi_dam.settings');
    $this->baseUrl = $config->get('domain');
    $cookieDomain = parse_url($this->baseUrl)['host'];
    $username = $config->get('user');
    $password = $config->get('pass');
    if (empty($data)) {
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
    }

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
        setcookie("JSESSIONID", $result, time() + 60 * 60 * 24, $cookieDomain);
        $cookieJar = CookieJar::fromArray([
          'JSESSIONID' => $result,
        ], $cookieDomain);

        return $cookieJar;
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

  public function getClientId() {
    $apiCall = $this->guzzleClient->request('GET', $this->baseUrl . '/customerIds/' . self::CUSTOMER, [
      'cookies' => $this->cookieJar
    ]);

    return Json::decode($apiCall->getBody()->getContents())['id'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCustomerContent(int $customer, array $params = []): array {
    $parameters = '';

    foreach ($params as $key => $param) {
      $parameters .= '&' . $key . '=' . $param;
    }

    $userContent = $this->guzzleClient->request('GET', $this->baseUrl . '/customers/' . $customer . '/contents?include=attachments' . $parameters, [
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
   * @return array|null
   *   Content.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFolderContent(int $folder_id, array $params = []): ?array {
    if (empty($folder_id)) {
      return NULL;
    }
    $parameters = '';
    foreach ($params as $key => $param) {
      $parameters .= '&' . $key . '=' . $param;
    }
    $userContent = $this->guzzleClient->request('GET', $this->baseUrl . '/folders/' . $folder_id . '/files/?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->cookieJar,
    ]);
    $posts = $userContent->getBody()->getContents();
    $contents = [];

    foreach (Json::decode($posts) as $post) {
      if (!$post['folder']) {
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
      $this->baseUrl . '/files/' . $id . '?include=' . implode('%2C', $expands),
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
