<?php

namespace Drupal\helfi_gredi\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\helfi_gredi\GrediClient;
use Drupal\media\MediaInterface;
use Drupal\media_library\Form\FileUploadForm;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\OpenerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to create media entities from uploaded files.
 *
 * @internal
 *   Form classes are internal.
 */
class GrediFileUploadForm extends FileUploadForm {

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $renderer;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Gredi client.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  protected $damClient;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * GrediFileUploadForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\media_library\MediaLibraryUiBuilder $library_ui_builder
   *   The media library UI builder.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\media_library\OpenerResolverInterface $opener_resolver
   *   The opener resolver.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\file\FileRepositoryInterface|null $file_repository
   *   The file repository service.
   * @param \Drupal\helfi_gredi\GrediClient $damClient
   *   The Gredi client service.
   * @param \Drupal\Component\Datetime\TimeInterface $timeManager
   *   The time manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MediaLibraryUiBuilder $library_ui_builder, ElementInfoManagerInterface $element_info, RendererInterface $renderer, FileSystemInterface $file_system, OpenerResolverInterface $opener_resolver, FileUsageInterface $file_usage, FileRepositoryInterface $file_repository = NULL, GrediClient $damClient, TimeInterface $timeManager) {
    parent::__construct($entity_type_manager, $library_ui_builder, $element_info, $renderer, $file_system, $opener_resolver, $file_usage, $file_repository);
    $this->entity_type_manager = $entity_type_manager;
    $this->damClient = $damClient;
    $this->timeManager = $timeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_library.ui_builder'),
      $container->get('element_info'),
      $container->get('renderer'),
      $container->get('file_system'),
      $container->get('media_library.opener_resolver'),
      $container->get('file.usage'),
      $container->get('file.repository'),
      $container->get('helfi_gredi.dam_client'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateMediaEntity(MediaInterface $media, array $form, FormStateInterface $form_state, $delta) {

    // Retrieve meta fields from config and input values.
    $bundle = $media->getEntityType()->getBundleEntityType();
    $field_map = $this->entity_type_manager->getStorage($bundle)
      ->load($media->getSource()->getPluginId())->getFieldMap();

    $inputs = [];
    foreach ($field_map as $key => $field) {
      if (is_int($key)) {
        foreach ($form_state->getValues()['media'][$delta]['fields'] as $name => $properties) {
          if ($name === $field) {
            $inputs[$field] = $properties[$delta]['value'];
            break;
          }
        }
      }
    }
    $inputs += [
      'langcode' => $form_state->getValues()['media'][$delta]['fields']['langcode'][$delta]['value']
    ];

    // Upload image to Gredi API.
    try {
      $asset_id = $this->damClient->uploadImage($inputs, $media, FALSE);
      $media->set('gredi_asset_id', $asset_id);
      $media->set('gredi_modified', $this->timeManager->getCurrentTime());
    }
    catch (\Exception $exception) {
      \Drupal::messenger()->addError(t('Failed to upload image.'));
      $form_state->setError($form['media'], 'Upload error');
    }
    $form_display = EntityFormDisplay::collectRenderDisplay($media, 'media_library');
    $form_display->extractFormValues($media, $form['media'][$delta]['fields'], $form_state);
    $form_display->validateFormValues($media, $form['media'][$delta]['fields'], $form_state);
  }

}
