<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Config\ImmutableConfig;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Gredi DAM authentication service interface.
 */
interface GrediDamAuthServiceInterface {

  /**
   * Return the Gredi DAM configs.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable configuration object.
   */
  public static function getConfig(): ImmutableConfig;

  /**
   * Get cookie jar variable.
   *
   * @return \GuzzleHttp\Cookie\CookieJar|null
   *   Authentication cookie.
   */
  public function getCookieJar(): ?CookieJar;

  /**
   * Get customer ID.
   *
   * @return mixed
   *   Customer ID.
   */
  public function getCustomerId();

  /**
   * Get Gredi DAM Username from user profile.
   */
  public function getGrediUsername();

  /**
   * Get Gredi DAM Password from user profile.
   */
  public function getGrediPassword();

}
