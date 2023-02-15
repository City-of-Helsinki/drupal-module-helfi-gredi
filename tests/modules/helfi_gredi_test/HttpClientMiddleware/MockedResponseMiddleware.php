<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi_test\HttpClientMiddleware;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Intercepts API requests to the DAM to return mocked data.
 */
final class MockedResponseMiddleware {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Constructs a new MockedResponseMiddleware object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Middleware callable to handle mocked responses.
   */
  public function __invoke(): callable {
    return function (callable $handler): callable {
      return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
        if ($request->getUri()->getHost() === 'test.widencollective.disconnect') {
          return new FulfilledPromise(new Response(200));
        }
        if ($request->getUri()->getHost() === 'test.widencollective.connect') {
          return new FulfilledPromise(new Response(200));
        }
        if ($request->getUri()->getHost() === 'api.widencollective.com') {
          $path = $request->getUri()->getPath();
          if ($path === '/v2/test') {
            if ($request->getHeader('Authorization')[0] === 'Bearer wat_laser_b1d3c61e03c65d0650550f35a330249e') {
              return new FulfilledPromise(new Response(200));
            }
            return new FulfilledPromise(new Response(401));
          }
          if ($path === '/v2/oauth/access-token') {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/access-token-using-refresh-token.json");
            return new FulfilledPromise(new Response(200, [], $response));
          }
        }

        if ($request->getUri()->getHost() === 'previews.us-east-1.widencdn.net') {
          $image = file_get_contents(__DIR__ . "/../../../../fixtures/preview-thumbnail.png");
          return new FulfilledPromise(
            new Response(200, [
              'Content-Type' => 'image/png',
              'Content-Length' => strlen($image),
            ], $image)
          );
        }
        if ($request->getUri()->getHost() === 'laser.widen.net' && str_contains($request->getUri()->getPath(), '/content')) {
          $parts = explode('/', $request->getUri()->getPath());
          $format = $parts[3];
          if ($format === 'web') {
            $image = file_get_contents(__DIR__ . "/../../../../fixtures/preview-thumbnail.png");
            $header = [
              'Content-Type' => 'image/png',
              'Content-Length' => strlen($image),
            ];
            return new FulfilledPromise(new Response(200, $header, $image));
          }
        }
        $path = $request->getUri()->getPath();
        if ($path === '/collective.ping') {
          return new FulfilledPromise(new Response(200));
        }
        if (!str_contains($request->getUri()->getHost(), 'widencollective.com')) {
          return $handler($request, $options);
        }
        $query = [];
        parse_str(urldecode($request->getUri()->getQuery()), $query);
        $search_mapping = [
          'pdf' => 'Explorer',
          'video' => 'SD-Social',
          'SpinSet' => 'eudaimonia_spin',
          'image' => '422-lake-shore',
          'office' => 'Best',
        ];
        $search_query = $query['query'] ?? '';
        // Handle trigger for a 4xx response.
        if (strpos($search_query, '4xx_error') !== FALSE) {
          return new FulfilledPromise(new Response(400));
        }
        // Handle trigger for a 5xx response.
        if (strpos($search_query, '5xx_error') !== FALSE) {
          return new FulfilledPromise(new Response(500));
        }
        // Media library search.
        if ($path === '/v2/assets/search'
          && (preg_match("/ft:\((\w*)\)/i", $search_query, $matches) === 1
            || preg_match("/ff:\((\w*)\)/i", $search_query, $matches) === 1)
        ) {
          $file_type = strtok($search_query, ' ');
          if ($file_type === 'ft:(' . $matches[1] . ')' || $file_type === 'ff:(' . $matches[1] . ')') {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/$matches[1]/getSearch.json");
          }
          elseif (strpos($search_query, $search_mapping[$matches[1]]) !== FALSE) {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/$matches[1]/getSearchFile.json");
          }
          elseif (preg_match('/assettype:\((\w*)\)/i', $search_query, $results) === 1) {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/search/assettype-$results[1].json");
          }
          elseif (preg_match('/keywords:\((\w*)\)/i', $search_query, $results) === 1) {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/search/keywords-$results[1].json");
          }
          else {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/$matches[1]/getSearchEmpty.json");
          }
          assert($response !== FALSE);
          return new FulfilledPromise(new Response(200, [], $response));
        }
        if (str_contains($search_query, 'lastEditDate')) {
          $response = file_get_contents(__DIR__ . "/../../../../fixtures/lastEditedSearch.json");
          return new FulfilledPromise(new Response(200, [], $response));
        }
        if (strpos($path, '/v2/assets/') === 0) {
          $asset_id = str_replace('/v2/assets/', '', $path);
          if (file_exists(__DIR__ . "/../../../../fixtures/$asset_id.json")) {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/$asset_id.json");
            // Adjust the mocked response so that a valid thumbnail URL is
            // available. Thumbnail URLs are temporary and generated when the
            // asset is fetched from the API. Using a mock response prevents
            // fetching that thumbnail.
            $data = Json::decode($response);

            $status_code = $asset_id === 'c2bbed58-427f-43f7-91d8-c380307dac67' ? 404 : 200;
            return new FulfilledPromise(new Response($status_code, [], Json::encode($data)));
          }
        }
        if ($path === '/api/rest/integrationlink') {
          $auth_content = json_decode($request->getBody()->getContents());
          // If register for node we need a different integration link id.
          if (str_contains($auth_content->url, 'node')) {
            $response = file_get_contents(__DIR__ . '/../../../../fixtures/integration_links/postIntegrationLinkNode.json');
            assert($response !== FALSE);

            $data = Json::decode($response);
            return new FulfilledPromise(new Response(200, [], Json::encode($data)));
          }
          $response = file_get_contents(__DIR__ . '/../../../../fixtures/integration_links/postIntegrationLink.json');
          assert($response !== FALSE);

          $data = Json::decode($response);
          return new FulfilledPromise(new Response(200, [], Json::encode($data)));
        }
        if ($path === '/api/rest/integrationlink/ae93661e-bc39-4997-8f7d-c957aaade231') {
          // Integration link delete request.
          return new FulfilledPromise(new Response(200));
        }
        if ($path === '/api/rest/integrationlink/ae93661e-bc39-4997-8f7d-c957aaade238') {
          return new FulfilledPromise(new Response(204));
        }
        if ($path == '/api/rest/oauth/token') {
          $auth_content = json_decode($request->getBody()->getContents());
          if ($auth_content->authorization_code == 'server_error') {
            return new FulfilledPromise(new Response(502));
          }
          elseif ($auth_content->authorization_code == 'client_error') {
            return new FulfilledPromise(new Response(403));
          }
          elseif ($auth_content->authorization_code == 'pass') {
            $response = file_get_contents(__DIR__ . '/../../../../fixtures/authBody.json');
            assert($response !== FALSE);
            $data = Json::decode($response);
            return new FulfilledPromise(new Response(200, [], Json::encode($data)));
          }

        }
        if ($path == '/api/rest/oauth/logout') {
          return new FulfilledPromise(new Response(200));
        }
        if ($path === '/v2/metadata/fields/viewable') {
          if ($query['field_types'] === 'text,text_short,text_long') {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/viewableTextTypes.json");
          }
          elseif ($query['field_types'] === 'selection_list') {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/viewableSelectList.json");
          }
          elseif ($query['field_types'] === 'all') {
            $response = file_get_contents(__DIR__ . "/../../../../fixtures/viewableTextTypes.json");
          }
          assert($response !== FALSE);
          return new FulfilledPromise(new Response(200, [], $response));

        }
        if ($path == '/v2/metadata/assettype/vocabulary') {
          $response = file_get_contents(__DIR__ . "/../../../../fixtures/assetTypeVocabulary.json");
          assert($response !== FALSE);
          return new FulfilledPromise(new Response(200, [], $response));
        }
        if (preg_match('/\/api\/rest\/asset\/uuid\/(.*)\/assetversions/', $path, $matches) === 1) {
          $response = file_get_contents(__DIR__ . "/../../../../fixtures/versions/$matches[1].json");
          assert($response !== FALSE);
          return new FulfilledPromise(new Response(200, [], $response));
        }
        if ($path === '/v2/categories') {
          $response = file_get_contents(__DIR__ . '/../../../../fixtures/categories.json');
          assert($response !== FALSE);
          return new FulfilledPromise(new Response(200, [], $response));
        }
        if ($path === '/v2/categories/Testing') {
          return new FulfilledPromise(new Response(200, [], Json::encode([
            'items' => [],
          ])));
        }
        $this->logger->warning(
          sprintf("The DAM client requested '%s' which is not mocked", $request->getUri())
        );
        throw new \RuntimeException('Request URI not mocked: ' . $request->getUri());
      };
    };
  }

}
