<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;
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
    $this->baseUrl = trim($this->grediDamAuthService->getConfig()->get('domain'), '/');
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
    if ($this->categoryTree) {
      return $this->categoryTree;
    }

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

    $url = sprintf("%s/settings", $this->baseUrl);
    $apiSettings = $this->guzzleClient->request('GET', $url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ])->getBody()->getContents();
    $this->rootFolderId = Json::decode($apiSettings)['contentFolderId'];

    return Json::decode($apiSettings)['contentFolderId'];
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
    $remoteBaseUrl = Asset::getAssetRemoteBaseUrl();
    $downloadUrl = $remoteBaseUrl . ($original ? $asset->apiContentLink : $asset->apiPreviewLink);

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
      $response = $this->guzzleClient->request(
        "GET",
        $downloadUrl,
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
   * {@inheritDoc}
   */
  public function searchAssets(array $params, $limit, $offset): array {
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
    $totalAssets = count($content['assets']);
    $content['assets'] = array_slice($content['assets'], $offset, $limit);
    return [
      'content' => $content,
      'total' => $totalAssets,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function uploadImage(File $image): ?string {
    // If getCategoryTree found that UPLOAD folder exists,
    // it will assign the folder id to uploadFolderId.
    if ($this->uploadFolderId) {
      $urlUpload = sprintf("%s/folders/%d/files/", $this->baseUrl, $this->uploadFolderId);
    }
    else {
      // If upload folder doesn't exist,
      // it will be created and the folder id
      // will be assigned to uploadFolderId.
      $this->createFolder('UPLOAD', 'Upload folder');
      $urlUpload = sprintf("%s/folders/%d/files/", $this->baseUrl, $this->uploadFolderId);
    }

    $apiResponse = $this->guzzleClient->request('GET', $urlUpload, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $this->grediDamAuthService->getCookieJar(),
    ])->getStatusCode();

    if ($apiResponse == '200') {
      $fieldData = [
        "name" => basename($image->getFileUri()),
        "fileType" => "nt:file",
        "propertiesById" => [],
        "metaById" => [],
      ];
      $fieldString = json_encode($fieldData, JSON_FORCE_OBJECT);
      $base64EncodedFile = base64_encode(file_get_contents($image->getFileUri()));

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
      $requestBody .= "Content-Type: image/jpeg\r\n";
      $requestBody .= "Content-Transfer-Encoding: base64\r\n";
      $requestBody .= "\r\n";
      $requestBody .= $base64EncodedFile . "\r\n";
      $requestBody .= "--" . $boundary . "--\r\n";
      $requestBody .= "\r\n";
      try {
        $response = $this->guzzleClient->request('POST', $urlUpload, [
          'cookies' => $this->grediDamAuthService->getCookieJar(),
          'headers' => [
            'Content-Type' => 'multipart/form-data;boundary=' . $boundary,
            'Content-Length' => strlen($requestBody),
          ],
          'body' => $requestBody,
        ])->getBody()->getContents();

        // Return file ID from API as string.
        return json_decode($response, TRUE)['id'];
      }
      catch (\Exception $e) {
        \Drupal::logger('helfi_gredi_image')->error($e->getMessage());
      }
    }
    return NULL;
  }

  /**
   * Function that creates a folder in the API root.
   *
   * @param string $folderName
   *   Folder name.
   * @param string $folderDescription
   *   Folder description.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function createFolder($folderName, $folderDescription) {
    $url = sprintf("%s/folders/%d/files", $this->baseUrl, $this->getRootFolderId());

    $fieldData = [
      "name" => $folderName,
      "fileType" => "nt:folder",
      "propertiesById" => [
        'nibo:description_fi' => $folderDescription,
        'nibo:description_en' => $folderDescription,
      ],
    ];
    $fieldString = json_encode($fieldData, JSON_FORCE_OBJECT);

    try {
      $response = $this->guzzleClient->request('POST', $url, [
        'cookies' => $this->grediDamAuthService->getCookieJar(),
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'body' => $fieldString,
      ])->getBody()->getContents();

      $this->uploadFolderId = Json::decode($response)['id'];
    }
    catch (\Exception $e) {
      \Drupal::logger('helfi_gredi_image')->error($e->getMessage());
    }
  }

}
