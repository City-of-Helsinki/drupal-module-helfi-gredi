<?php

namespace Drupal\helfi_gredi\Plugin\QueueWorker;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media\Entity\Media;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "gredi_asset_update",
 *   title = @Translation("Updates Gredi image asset"),
 *   cron = {"time" = 90}
 * )
 */
class MetaUpdate extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($media_id) {

    $media = Media::load($media_id);
    if (empty($media) || !$media->get('active_external_asset')->value) {
      \Drupal::logger('helfi_gredi')
        ->log(RfcLogLevel::WARNING, 'Media with external id '
          . $media->get('active_external_asset')->value . ' was not found.');
      \Drupal::messenger()->addWarning(t('External asset no longer exists.'));
      return;
    }
    // External asset modified timestamp.
    $external_field_modified = $media->getSource()->getMetadata($media, 'modified');
    // Stored asset modified timestamp.
    $internal_field_modified = $media->get('gredi_modified')->value;
    // Set fields that needs to be updated NULL to let Media::prepareSave()
    // fill up the fields with the newest fetched data.
    $bundle = $media->getEntityType()->getBundleEntityType();

    $field_map = \Drupal::entityTypeManager()->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();

    // TODO: if we have 2 translations and if we retrieve the image, afterwards we add another
    // TODO: site language and try to sync from Gredi, it will not retrieve the third translation
    // TODO: because the external_field_modified === internal_field_modified at this point
    if ($external_field_modified > $internal_field_modified) {

      $media->set('gredi_modified', $external_field_modified);
      $apiLanguages = $media->getSource()->getMetadata($media, 'lang_codes');
      $siteLanguages = array_keys(\Drupal::languageManager()->getLanguages());
      $currentLanguage = \Drupal::languageManager()->getDefaultLanguage()->getId();

      // API uses SE for Swedish, so we hardcode here the mapping.
      // Loop through all the languages to check for new translations.
      foreach ($apiLanguages as $apiLangCode) {
        if ($apiLangCode == 'se') {
          $apiLangCode = 'sv';
        }
        if (!in_array($apiLangCode, $siteLanguages)) {
          continue;
        }
        if ($currentLanguage == $apiLangCode) {
          continue;
        }
        if ($media->hasTranslation($apiLangCode)) {
          continue;
        }
        $translation = $media->addTranslation($apiLangCode);
        $assetName = $media->getSource()->getMetadata($media, 'name');
        $translation->set('name', $assetName);
        $translation->set('field_alt_text', NULL);
        $translation->set('field_keywords', NULL);

        $source_field_name = $media->getSource()
          ->getConfiguration()['source_field'];
        if (!empty($file) && $translation->get($source_field_name)
            ->getFieldDefinition()
            ->isTranslatable()) {
          $translation->set($source_field_name, ['target_id' => $file->id()]);
        }
        $translation->save();
      }

      // Loop through all fields and set them on NULL to force fetch again.
      foreach ($field_map as $key => $field) {
        // Skip the original_file field.
        if ($key === 'original_file') {
          continue;
        }
        foreach ($media->getTranslationLanguages() as $langCode) {
          if ($langCode->getId() === $currentLanguage) {
            $media->set($field, NULL);
            continue;
          }
          $media->getTranslation($langCode->getId())->set($field, NULL);
        }
        $media->getTranslation($langCode->getId())->save();
          // Setting null will trigger media to fetch again the mapped values.
      }
      $media->save();
      // When a new translation is present in gredi, we need to create.
      // TODO we need to loop and set null through all translations.
      // TODO we should handle the case when a new language in gredi appears and we have that lang enabled -> create the translation in Drupal.
      // TODO maybe find a way to reuse code from MediaLibrarySelectForm in regards with translation?

      \Drupal::messenger()->addStatus(t('Gredi Asset synced successfully.'));
      \Drupal::logger('helfi_gredi')
        ->notice('Metadata for Gredi asset with id ' . $media_id);
    }

  }
}
