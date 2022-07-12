<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\helfi_gredi_image\Entity\Category;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Gredidam.
 *
 * Abstracts away details of the REST API.
 */
class Gredidam implements GredidamInterface, ContainerInjectionInterface {

  /**
   * Temporary asset data storage.
   *
   * @var array
   */
  protected static $cachedAssets = [];

  /**
   * The Gredi DAM client service.
   *
   * @var \Drupal\helfi_gredi_image\GrediDamClient
   */
  protected $grediDamClientFactory;

  /**
   * Media: Gredi DAM logging service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Gredidam constructor.
   *
   * @param \Drupal\helfi_gredi_image\GrediDamClient $grediDamClientFactory
   *   An instance of GrediDamClient.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(GrediDamClient $grediDamClientFactory, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->grediDamClientFactory = $grediDamClientFactory;
    $this->loggerChannel = $loggerChannelFactory->get('helfi_gredi_image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_gredi_image.client_factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * Call getCustomerContent from ClientFactory.
   */
  public function getCustomerContent($customer, $params = []) {
    return $this->grediDamClientFactory->getCustomerContent($customer, $params);
  }

  /**
   * Call getFolderContent from ClientFactory.
   */
  public function getFolderContent($folder_id, $params = []) {
    return $this->grediDamClientFactory->getFolderContent($folder_id, $params);
  }

  /**
   * Call getMultipleAsset from ClientFactory.
   */
  public function getMultipleAsset($ids, $expand = []) {
    return $this->grediDamClientFactory->getMultipleAsset($ids, $expand);
  }

  /**
   * Call getAsset from ClientFactory.
   */
  public function getAsset($assetId, $expands = ['meta', 'attachments']) {
    return $this->grediDamClientFactory->getAsset($assetId, $expands);
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
    return $this->grediDamClientFactory->getCategoryData($category);
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
    return $this->grediDamClientFactory->getSpecificMetadataFields();
  }

}
