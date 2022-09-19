<?php

namespace Drupal\helfi_gredi_image\Plugin\EntityBrowser\Widget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload;
use Drupal\Core\Url;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\MediaImageUpload;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Alter media image upload widget.
 *
 * @EntityBrowserWidget(
 *   id = "gredi_media_image_upload",
 *   label = @Translation("Gredi Upload images as media items"),
 *   description = @Translation("Upload widget that will create media entities of the uploaded images."),
 *   auto_select = FALSE
 * )
 */
class GrediMediaImageUpload extends MediaImageUpload {

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
   * GrediMediaImageUpload constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    WidgetValidationManager $validation_manager,
    ModuleHandlerInterface $module_handler,
    Token $token,
    AccountInterface $account,
    LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager, $module_handler, $token);
    $this->user = User::load($account->id());
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('module_handler'),
      $container->get('token'),
      $container->get('current_user'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadByProperties(['source' => 'gredidam_asset']);

    foreach ($media_types as $media_type) {
      $media_type_options[$media_type->id()] = $media_type->label();
    }

    if (empty($media_type_options)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t("You don't have media type of the Gredi DAM asset type. You should <a href='@link'>create one</a>", ['@link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media type'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $media_type_options,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    if (!$this->configuration['media_type'] || !($media_type = $this->entityTypeManager->getStorage('media_type')->load($this->configuration['media_type']))) {
      return ['#markup' => $this->t('The media type is not configured correctly.')];
    }

    $form = Upload::getForm($original_form, $form_state, $aditional_widget_parameters);
    $form['action_upload'] = [
      '#type' => 'hidden',
      '#value' => TRUE,
    ];
    if ($media_type->getSource()->getPluginId() != 'gredidam_asset') {
      return ['#markup' => $this->t('The configured media type is not using the gredidam plugin.')];
    }

    $form['upload']['#upload_validators']['file_validate_extensions'] = [$this->configuration['extensions']];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $files = Upload::prepareEntities($form, $form_state);
      $media_type = $this->entityTypeManager
        ->getStorage('media_type')->load($this->configuration['media_type']);

      $source_field = $media_type->getSource()
        ->getSourceFieldDefinition($media_type)
        ->getName();

      $entities = [];

      foreach ($files as $file) {
        $external_id = \Drupal::service('helfi_gredi_image.dam_client')
          ->uploadImage($file);
        $entity = Media::create([
          'bundle' => $media_type->id(),
          'uid' => $this->user->id(),
          'lang' => $this->languageManager->getCurrentLanguage()->getId(),
          'status' => 1,
          'name' => $file->label(),
          'field_media_image' => [
            'target_id' => $file->id(),
          ],
          $source_field => [
            'asset_id' => $external_id,
          ],
        ]);
        $entity->save();
        $form_state->setUserInput([
          'media' => [
            $entity,
          ],
        ]);
        $entities[] = $entity;
      }

    }
    // Add the new entity to the array of returned entities.
    $this->selectEntities($entities, $form_state);
  }

}
