<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_gredi_image\GrediDamAuthServiceInterface;

class GrediDamAuthService implements GrediDamAuthServiceInterface {

  /**
   * Client id to identify the Gredi Dam client.
   *
   * @var string
   */
  const CLIENT = 'helsinki';

  /**
   * {@inheritdoc}
   */
  public static function getConfig(): ImmutableConfig {
    return \Drupal::config('gredi_dam.settings');
  }
}
