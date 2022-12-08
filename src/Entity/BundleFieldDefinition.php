<?php

namespace Drupal\helfi_gredi_image\Entity;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Provides a field definition class for bundle fields.
 *
 * Core currently doesn't provide one, the hook_entity_bundle_field_info()
 * example uses BaseFieldDefinition, which is wrong. Tracked in #2346347.
 *
 * Copied from the Entity module, since it is not in Drupal core.
 *
 * @see \Drupal\entity\BundleFieldDefinition
 * @internal
 */
class BundleFieldDefinition extends BaseFieldDefinition {

  /**
   * {@inheritdoc}
   */
  public function isBaseField(): bool {
    return FALSE;
  }

}
