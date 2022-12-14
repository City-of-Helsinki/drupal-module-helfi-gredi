<?php

namespace Drupal\helfi_gredi_image\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\media_library\Form\FileUploadForm;

/**
 * Creates a form to create media entities from uploaded files.
 *
 * @internal
 *   Form classes are internal.
 */
class GrediFileUploadForm extends FileUploadForm {

  /**
   * {@inheritdoc}
   */
  protected function validateMediaEntity(MediaInterface $media, array $form, FormStateInterface $form_state, $delta) {
    $file_id = $media->field_media_image->target_id;
    /** @var \Drupal\file\Entity\File $file_entity */
    $file_entity = File::load($file_id);

    /** @var \Drupal\helfi_gredi_image\Service\GrediDamClient $damClient */
    $damClient = \Drupal::service('helfi_gredi_image.dam_client');
    // Upload image to Gredi API.
    try {
      $asset_id = $damClient->uploadImage($file_entity);
      $media->set('gredi_asset_id', $asset_id);
      $media->set('gredi_modified', \Drupal::time()->getCurrentTime());
    }
    catch(\Exception $exception) {
      $form_state->setError($form['media'], 'Upload error');
    }
    $form_display = EntityFormDisplay::collectRenderDisplay($media, 'media_library');
    $form_display->extractFormValues($media, $form['media'][$delta]['fields'], $form_state);
    $form_display->validateFormValues($media, $form['media'][$delta]['fields'], $form_state);
  }
}
