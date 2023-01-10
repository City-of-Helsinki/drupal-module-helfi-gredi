<?php

namespace Drupal\helfi_gredi\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\helfi_gredi\Plugin\media\Source\GrediAsset;

/**
 * Plugin implementation of the 'Gredi thumbnail for media library' formatter.
 *
 * @FieldFormatter(
 *   id = "gredi_thumbnail",
 *   label = @Translation("Gredi thumbnail for media library"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
final class AssetThumbnailViewer extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];
    $parent = $items->getEntity();
    if ($parent->getSource() instanceof GrediAsset) {
      $thumbnails_list = $parent->getSource()->getMetadata($parent, 'thumbnail_uri');
      // @todo should we add the image style configured?
      $elements[0] = [
        '#theme' => 'image',
        '#uri' => $thumbnails_list,
      ];
    }
    return $elements;

  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Only run on our media type + field.
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }
    if ($field_definition->getName() !== 'thumbnail') {
      return FALSE;
    }
    return TRUE;
  }

}
