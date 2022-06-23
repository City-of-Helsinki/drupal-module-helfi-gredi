<?php

namespace Drupal\helfi_gredi_image;

use cweagans\webdam\Client as OriginalClient;
use cweagans\webdam\Exception\InvalidCredentialsException;
use GuzzleHttp\Exception\ClientException;


/**
 * Overridden implementation of the cweagans php-webdam-client.
 */
class Client extends OriginalClient {

  /**
   * Authenticates a user.
   *
   * @param array $data
   *   An array of API parameters to pass. Defaults to password based
   *   authentication information.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function authenticate(array $data = []) {

    $url = 'https://api4.materialbank.net/api/v1/sessions/';
    if (empty($data)) {
      $data = [
        'headers' => [
          'Content-Type' => 'application/json'
        ],
        'body' => '{
        "customer": "'. $this->customer . '",
        "username": "'. $this->username . '",
        "password": "'. $this->password . '"
      }'
      ];
    }

    // For error response body details:
    // @see \cweagans\webdam\tests\ClientTest::testInvalidClient().
    // @see \cweagans\webdam\tests\ClientTest::testInvalidGrant().
    // For successful auth response body details:
    // @see \cweagans\webdam\tests\ClientTest::testSuccessfulAuthentication().
    try {
      $response = $this->client->request(
        "POST",
        $url,
        $data
      );

    }
    catch (ClientException $e) {
      // For bad auth, the WebDAM API has been observed to return either
      // 400 or 403, so handle those via InvalidCredentialsException.
      $status_code = $e->getResponse()->getStatusCode();
      if ($status_code == 400 || $status_code == 403) {
        $body = (string) $e->getResponse()->getBody();
        $body = json_decode($body);

        throw new InvalidCredentialsException(
          $body->error_description . ' (' . $body->error . ').'
        );
      }
      else {
        // We've received an error status other than 400 or 403; log it
        // and move on.
        \Drupal::logger('helfi_gredi_image')->error(
          'Unable to authenticate. DAM API client returned a @code exception code with the following message: %message',
          [
            '@code' => $status_code,
            '%message' => $e->getMessage(),
          ]
        );
      }
    }
    return $response;
  }


}
