<?php

namespace Drupal\helfi_gredi_image\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GredidamAuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new AcquiadamAuthController.
   */
  public function __contruct () {

  }

  /**
   * {@inheritdoc}
   */
  public static function create (ContainerInterface $container) {
    return new static();
  }

  /**
   * Menu callback from Acquia DAM to complete authorization process.
   */
  public function authenticate() {

  }
}
