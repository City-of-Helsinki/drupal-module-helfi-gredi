<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi\Plugin\views\field;

use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm as MediaEntityMediaLibrarySelectForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Media selection field for asset media type.
 *
 * @ViewsField("gredi_media_library_select_form")
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
  protected $entityTypeManager;

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

    // We rely on details coming from API to be in form_state.
    $assetsData = $form_state->get('assetsData');

    $form['#attached']['library'][] = 'helfi_gredi/media_library_selection';

    $media_ids = [];
    foreach ($selected_ids as $id) {
      if (empty($assetsData[$id])) {
        // This should not happen.
        continue;
      }

      /** @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager */
      $entityTypeManager = \Drupal::service('entity_type.manager');
      $existing_ids = $entityTypeManager
        ->getStorage('media')
        ->getQuery()
        ->condition('bundle', 'gredi_asset')
        ->condition('gredi_asset_id', $id)
        ->execute();

      if (!empty($existing_ids)) {
        // We should not have more than 1 with same gredi id.
        $mediaId = end($existing_ids);
        $media_ids[] = $mediaId;
      }
      else {
        /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
        $entityTypeManager = \Drupal::service('entity_type.manager');
        /** @var \Drupal\media\MediaTypeInterface $media_type */
        $media_type = $entityTypeManager->getStorage('media_type')
          ->load('gredi_asset');

        $currentLanguage = \Drupal::languageManager()->getDefaultLanguage()->getId();
        $entity = Media::create([
          'bundle' => $media_type->id(),
          'uid' => \Drupal::currentUser()->id(),
          'langcode' => $currentLanguage,
          'status' => 1,
          'gredi_asset_id' => $id,
          'gredi_removed' => FALSE,
        ]);
        /** @var \Drupal\helfi_gredi\Plugin\media\Source\GrediAsset $source */
        $source = $entity->getSource();
        $source_field_name = $source->getConfiguration()['source_field'];
        $source->setAssetData($assetsData[$id]);

        $assetName = $source->getMetadata($entity, 'name');
        $entity->set('name', $assetName);

        /** @var \Drupal\file\FileInterface $file */
        $file = $source->getMetadata($entity, 'original_file');
        if (empty($file)) {
          // Allow the opener service to handle the selection.
          $state = MediaLibraryState::fromRequest($request);
          return \Drupal::service('media_library.opener_resolver')
            ->get($state)
            ->getSelectionResponse($state, $media_ids)
            ->addCommand(new AlertCommand(t('Unable create or fetch file')));
        }
        else {
          $entity->set($source_field_name, ['target_id' => $file->id()]);
        }

        $entity->save();

        // Create all translations.
        $entity->getSource()->syncMediaFromGredi($entity);

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

    $form['#attached']['library'][] = 'helfi_gredi/media_library_selection';

    $assetsData = [];
    foreach ($this->view->result as $row_index => $row) {
      $entity = $this->getEntity($row);
      $externalId = $entity->get('gredi_asset_id')->value;
      $form[$this->options['id']][$row_index]['#return_value'] = $externalId;
      $asset_data = $entity->getSource()->getAssetData();
      $parentId = $asset_data['parentId'];
      $form[$this->options['id']][$row_index]['#parent_id'] = $parentId;
      /** @var \Drupal\helfi_gredi\Plugin\media\Source\GrediAsset $source */
      $source = $entity->getSource();
      $assetsData[$externalId] = $source->getAssetData();

      if (!empty($row->folder)) {
        $form[$this->options['id']][$row_index]['#type'] = 'hidden';
        $form[$this->options['id']][$row_index]['#value'] = $form[$this->options['id']][$row_index]['#return_value'];
        $form[$this->options['id']][$row_index]['#attributes']['class'][] = 'gredi-folder-id-input-selection';
        $form[$this->options['id']][$row_index]['#attributes']['data']['parent_id'] = $form[$this->options['id']][$row_index]['#parent_id'];
      }

    }
    // Setting the api result so that we don't have to
    // call again the API for details in updateWdiget.
    $form_state->set('assetsData', $assetsData);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(array &$form, FormStateInterface $form_state) {
    parent::viewsFormValidate($form, $form_state);
    $selected = array_filter($form_state->getValue($this->options['id']));
    if (empty($selected)) {
      $form_state->setErrorByName('', $this->t('No items selected.'));
    }
    if (count($selected) > 1) {
      $form_state->setErrorByName('', $this->t('Inserting entire folder is not allowed.'));
    }
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
