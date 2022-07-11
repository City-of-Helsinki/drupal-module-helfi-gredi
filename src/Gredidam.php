<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
   * @var \Drupal\helfi_gredi_image\Client
   */
  protected $grediDamClient;

  /**
   * The Gredi DAM client service.
   *
   * @var \Drupal\helfi_gredi_image\GrediClientFactory
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
   * @param \Drupal\helfi_gredi_image\Client $grediDamClient
   *   An instance of ClientFactory that we can get a webdam client from.
   * @param string \Drupal\helfi_gredi_image\GrediClientFactory
   *   An instance of GrediClientFactory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(Client $grediDamClient, GrediClientFactory $grediDamClientFactory, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->grediDamClient = $grediDamClient;
    $this->grediDamClientFactory = $grediDamClientFactory;
    $this->loggerChannel = $loggerChannelFactory->get('helfi_gredi_image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_gredi_image.client'),
      $container->get('helfi_gredi_image.client_factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, array $arguments) {
    $method_variable = [$this->grediDamClient, $name];
    return is_callable($method_variable) ?
      call_user_func_array($method_variable, $arguments) : NULL;
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

}
