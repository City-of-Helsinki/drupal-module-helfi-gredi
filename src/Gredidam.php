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
   * Media: Gredi DAM logging service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Gredidam constructor.
   *
   * @param \Drupal\helfi_gredi_image\GrediClientFactory $client_factory
   *   An instance of ClientFactory that we can get a webdam client from.
   * @param string $credential_type
   *   The type of credentials to use.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(GrediClientFactory $client_factory, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->grediDamClient = $client_factory->getWithCredentials('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx');
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
  * {@inheritdoc}
  */
 public function getCustomerContent($customer) {

 }

  public function __call($name, array $arguments)
  {
    // TODO: Implement __call() method.
  }
}
