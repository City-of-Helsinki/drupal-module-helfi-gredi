<?php

namespace Drupal\helfi_gredi_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider to register helfi_gredi_test service.
 */
class HelfiGrediTestServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Override the 'helfi_gredi.dam_client' service with the mocked version.
    if ($container->hasDefinition('helfi_gredi.dam_client')) {
      $definition = $container->getDefinition('helfi_gredi.dam_client');
      $definition->setClass(GrediClientTest::class);
    }

  }

}
