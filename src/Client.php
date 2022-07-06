<?php

namespace Drupal\helfi_gredi_image;

use Drupal\helfi_gredi_image\Entity\Category;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Overridden implementation of the cweagans php-webdam-client.
 */
class Client {

  /**
   * The Guzzle client to use for communication with the Gredi DAM API.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The base URL of the Gredi DAM API.
   *
   * @var string
   */
  protected $baseUrl = "https://api4.materialbank.net/api/v1";

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The version of this client. Used in User-Agent string for API requests.
   *
   * @var string
   */
  const CLIENTVERSION = "2.x";

  /**
   * The Gredi DAM client service.
   *
   * @var \Drupal\helfi_gredi_image\GrediClientFactory
   */
  protected $grediDamClientFactory;

  /**
   * Datastore for the specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields;

  /**
   * Client constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle client interface.
   * @param string \Drupal\helfi_gredi_image\GrediClientFactory
   *   An instance of GrediClientFactory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
  */
  public function __construct(ClientInterface $client, GrediClientFactory $grediDamClientFactory, RequestStack $request_stack) {
    $this->client = $client;
    $this->grediDamClientFactory = $grediDamClientFactory;
    $this->requestStack = $request_stack;
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

    $response = $this->client->request(
      "GET",
      $url,
      [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'cookies' => $this->grediDamClientFactory->getWithCredentials('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx')
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
        'type' => 'string'
      ],
      'name' => [
        'label' => 'Filename',
        'type' => 'string'
      ],
      'width' => [
        'label' => 'Width',
        'type' => 'string'
      ],
      'height' => [
        'label' => 'Height',
        'type' => 'string'
      ],
      'resolution' => [
        'label' => 'Resolution',
        'type' => 'string'
      ],
      'keywords' => [
        'label' => 'Keywords',
        'type' => 'text_long'
      ],
      'alt_text' => [
        'label' => 'Alt text',
        'type' => 'string'
      ],
      'size' => [
        'label' => 'Filesize (kb)',
        'type' => 'string'
      ],
    ];

    $this->specificMetadataFields = [];
    foreach ($fields as $key => $field) {
      $this->specificMetadataFields[$key] = $field;
    }
    return $this->specificMetadataFields;
  }

}
