<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFactory.
 *
 * Factory class for Client.
 */
class GrediClientFactory implements ContainerInjectionInterface {

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $guzzleClient;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   */
  public function __construct(ClientInterface $guzzleClient) {
    $this->guzzleClient = $guzzleClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Gets a base DAM Client object using the specified credentials.
   *
   * @param string $customer
   *   The customer to authenticate with.
   * @param string $username
   *   The username to authenticate with.
   * @param string $password
   *   The password to authenticate with.
   *
   * @return \Drupal\helfi_gredi_image\Client
   *   The Gredi DAM client.
   */
  public function getWithCredentials($customer, $username, $password) {
    return new Client(
      $this->guzzleClient,
      $customer,
      $username,
      $password
    );
  }

}
