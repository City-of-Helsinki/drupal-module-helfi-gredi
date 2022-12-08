<?php

namespace Drupal\helfi_gredi_image\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_gredi_image\Plugin\media\Source\GredidamAsset;

/**
 * Plugin implementation of the 'acquia dam image' formatter.
 *
 * @FieldFormatter(
 *   id = "gredi_dam_thumbnail",
 *   label = @Translation("Gredi Dam Image Thumbnail"),
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
    if ($parent->getSource() instanceof GredidamAsset) {
      $thumbnails_list = $parent->getSource()->getMetadata($parent, 'thumbnail_uri');
//      $size = $this->getSetting('thumbnail_size') ?: '300px';
//      $dimension = (int) filter_var($size, FILTER_SANITIZE_NUMBER_INT);
      $elements[0] = [
        '#theme' => 'image',
//        '#width' => $dimension,
//        '#height' => $dimension,
        '#uri' => $thumbnails_list,
//        '#alt' => $this->t('@filename preview', [
//          '@filename' => $parent->getName(),
//        ]),
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
