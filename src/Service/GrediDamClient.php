<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\GrediDamClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
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
  protected ConfigFactoryInterface $config;

  /**
   * Customer ID.
   *
   * @var mixed
   */
  protected $customerId;

  /**
   * Gredi dam auth service.
   *
   * @var \Drupal\helfi_gredi_image\Service\GrediDamAuthService
   */
  protected GrediDamAuthService $grediDamAuthService;

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
   * @param \Drupal\helfi_gredi_image\Service\GrediDamAuthService $grediDamAuthService
   *   Gredi dam auth service.
   */
  public function __construct(ClientInterface $guzzleClient, ConfigFactoryInterface $config, GrediDamAuthService $grediDamAuthService) {
    $this->guzzleClient = $guzzleClient;
    $this->config = $config;
    $this->grediDamAuthService = $grediDamAuthService;
    // $this->cookieJar = $this->grediDamAuthService->getCookieJar();
    // $this->customerId = $this->grediDamAuthService->getCustomerId();
    $this->baseUrl = $this->grediDamAuthService->getConfig()->get('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('helfi_gredi_image.auth_service')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getCustomerContent(array $params = []): array {
    $parameters = '';

    if (isset($params)) {
      foreach ($params as $key => $param) {
        $parameters .= '&' . $key . '=' . $param;
      }
    }
    try {
      $customerId = $this->grediDamAuthService->getCustomerId();
    }
    catch (\Exception $e) {
      throw $e;
    }
    $userContent = $this->guzzleClient->request('GET', $this->baseUrl . '/customers/' . $customerId . '/contents?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
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
   * {@inheritDoc}
   */
  public function getFolderContent(int $folder_id, array $params = []): ?array {
    if (empty($folder_id)) {
      return NULL;
    }
    $parameters = '';

    if (isset($params)) {
      foreach ($params as $key => $param) {
        $parameters .= '&' . $key . '=' . $param;
      }
    }

    $userContent = $this->guzzleClient->request('GET', $this->baseUrl . '/folders/' . $folder_id . '/files/?include=attachments' . $parameters, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
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
   * {@inheritDoc}
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
   * {@inheritDoc}
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
        'cookies' => $this->grediDamAuthService->getCookieJar(),
      ]
    );

    return Asset::fromJson($response->getBody()->getContents(), $folder_id);
  }

  /**
   * {@inheritDoc}
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

  /**
   * Fetches binary asset data from a remote source.
   *
   * @param \Drupal\helfi_gredi_image\Entity\Asset $asset
   *   The asset to fetch data for.
   * @param string $filename
   *   The filename as a reference so it can be overridden.
   *
   * @return false|string[]
   *   The remote asset contents or FALSE on failure.
   */
  public function fetchRemoteAssetData(Asset $asset, &$filename) {
    if ($this->config->get('transcode') === 'original') {
      $download_url = $asset->attachments;
    }
    else {
      // If the module was configured to enforce an image size limit then we
      // need to grab the nearest matching pre-created size.
      $remote_base_url = Asset::getAssetRemoteBaseUrl();
      $download_url = $remote_base_url . $asset->apiContentLink;

//      if (empty($download_url)) {
//        $this->loggerChannel->warning(
//          'Unable to save file for asset ID @asset_id.
//           Thumbnail has not been found.', [
//          '@asset_id' => $asset->external_id,
//        ],
//        );
//        return FALSE;
//      }
    }

    try {
      $response = $this->guzzleClient->request(
        "GET",
        $download_url,
        [
          'allow_redirects' => [
            'track_redirects' => TRUE,
          ],
          'cookies' => $this->grediDamAuthService->getCookieJar(),
        ]
      );

      $size = $response->getBody()->getSize();

      if ($size === NULL || $size === 0) {
//        $this->loggerChannel->error('Unable to download contents for asset ID @asset_id.
//        Received zero-byte response for download URL @url',
//          [
//            '@asset_id' => $asset->external_id,
//            '@url' => $download_url,
//          ]);
        return FALSE;
      }
      $file_contents = (string) $response->getBody();

      if ($response->hasHeader('Content-Disposition')) {
        $disposition = $response->getHeader('Content-Disposition')[0];
        preg_match('/filename="(.*)"/', $disposition, $matches);
        if (count($matches) > 1) {
          $filename = $matches[1];
        }
      }
    }
    catch (RequestException $exception) {
      $message = 'Unable to download contents for asset ID @asset_id: %message.
      Attempted download URL @url with redirects to @history';
      $context = [
        '@asset_id' => $asset->external_id,
        '%message' => $exception->getMessage(),
        '@url' => $download_url,
        '@history' => '[empty request, cannot determine redirects]',
      ];
      $response = $exception->getResponse();
      if ($response) {
        $context['@history'] = $response->getHeaderLine('X-Guzzle-Redirect-History');
      }
//      $this->loggerChannel->error($message, $context);
      return FALSE;
    }

    return $file_contents;
  }

}
