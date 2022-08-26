<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\DamClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFactory.
 *
 * Factory class for Client.
 */
class GrediDamClient implements ContainerInjectionInterface, DamClientInterface {

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
   * Gredi DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannel;

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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(
    ClientInterface $guzzleClient,
    ConfigFactoryInterface $config,
    GrediDamAuthService $grediDamAuthService,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->guzzleClient = $guzzleClient;
    $this->config = $config;
    $this->grediDamAuthService = $grediDamAuthService;
    $this->loggerChannel = $loggerChannelFactory->get('media_gredidam');
    $this->baseUrl = $this->grediDamAuthService->getConfig()->get('domain');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('helfi_gredi_image.auth_service'),
      $container->get('logger.factory')
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
    $url = sprintf("%s/customers/%d/contents?include=attachments%s", $this->baseUrl, $customerId, $parameters);
    $userContent = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ]);
    $posts = $userContent->getBody()->getContents();
    $content = [
      'folders' => [],
      'assets' => [],
    ];
    foreach (Json::decode($posts) as $post) {
      if ($post['fileType'] == 'file' && $post['mimeGroup'] = 'picture') {
        $expands = ['meta', 'attachments'];
        $content['assets'][] = $this->getAsset($post['id'], $expands, $post['parentId']);
      }
      elseif ($post['fileType'] == 'folder') {
        $content['folders'][] = Category::fromJson($post);
      }
    }

    return [
      'content' => $content,
      'total' => count($content),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCategoryTree(): array {
    try {
      $customerId = $this->grediDamAuthService->getCustomerId();
    }
    catch (\Exception $e) {
      throw $e;
    }
    $url = sprintf("%s/customers/%d/contents?materialType=folder", $this->baseUrl, $customerId);
    $userContent = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ]);
    $posts = $userContent->getBody()->getContents();
    $categories = [];
    foreach (Json::decode($posts) as $post) {
      $category = Category::fromJson($post);
      $categories[$category->id] = $category;
    }

    return $categories;
  }

  /**
   * Get folder id.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFolderRootId() {
    $url = sprintf("%s/settings", $this->baseUrl);
    $apiSettings = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ])->getBody()->getContents();
    return Json::decode($apiSettings)['contentFolderId'];
  }

  /**
   * {@inheritDoc}
   */
  public function getRootContent(int $limit, int $offset): array {
    return $this->getFolderContent($this->getFolderRootId(), $limit, $offset);
  }

  /**
   * {@inheritDoc}
   */
  public function getFolderId(string $path = ""): ?int {
    $url = sprintf("%s/fileIds/%s", $this->baseUrl, $path);
    try {
      $apiCall = $this->guzzleClient->request('GET', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->grediDamAuthService->getCookieJar(),
      ]);
      return Json::decode($apiCall->getBody()->getContents())['id'];
    }
    catch (ClientException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getFolderContent(int $folder_id, int $limit, int $offset): ?array {
    if (empty($folder_id)) {
      return NULL;
    }
    $url = sprintf("%s/folders/%d/files/?include=attachments", $this->baseUrl, $folder_id);
    $userContent = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ]);
    $posts = Json::decode($userContent->getBody()->getContents());
    $pageContent = array_slice($posts, $offset, $limit);
    $content = [
      'folders' => [],
      'assets' => [],
    ];
    foreach ($pageContent as $post) {
      if (!$post['folder']) {
        $content['assets'][] = Asset::fromJson($post);
      }
      else {
        $content['folders'][] = Category::fromJson($post);
      }
    }
    return [
      'content' => $content,
      'total' => count($posts),
    ];
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

    $url = sprintf("%s/files/%s?include=%s", $this->baseUrl, $id, implode('%2C', $expands));
    $response = $this->guzzleClient->request(
      "GET",
      $url,
      [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->grediDamAuthService->getCookieJar(),
      ]
    );
    return Asset::fromJson($response->getBody()->getContents());
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
      $download_url = $asset->attachments[Asset::ATTACHMENT_TYPE_ORIGINAL];
    }
    else {
      // If the module was configured to enforce an image size limit then we
      // need to grab the nearest matching pre-created size.
      $remote_base_url = Asset::getAssetRemoteBaseUrl();
      $download_url = $remote_base_url . $asset->apiContentLink;

      if (empty($download_url)) {
        $this->loggerChannel->warning(
          'Unable to save file for asset ID @asset_id.
          Thumbnail has not been found.', [
            '@asset_id' => $asset->external_id,
          ],
        );
        return FALSE;
      }
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
        $this->loggerChannel->error('Unable to download contents for asset ID @asset_id.
        Received zero-byte response for download URL @url',
          [
            '@asset_id' => $asset->external_id,
            '@url' => $download_url,
          ]);
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
      $this->loggerChannel->error($message, $context);
      return FALSE;
    }

    return $file_contents;
  }

  /**
   * {@inheritDoc}
   */
  public function searchAssets(array $params): array {
    $parameters = '';

    foreach ($params as $key => $param) {
      if (empty($param)) {
        continue;
      }
      $parameters .= '&' . $key . '=' . $param;
    }

    try {
      $customerId = $this->grediDamAuthService->getCustomerId();
    }
    catch (\Exception $e) {
      throw $e;
    }
    $url = sprintf("%s/customers/%d/contents?include=object%s", $this->baseUrl, $customerId, $parameters);
    $response = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ]);

    $posts = Json::decode($response->getBody()->getContents());
    $content = [
      'assets' => [],
    ];

    foreach ($posts as $post) {
      if (!$post['folder']) {
        $content['assets'][] = Asset::fromJson($post);
      }
    }
    return [
      'content' => $content,
      'total' => count($content['assets']),
    ];
  }

  public function uploadImage() {
    // Upload folder url.
    $url = sprintf("%s/folders/16209558/files/", $this->baseUrl);
    $apiResponse = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ])->getStatusCode();
    dump($apiResponse);

//    $rootId = Json::decode($apiSettings)['contentFolderId'];

//    return $this->getFolderContent($rootId, $limit, $offset);
  }

}
