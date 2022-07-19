<?php

namespace Drupal\helfi_gredi_image;

use Drupal\Core\Config\ImmutableConfig;

interface GrediDamAuthServiceInterface {

  /**
   * Return the Gredi DAM configs.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable configuration object.
   */
  public static function getConfig(): ImmutableConfig;
}
