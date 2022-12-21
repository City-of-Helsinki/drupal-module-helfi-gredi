<?php

namespace Drupal\helfi_gredi_image;

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
   *   Return bool.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function authenticate() : bool;

  /**
   * Check if is authenticated.
   *
   * @return bool
   *   Return bool.
   */
  public function isAuthenticated() : bool;

  /**
   * Setter method for the session id.
   *
   * @param string $session
   *   Session parameter.
   */
  public function setSessionId(string $session);

  /**
   * Stores the session id to state.
   *
   * @param string $session
   *   Session parameter.
   */
  public function storeSessionId(string $session);

  /**
   * Getter method for the session id.
   *
   * @return string
   *   Stored session id.
   */
  public function getStoredSessionId() :string;

}
