<?php

namespace Drupal\helfi_gredi_image;

use cweagans\webdam\Exception\InvalidCredentialsException;
use GuzzleHttp\Exception\ClientException;


/**
 * Overridden implementation of the cweagans php-webdam-client.
 */
class Client {

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



    // For error response body details:
    // @see \cweagans\webdam\tests\ClientTest::testInvalidClient().
    // @see \cweagans\webdam\tests\ClientTest::testInvalidGrant().
    // For successful auth response body details:
    // @see \cweagans\webdam\tests\ClientTest::testSuccessfulAuthentication().

  }


}
