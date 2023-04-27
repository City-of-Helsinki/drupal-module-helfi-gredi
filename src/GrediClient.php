<?php

namespace Drupal\helfi_gredi;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GrediClient.
 *
 * Factory class for Client.
 */
class GrediClient implements ContainerInjectionInterface, GrediClientInterface {

  /**
   * The CacheBackEndInterface service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBin;

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Config Factory var.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * Gredi dam auth service.
   *
   * @var \Drupal\helfi_gredi\GrediAuthService
   */
  protected GrediAuthService $authService;

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
  private $apiUrl;

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
   * Entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $typeManager;

  /**
   * GrediClient constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory var.
   * @param \Drupal\helfi_gredi\GrediAuthService $grediAuthService
   *   Gredi dam auth service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBin
   *   The Drupal CacheBackendInterface service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $typeManager
   *   The Drupal EntityTypeManager service.
   */
  public function __construct(
    ClientInterface $guzzleClient,
    ConfigFactoryInterface $config,
    GrediAuthService $grediAuthService,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    CacheBackendInterface $cacheBin,
    EntityTypeManagerInterface $typeManager
  ) {
    $this->httpClient = $guzzleClient;
    $this->config = $config;
    $this->authService = $grediAuthService;
    $this->loggerChannel = $loggerChannelFactory->get('helfi_gredi');
    $this->apiUrl = $this->authService->apiUrl;
    $this->cacheBin = $cacheBin;
    $this->typeManager = $typeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('helfi_gredi.auth_service'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apiCallGet($apiUri, $queryParams = []) : ResponseInterface {
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
        if (!$login_retry && $e->getCode() === 401) {
          $login_retry = TRUE;
        }
        else {
          $this->loggerChannel->error(t('Error on calling @url : @error', [
            '@error' => $e->getMessage(),
            '@url' => $url,
          ]));
          throw new \Exception($e->getMessage(), $e->getCode());
        }
      }
      catch (GuzzleException $e) {
        $this->loggerChannel->error(t('Error on calling @url : @error', [
          '@error' => $e->getMessage(),
          '@url' => $url,
        ]));
        throw new \Exception($e->getMessage(), $e->getCode());
      }
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetData(string $id): array | NULL {
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
   * {@inheritdoc}
   */
  public function getFileContent($assetId, $downloadUrl) : FALSE|string {
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
      $downloadUrl = str_replace('/api/v1/', '', $downloadUrl);
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
   * {@inheritdoc}
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

  public function getFolderContent($folderId = NULL, $sortBy = '', $sortOrder = '', $limit = 10, $offset = 0): array {
    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }
    if (empty($folderId)) {
      $folderId = $this->config->get('helfi_gredi.settings')->get('root_folder_id');
    }
    $url = sprintf("folders/%d/files", $folderId);
    $queryParams = [
      'include' => $this->includes,
      // @todo allow only folders and images.
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
   * {@inheritdoc}
   */
  public function uploadImage(array $requestData, bool $is_update): ?string {

    if (!$this->authService->isAuthenticated()) {
      $this->authService->authenticate();
    }

    if (!$is_update) {
      // @todo check this instead of this hardcoded string.
      // https://docs.guzzlephp.org/en/stable/quickstart.html#sending-form-fields
      $boundary = "helfiboundary";
      $requestBody = "";
      $requestBody .= "\r\n";
      $requestBody .= "\r\n";
      $requestBody .= "--" . $boundary . "\r\n";
      $requestBody .= "Content-Disposition: form-data; name=\"json\"\r\n";
      $requestBody .= "Content-Type: application/json\r\n";
      $requestBody .= "\r\n";
      $requestBody .= $requestData['fieldData'] . "\r\n";
      $requestBody .= "--" . $boundary . "\r\n";
      $requestBody .= "Content-Disposition: form-data; name=\"file\"\r\n";
      $requestBody .= "Content-Type: " . $requestData['mime'] . "\r\n";
      $requestBody .= "Content-Transfer-Encoding: base64\r\n";
      $requestBody .= "\r\n";
      $requestBody .= $requestData['file'] . "\r\n";
      $requestBody .= "--" . $boundary . "--\r\n";
      $requestBody .= "\r\n";

      $urlUpload = sprintf("%s/folders/%s/files/", $this->apiUrl, $this->authService->uploadFolder);
      // Request made when uploading assets.
      $response = $this->httpClient->request('POST', $urlUpload, [
        'cookies' => $this->authService->getCookieJar(),
        'headers' => [
          'Content-Type' => 'multipart/form-data;boundary=helfiboundary',
          'Content-Length' => strlen($requestBody),
        ],
        'body' => $requestBody,
      ])->getBody()->getContents();
    }
    else {
      $urlSync = sprintf("%s/files/%s", $this->apiUrl, $requestData['assetId']);
      // Request made when syncing assets.
      $response = $this->httpClient->request('PUT', $urlSync, [
        'cookies' => $this->authService->getCookieJar(),
        'headers' => [
          'Content-Type' => 'application/json',
          'Content-Length' => strlen($requestData['fieldData']),
        ],
        'body' => $requestData['fieldData'],
      ])->getBody()->getContents();
    }

    // For upload we have the id, for update we have empty array response.
    return json_decode($response, TRUE)['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaFields(): array {
    if ($this->metafields) {
      return $this->metafields;
    }
    $cache = $this->cacheBin->get('helfi_gredi_metafields');
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

    $cache_tags = is_array($this->config->get('helfi_gredi.settings')->getCacheTags()) ?
      $this->config->get('helfi_gredi.settings')->getCacheTags() : [];

    $this->cacheBin->set('helfi_gredi_metafields', $this->metafields, Cache::PERMANENT, $cache_tags);

    return $this->metafields;
  }

}


