<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Cookie\CookieJar;
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
  * {@inheritdoc}
  */
 public function getCustomerContent($customer) {
    return $this->grediDamClientFactory->getCustomerContent($customer);
 }


}
