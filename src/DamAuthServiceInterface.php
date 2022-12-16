<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Gredi DAM authentication service interface.
 */
interface DamAuthServiceInterface {

  /**
   * Get cookie jar variable.
   *
   * @return \GuzzleHttp\Cookie\CookieJar|bool
   *   Authentication cookie.
   */
  public function getCookieJar();

  /**
   * Get customer ID.
   *
   * @return mixed
   *   Customer ID.
   */
  public function getCustomerId();

  /**
   * Method for authentication on Gredi API.
   *
   * @return bool
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function authenticate() : bool;

  /**
   * Check if is authenticated.
   *
   * @return bool
   */
  public function isAuthenticated() : bool;

  /**
   * Setter method for the session id.
   *
   * @param string $session
   *
   * @return void
   */
  public function setSessionId(string $session) :void;

  /**
   * Stores the session id to state.
   *
   * @param string $session
   *
   * @return void
   */
   function storeSessionId(string $session) :void;

  /**
   * Getter method for the session id.
   *
   * @return string
   */
  function getStoredSessionId() :string;

}
