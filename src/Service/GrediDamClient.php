<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\DamClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
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
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

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
   * Root folder ID.
   *
   * @var mixed
   */
  protected $rootFolderId;

  /**
   * Gredi dam auth service.
   *
   * @var \Drupal\helfi_gredi_image\Service\GrediDamAuthService
   */
  protected GrediDamAuthService $authService;

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
  private $baseApiUrl;

  /**
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  private $apiUrl;

  /**
   * The tree of Gredi DAM categories.
   *
   * @var array
   */
  private $categoryTree;

  /**
   * Upload folder id.
   *
   * @var string
   */
  protected $uploadFolderId;

  /**
   * Meta's to include in API call.
   *
   * @var string
   */
  public $includes = 'object,meta,attachments';

  /**
   * Metafields array.
   *
   * @var array
   */
  private $metafields;

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
    LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->httpClient = $guzzleClient;
    $this->config = $config;
    $this->authService = $grediDamAuthService;
    $this->loggerChannel = $loggerChannelFactory->get('helfi_gredi_image');
    $this->baseApiUrl = $this->authService->baseApiUrl;
    $this->apiUrl = $this->authService->apiUrl;
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

  public function apiCallGet($apiUri, $queryParams = []) {
    $retry = TRUE;
    $login_retry = FALSE;
    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    while ($retry) {
      try {
        $url = sprintf("%s/%s", $this->apiUrl, $apiUri);
        if ($login_retry) {
          $this->authService->authenticate();
          $retry = FALSE;
        }
        $options = [
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'cookies' => $this->authService->getCookieJar(),
        ];
        if ($queryParams) {
          $url = sprintf("%s?%s", $url, http_build_query($queryParams));
        }
        $response = $this->httpClient->get($url, $options);
        $retry = FALSE;
      }
      catch (ClientException $e) {
        // TODO if Unauthorized try a new login
        if (!$login_retry && $e->getCode() == 401) {
          $login_retry = TRUE;
        }
        else {
          $this->loggerChannel->error(t('Error on calling @url : @error', [
            '@error' => $e->getMessage(),
            '@url' => $url,
          ]));
          throw new \Exception($e->getMessage());
        }
      }
      catch (GuzzleException $e) {
        $this->loggerChannel->error(t('Error on calling @url : @error', [
          '@error' => $e->getMessage(),
          '@url' => $url,
        ]));
        throw new \Exception($e->getMessage());
      }

    }

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function getCategoryTree(): array {
    if ($this->categoryTree) {
      return $this->categoryTree;
    }

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $customerId = $this->authService->getCustomerId();
    $uri = sprintf("customers/%d/contents", $customerId);
    $queryParams = [
      'materialType' => 'folder',
    ];
    $userContent = $this->apiCallGet($uri, $queryParams);
    $posts = $userContent->getBody()->getContents();
    $this->categoryTree = [];
    foreach (Json::decode($posts) as $post) {
      // Set upload folder id.
      if ($post['name'] == 'UPLOAD' && $post['parentId'] == $this->getRootFolderId()) {
        $this->uploadFolderId = $post['id'];
      }

      $category = Category::fromJson($post);
      $this->categoryTree[$category->id] = $category;
    }

    return $this->categoryTree;
  }

  /**
   * Get root folder id.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getRootFolderId() {
    if ($this->rootFolderId) {
      return $this->rootFolderId;
    }

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }

    $apiSettings = $this->apiCallGet('settings')->getBody()->getContents();

    $this->rootFolderId = Json::decode($apiSettings)['contentFolderId'];

    return $this->rootFolderId;
  }

  /**
   * {@inheritDoc}
   */
  public function getRootContent(int $limit, int $offset): array {
    return $this->getFolderContent($this->getRootFolderId(), $limit, $offset);
  }

  /**
   * {@inheritDoc}
   */
  public function getFolderContent(int $folder_id, int $limit, int $offset): ?array {
    if (empty($folder_id)) {
      return NULL;
    }

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $url = sprintf("folders/%d/files", $folder_id);
    $queryParams = [
      'include' => 'attachments,meta',
    ];
    $userContent = $this->apiCallGet($url, $queryParams);
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

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $url = sprintf("files/%s", $id);
    $queryParams = [
      'include' => 'object,meta,attachments',
    ];
    $response = $this->apiCallGet($url, $queryParams);
    return Asset::fromJson($response->getBody()->getContents());
  }

  /**
   * {@inheritDoc}
   */
  public function getAssetData(string $id): array {
    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $url = sprintf("files/%s", $id);
    $queryParams = [
      'include' => $this->includes,
    ];
    $response = $this->apiCallGet($url, $queryParams);

    return Json::decode($response->getBody()->getContents());
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
      'keywords' => [
        'label' => 'Keywords',
        'type' => 'text_long',
      ],
      'alt_text' => [
        'label' => 'Alt text',
        'type' => 'string',
      ],
      'created' => [
        'label' => 'Created',
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
   *   The filename as a reference, so it can be overridden.
   * @param bool $original
   *   If true download the original data else the preview.
   *
   * @return false|string[]
   *   The remote asset contents or FALSE on failure.
   */
  public function fetchRemoteAssetData(Asset $asset, &$filename, $original = TRUE) {
    // If the module was configured to enforce an image size limit then we
    // need to grab the nearest matching pre-created size.
    $downloadUrl = $original ? $asset->apiContentLink : $asset->apiPreviewLink;

    if (empty($downloadUrl)) {
      $this->loggerChannel->warning(
        'Unable to save file for asset ID @asset_id.
         Thumbnail has not been found.', [
           '@asset_id' => $asset->external_id,
         ],
      );
      return FALSE;
    }

    try {
      if (!$this->authService->isAuthenticated()) {
        $this->authService->authenticate();
      }
      $response = $this->apiCallGet($downloadUrl);

      $size = $response->getBody()->getSize();

      if ($size === NULL || $size === 0) {
        $this->loggerChannel->error('Unable to download contents for asset ID @asset_id.
        Received zero-byte response for download URL @url',
          [
            '@asset_id' => $asset->external_id,
            '@url' => $downloadUrl,
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
        '@url' => $downloadUrl,
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
   * Fetches binary asset data from a remote source.
   *
   * @param \Drupal\helfi_gredi_image\Entity\Asset $asset
   *   The asset to fetch data for.
   * @param string $filename
   *   The filename as a reference, so it can be overridden.
   * @param bool $original
   *   If true download the original data else the preview.
   *
   * @return false|string[]
   *   The remote asset contents or FALSE on failure.
   */
  public function getFileContent($assetId, $downloadUrl) {
    // If the module was configured to enforce an image size limit then we
    // need to grab the nearest matching pre-created size.

    if (empty($downloadUrl)) {
      $this->loggerChannel->warning(
        'Unable to save file for asset ID @asset_id.
         Thumbnail has not been found.', [
        '@asset_id' => $assetId,
      ],
      );
      return FALSE;
    }

    try {
      $downloadUrl = str_replace('/api/v1/' , '', $downloadUrl);
      if (!$this->authService->isAuthenticated()) {
        $this->authService->authenticate();
      }
      $response = $this->apiCallGet($downloadUrl);

      $size = $response->getBody()->getSize();

      if ($size === NULL || $size === 0) {
        $this->loggerChannel->error('Unable to download contents for asset ID @asset_id.
        Received zero-byte response for download URL @url',
          [
            '@asset_id' => $assetId,
            '@url' => $downloadUrl,
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
        '@asset_id' => $assetId,
        '%message' => $exception->getMessage(),
        '@url' => $downloadUrl,
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
  public function searchAssets($search = '', $sortBy = '', $sortOrder = '', $limit = 10, $offset = 0): array {
    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $customerId = $this->authService->getCustomerId();
    $url = sprintf("customers/%d/contents", $customerId);
    $queryParams = [
      'include' => $this->includes,
      'mimeGroups' => 'picture',
      'search' => $search,
      'sort' => $sortOrder . $sortBy,
      'limit' => $limit,
      'offset' => $offset,
    ];
    $queryParams = array_filter($queryParams);
    $response = $this->apiCallGet($url, $queryParams);

    $items = Json::decode($response->getBody()->getContents());
    $result = [];
    foreach ($items as $item) {
      $asset = (array) $item;
      $result[] = $asset;
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function uploadImage(File $image): ?string {

    $mime = $image->getMimeType();

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    // Create the upload URL.
    $urlUpload = sprintf("folders/%s/files/", $this->authService->uploadFolder);
    $url = sprintf("%s/%s", $this->apiUrl, $urlUpload);

    $fieldData = [
      "name" => basename($image->getFileUri()),
      "fileType" => "nt:file",
      "propertiesById" => [],
      "metaById" => [],
    ];
    $fieldString = json_encode($fieldData, JSON_FORCE_OBJECT);
    $base64EncodedFile = base64_encode(file_get_contents($image->getFileUri()));
    // TODO check this instead of this hardcoded string.
    // https://docs.guzzlephp.org/en/stable/quickstart.html#sending-form-fields
    $boundary = "helfiboundary";
    $requestBody = "";
    $requestBody .= "\r\n";
    $requestBody .= "\r\n";
    $requestBody .= "--" . $boundary . "\r\n";
    $requestBody .= "Content-Disposition: form-data; name=\"json\"\r\n";
    $requestBody .= "Content-Type: application/json\r\n";
    $requestBody .= "\r\n";
    $requestBody .= $fieldString . "\r\n";
    $requestBody .= "--" . $boundary . "\r\n";
    $requestBody .= "Content-Disposition: form-data; name=\"file\"\r\n";
    $requestBody .= "Content-Type: " . $mime . "\r\n";
    $requestBody .= "Content-Transfer-Encoding: base64\r\n";
    $requestBody .= "\r\n";
    $requestBody .= $base64EncodedFile . "\r\n";
    $requestBody .= "--" . $boundary . "--\r\n";
    $requestBody .= "\r\n";

    $response = $this->httpClient->request('POST', $url, [
      'cookies' => $this->authService->getCookieJar(),
      'headers' => [
        'Content-Type' => 'multipart/form-data;boundary=' . $boundary,
        'Content-Length' => strlen($requestBody),
      ],
      'body' => $requestBody,
    ])->getBody()->getContents();

    // Return file ID from API as string.
    return json_decode($response, TRUE)['id'];

  }

  /**
   * Get metafields.
   *
   * @return array
   *   Return metafields array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getMetaFields(): array {
    if ($this->metafields) {
      return $this->metafields;
    }
    $cache = \Drupal::cache()->get('helfi_gredi_image_metafields');
    if (!empty($cache->data)) {
      $this->metafields = $cache->data;
      return $this->metafields;
    }
    $customerId = $this->authService->getCustomerId();
    $url = sprintf("customers/%d/meta", $customerId);
    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    $response = $this->apiCallGet($url)->getBody()->getContents();
    $result = Json::decode($response);
    foreach ($result as $item) {
      if (!isset($item['id'])) {
        continue;
      }
      $this->metafields[$item['id']] = $item;
    }
    $cache_tags = $this->authService->getConfig()->getCacheTags();
    \Drupal::cache()->set('helfi_gredi_image_metafields', $this->metafields, Cache::PERMANENT, $cache_tags);

    return $this->metafields;
  }

}
