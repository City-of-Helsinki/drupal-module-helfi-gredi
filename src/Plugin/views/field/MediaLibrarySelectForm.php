<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi_image\Plugin\views\field;

use Drupal\acquia_dam\Entity\MediaEmbedsField;
use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm as MediaEntityMediaLibrarySelectForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\helfi_gredi_image\Entity\Asset;

/**
 * Media selection field for asset media type.
 *
 * @ViewsField("gredidam_media_library_select_form")
 *
 * @see \Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm
 *
 * @phpstan-ignore-next-line
 */
final class MediaLibrarySelectForm extends MediaEntityMediaLibrarySelectForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }



  /**
   * Submit handler for the media library select form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A command to send the selection to the current field widget.
   */
  public static function updateWidget(array &$form, FormStateInterface $form_state, Request $request) {
    $selected_ids = $form_state->getValue('media_library_select_form');
    $selected_ids = $selected_ids ? array_filter($selected_ids) : [];

    $assetsData = $form_state->get('assetsData');

    $media_ids = [];
    foreach ($selected_ids as $id) {
      if (empty($assetsData[$id])) {
        // TODO nasol
        continue;
      }
      // TODO Entity query by gredi_asset_id = $id - maybe in a service method if not already?
      // TODO if media exists, than skip the creation part.
      // TODO maybe check for changes !? modified since !?

      // Query for existing entities.
      /** @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager */
      $entityTypeManager = \Drupal::service('entity_type.manager');
      $existing_ids = $entityTypeManager
        ->getStorage('media')
        ->getQuery()
        ->condition('bundle', 'gredi_dam_assets')
        ->condition('gredi_asset_id', $id)
        ->execute();

//      $entities = $this->entityTypeManager->getStorage('media')
//        ->loadMultiple($existing_ids);
      if ($existing_ids) {
        $mediaId = end($existing_ids);
        // TODO should we check if modified since our copy?
        // TODO save so that it fetches the fields and translations.
        $media_ids[] = $mediaId;
      }
      else {
        /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
        $entityTypeManager = \Drupal::service('entity_type.manager');
        /** @var \Drupal\media\MediaTypeInterface $media_type */
        $media_type = $entityTypeManager->getStorage('media_type')
          ->load('gredi_dam_assets');

        $source_field = $media_type->getSource()
          ->getSourceFieldDefinition($media_type)
          ->getName();

        $entity = Media::create([
          'bundle' => $media_type->id(),
          'uid' => \Drupal::currentUser()->id(),
          'langcode' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
          // @todo Find out if we can use status from Gredi DAM.
          'status' => 1,
          'gredi_asset_id' => $id,
        ]);
        /** @var \Drupal\helfi_gredi_image\Plugin\media\Source\GredidamAsset $source */
        $source = $entity->getSource();
        $source->setAssetData($assetsData[$id]);

        $assetName = $source->getMetadata($entity, 'name');
        $entity->set('name', $assetName);

        $modified = $source->getMetadata($entity, 'modified');
        $entity->set('gredi_modified', $modified);

        $file = $source->getMetadata($entity, 'original_file');
        if (empty($file)) {
          \Drupal::messenger()->addError('Unable to fetch or create file');
        }
        else {
          $entity->set('field_media_image', ['target_id' => $file->id()]);
        }

        // TODO what changed/modified should we store?
//          'created' => strtotime($asset->created),
//          'changed' => strtotime($asset->created),

//        $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
//        // Check enabled languages.
//        $siteLanguages = array_keys($this->languageManager->getLanguages());
//
//        foreach ($asset->keywords as $key => $lang) {
//          if (in_array($key, $siteLanguages)) {
//            // For current language case no translation will be added.
//            if ($key == $currentLanguage) {
//              $entity->field_media_image = $file->id();
//              $entity->field_keywords = $asset->keywords[$key];
//              $entity->field_alt_text = $asset->alt_text[$key];
//              continue;
//            }
//            $entity->addTranslation($key, [
//              'name' => $asset->name,
//              'field_media_image' => [
//                'target_id' => $file->id(),
//              ],
//              'field_keywords' => (!empty($asset->keywords[$key]) ? $asset->keywords[$key] : ''),
//              'field_alt_text' => (!empty($asset->alt_text[$key]) ? $asset->alt_text[$key] : ''),
//            ]);
//          }
//        }
        $entity->save();
        $media_ids[] = $entity->id();
      }
    }

    // Allow the opener service to handle the selection.
    $state = MediaLibraryState::fromRequest($request);

    return \Drupal::service('media_library.opener_resolver')
      ->get($state)
      ->getSelectionResponse($state, $media_ids)
      ->addCommand(new CloseDialogCommand());
  }

  /**
   * Form constructor for the media library select form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);

    $assetsData = [];
    foreach ($this->view->result as $row_index => $row) {
      $entity = $this->getEntity($row);
      $externalId = $entity->get('gredi_asset_id')->value;
      $form[$this->options['id']][$row_index]['#return_value'] = $externalId;
      /** @var \Drupal\helfi_gredi_image\Plugin\media\Source\GredidamAsset $source */
      $source = $entity->getSource();
      $assetsData[$externalId] = $source->getAssetData();
    }
    $form_state->set('assetsData', $assetsData);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing here.
    // However, the field alias needs to be set. This is used for click sorting
    // in the Table style and used by ::clickSort().
    $this->field_alias = $this->realField;
  }

}
