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
    $field_id = $form_state->getTriggeringElement()['#field_id'];

    $selected_ids = $form_state->getValue('media_library_select_form');

    $selected_ids = $selected_ids ? array_filter($selected_ids) : [];

    // Fetch selected asset by id.
    /** @var \Drupal\helfi_gredi_image\Service\GrediDamClient $gredi_client */
    $gredi_client = \Drupal::service('helfi_gredi_image.dam_client');
    $asset = $gredi_client->getAsset(current($selected_ids));

    $location = 'public://gredidam';

    /** @var \Drupal\helfi_gredi_image\Service\AssetFileEntityHelper $fileHelper */
    $fileHelper = \Drupal::service('helfi_gredi_image.asset_file.helper');
    $file = $fileHelper->createNewFile($asset, $location);


//    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
//    $entityTypeManager = \Drupal::service('entity_type.manager');
//    /** @var \Drupal\media\MediaTypeInterface $media_type */
//    $media_type = $entityTypeManager->getStorage('media_type');
//      ->load($this->configuration['media_type']);

    // Get the source field for this type which stores the asset id.
//    $source_field = $media_type->getSource()
//      ->getSourceFieldDefinition($media_type)
//      ->getName();
//
//    $entity = Media::create([
//      'bundle' => $media_type->id(),
//      'uid' => $this->user->id(),
//      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
//      // @todo Find out if we can use status from Gredi DAM.
//      'status' => 1,
//      'name' => $asset->name,
//      'field_media_image' => [
//        'target_id' => $file->id(),
//      ],
//      $source_field => [
//        'asset_id' => $asset->external_id,
//      ],
//      'created' => strtotime($asset->created),
//      'changed' => strtotime($asset->created),
//    ]);
//
//    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
//    // Check enabled languages.
//    $siteLanguages = array_keys($this->languageManager->getLanguages());
//
//    foreach ($asset->keywords as $key => $lang) {
//      if (in_array($key, $siteLanguages)) {
//        // For current language case no translation will be added.
//        if ($key == $currentLanguage) {
//          $entity->field_media_image = $file->id();
//          $entity->field_keywords = $asset->keywords[$key];
//          $entity->field_alt_text = $asset->alt_text[$key];
//          continue;
//        }
//        $entity->addTranslation($key, [
//          'name' => $asset->name,
//          'field_media_image' => [
//            'target_id' => $file->id(),
//          ],
//          'field_keywords' => (!empty($asset->keywords[$key]) ? $asset->keywords[$key] : ''),
//          'field_alt_text' => (!empty($asset->alt_text[$key]) ? $asset->alt_text[$key] : ''),
//        ]);
//      }
//    }
//
//    $entity->save();

    // Allow the opener service to handle the selection.
    $state = MediaLibraryState::fromRequest($request);

    return \Drupal::service('media_library.opener_resolver')
      ->get($state)
      ->getSelectionResponse($state, $selected_ids)
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
    $form['#attributes']['class'] = ['js-media-library-views-form'];

    // Add an attribute that identifies the media type displayed in the form.
    if (isset($this->view->args[0])) {
      $form['#attributes']['data-drupal-media-type'] = $this->view->args[0];
    }

    // Render checkboxes for all rows.
    $form[$this->options['id']]['#tree'] = TRUE;
    foreach ($this->view->result as $row_index => $row) {
      $entity = $this->getEntity($row);
      $form[$this->options['id']][$row_index] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select @label', [
          '@label' => $entity->label(),
        ]),
        '#title_display' => 'invisible',
        // TODO change with asset->id
        '#return_value' => $entity->id(),
      ];
    }

    // The selection is persistent across different pages in the media library
    // and populated via JavaScript.
    $selection_field_id = $this->options['id'] . '_selection';
    $form[$selection_field_id] = [
      '#type' => 'hidden',
      '#attributes' => [
        // This is used to identify the hidden field in the form via JavaScript.
        'id' => 'media-library-modal-selection',
      ],
    ];

    // @todo Remove in https://www.drupal.org/project/drupal/issues/2504115
    // Currently the default URL for all AJAX form elements is the current URL,
    // not the form action. This causes bugs when this form is rendered from an
    // AJAX path like /views/ajax, which cannot process AJAX form submits.
    $query = $this->view->getRequest()->query->all();
    $query[FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
    $query['views_display_id'] = $this->view->getDisplay()->display['id'];
    $form['actions']['submit']['#ajax'] = [
      'url' => Url::fromRoute('media_library.ui'),
      'options' => [
        'query' => $query,
      ],
      'callback' => [static::class, 'updateWidget'],
      // The AJAX system automatically moves focus to the first tabbable
      // element of the modal, so we need to disable refocus on the button.
      'disable-refocus' => TRUE,
    ];

    $form['actions']['submit']['#value'] = $this->t('Select Assets');
    $form['actions']['submit']['#button_type'] = 'primary';
    $form['actions']['submit']['#field_id'] = $selection_field_id;
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
