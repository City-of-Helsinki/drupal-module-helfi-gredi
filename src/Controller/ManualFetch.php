<?php

namespace Drupal\helfi_gredi_image\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use Drupal\Component\Serialization\Json;

/**
 * Defines helfi_gredi_image class.
 */
class ManualFetch extends ControllerBase {

  /**
   * Symfony\Component\DependencyInjection\ContainerAwareInterface definition.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
   */
  protected $queueFactory;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Inject services.
   */
  public function __construct(QueueFactory $queue, ClientInterface $httpClient) {
    $this->queueFactory = $queue;
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('http_client')
    );
  }

  /**
   * Clear worker queue.
   *
   * @return array
   *   Return markup array.
   */
  public function deleteQueue() {
    $this->queueFactory->get('gredi_image_fetch_queue')->deleteQueue();
    return [
      '#type' => 'markup',
      '#markup' => $this->t('The queue "gredi_image_fetch_queue" has been deleted'),
    ];
  }

  /**
   * Fetch data from Gredi API and create a queue item for each data.
   *
   * @return array
   *   Return array.
   */
  public function fetchData() {
  $loginSession = $this
    ->sessionLoginAPI('helsinki', 'apiuser', 'uFNL4SzULSDEPkmx');

  if ($loginSession->getStatusCode() == 200 && $loginSession->getReasonPhrase() == 'OK') {
    $getCookie = $loginSession->getHeader('Set-Cookie')[0];
    $subtring_start = strpos($getCookie, '=');
    $subtring_start += strlen('=');
    $size = strpos($getCookie, ';', $subtring_start) - $subtring_start;
    $result =  substr($getCookie, $subtring_start, $size);

    $cookieJar = CookieJar::fromArray([
      'JSESSIONID' => $result
    ], 'api4.materialbank.net');

    $userContent = $this->httpClient->request('GET', 'https://api4.materialbank.net/api/v1/customers/6/contents', [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'cookies' => $cookieJar
    ]);
    $posts = $userContent->getBody()->getContents();

    foreach (Json::decode($posts) as $post) {
      if ($post['fileType'] == 'file' && $post['mimeGroup'] = 'picture') {
        dump($post);
      }
    }
  }

  }

  /**
   * Create a session for the user.
   *
   * @param string $customer
   *   Customer name.
   *
   * @param string $username
   *   Username.
   *
   * @param string $password
   *   Password.
   *
   * @return array
   *   Return response.
   */
  public function sessionLoginAPI($customer, $username, $password) {
    return $this->httpClient
      ->request('POST', 'https://api4.materialbank.net/api/v1/sessions/', [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => '{
        "customer": "'. $customer . '",
        "username": "'. $username . '",
        "password": "'. $password . '"
      }'
    ]);
  }

}
