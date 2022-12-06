<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Gredi DAM authentication service interface.
 */
interface DamAuthServiceInterface {

  /**
   * Return the Gredi DAM configs.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable configuration object.
   */
  public function getConfig(): ImmutableConfig;

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

}
