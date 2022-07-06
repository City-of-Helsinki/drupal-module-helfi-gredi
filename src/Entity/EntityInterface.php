<?php

namespace Drupal\helfi_gredi_image\Entity;

/**
 * Provides an interface that all entity classes must implement.
 */
interface EntityInterface {

  /**
   * Create new instance of the Entity given a JSON object from Gredi DAM API.
   *
   * @param string|object $json
   *   Either a JSON string or a json_decode()'d object.
   * @param string $folder_id
   *   Parent folder id.
   *
   * @return static
   *   An instance of whatever class this method is being called on.
   */
  public static function fromJson($json, $folder_id = NULL);

}
