<?php

namespace Drupal\helfi_gredi_image;

/**
 * Interface GredidamInterface.
 *
 * Defines the Gredi dam interface.
 */
interface GredidamInterface {

  /**
   * Passes method calls through to the DAM client object.
   *
   * @param string $name
   *   The name of the method to call.
   * @param array $arguments
   *   An array of arguments.
   *
   * @return mixed
   *   Returns whatever the dam client returns.
   */
  public function __call($name, array $arguments);

  /*
   * Get customer content.
   *
   * @param int $customer
   *   Customer id.
   * @param array $params
   *   Get params.
   *
   * @return array
   *   Return the customer content.
   */
  public function getCustomerContent($customer, $params = []);

}

