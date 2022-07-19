<?php

namespace Drupal\helfi_gredi_image\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\entity_browser\WidgetBase;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\Form\GrediDamConfigForm;
use Drupal\helfi_gredi_image\Service\GrediDamAuthService;
use Drupal\helfi_gredi_image\Service\GrediDamClient;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\media\MediaSourceManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use GuzzleHttp\Psr7\Utils;
use Drupal\file\Entity\File;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "gredidam",
 *   label = @Translation("Gredi DAM"),
 *   description = @Translation("Gredi DAM image browser"),
 *   auto_select = FALSE
 * )
 */
class Gredidam extends WidgetBase {

  /**
   * The dam interface.
   *
   * @var \Drupal\helfi_gredi_image\Service\GrediDamClient
   */
  protected $grediDamClient;

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
   * A module handler object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A media source manager.
   *
   * @var \Drupal\media\MediaSourceManager
   */
  protected $sourceManager;

  /**
   * An entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * User data manager.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Drupal RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $guzzleClient;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Asset var.
   *
   * @var \Drupal\helfi_gredi_image\Entity\Asset
   */
  protected $assets;

  /**
   * Category var.
   *
   * @var \Drupal\helfi_gredi_image\Entity\Category
   */
  protected $currentCategory;

  /**
   * Gredi dam auth service.
   */
  protected $grediDamAuthService;

  /**
   * Messenger var.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * Gredidam constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    WidgetValidationManager $validation_manager,
    GrediDamClient $grediDamClient,
    AccountInterface $account,
    LanguageManagerInterface $languageManager,
    ModuleHandlerInterface $moduleHandler,
    MediaSourceManager $sourceManager,
    UserDataInterface $userData,
    RequestStack $requestStack,
    ConfigFactoryInterface $config,
    ClientInterface $guzzleClient,
    PagerManagerInterface $pagerManager,
    GrediDamAuthService $grediDamAuthService,
    MessengerInterface $messenger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->grediDamClient = $grediDamClient;
    $this->user = $account;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $moduleHandler;
    $this->sourceManager = $sourceManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->userData = $userData;
    $this->requestStack = $requestStack;
    $this->config = $config;
    $this->guzzleClient = $guzzleClient;
    $this->pagerManager = $pagerManager;
    $this->grediDamAuthService = $grediDamAuthService;
    $this->messenger = $messenger;
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
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('helfi_gredi_image.dam_client'),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('plugin.manager.media.source'),
      $container->get('user.data'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('pager.manager'),
      $container->get('helfi_gredi_image.auth_service'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $media_type_options = [];
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
  public function defaultConfiguration() {
    return [
      'media_type' => NULL,
      'submit_text' => $this->t('Select assets'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $media_type_storage = $this->entityTypeManager->getStorage('media_type');
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    if (!$this->configuration['media_type']
      || !($media_type = $media_type_storage->load($this->configuration['media_type']))) {
      return ['#markup' => $this->t('The media type is not configured correctly.')];
    }
    elseif ($media_type->id() != 'gredi_dam_assets') {
      return ['#markup' => $this->t('The configured media type is not using the gredi_image plugin.')];
    }
    // If this is not the current entity browser widget being rendered.
    elseif ($this->uuid() != $form_state->getStorage()['entity_browser_current_widget']) {
      // Return an empty array.
      return [];
    }
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $user = User::load($this->user->id());

    if (!isset($user->field_gredi_dam_username->getValue()[0]['value']) || !isset($user->field_gredi_dam_password->getValue()[0]['value'])) {
      return $this->messenger->addError($this->t('You have to fill Gredi DAM Username and Password in @user_profile', [
        '@user_profile' => Link::createFromRoute(t('user edit'), 'entity.user.edit_form', [
          'user' => $this->user->id()])->toString()
        ]));
    }


    $config = $this->config->get('gredi_dam.settings');

    $modulePath = $this->moduleHandler->getModule('helfi_gredi_image')->getPath();
    // Attach the modal library.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $trigger_elem = $form_state->getTriggeringElement();
    $this->currentCategory = new Category();
    // Default current category name to NULL which will act as root category.
    $this->currentCategory->id = NULL;
    $this->currentCategory->name = NULL;
    $this->currentCategory->parts = [];

    // Initialize pagination variables.
    $page = 0;
    $offset = 0;

    $num_per_page = $config->get('num_assets_per_page') ?? GrediDamConfigForm::NUM_ASSETS_PER_PAGE;
    if (isset($form_state->getCompleteForm()['widget'])
      && isset($trigger_elem) && $trigger_elem['#name'] != 'filter_sort_reset') {
      // Assign $widget for convenience.
      $widget = $form_state->getCompleteForm()['widget'];

      if (isset($widget['actions']['pager-container'])
        && is_numeric($widget['actions']['pager-container']['#page'])) {
        // Set the page number to the value stored in the form state.
        $page = intval($widget['actions']['pager-container']['#page']);
      }

      if (isset($widget['asset-container']) && isset($widget['asset-container']['#gredidam_category'])) {
        // Set current category to the value stored in the form state.
        $this->currentCategory->id = $widget['asset-container']['#gredidam_category']['id'];
        $this->currentCategory->parts[] = $trigger_elem['#gredidam_category']['name'];

      }
      if ($form_state->getValue('assets')) {
        $current_selections = $form_state
          ->getValue('current_selections', []) + array_filter($form_state->getValue('assets', []));

        $form['current_selections'] = [
          '#type' => 'value',
          '#value' => $current_selections,
        ];
      }
    }

    if (isset($trigger_elem)) {
      if ($trigger_elem['#name'] === 'gredidam_category') {
        // Update the required information of selected category.
        $this->currentCategory->id = $trigger_elem['#gredidam_category']['id'];
        $this->currentCategory->name = $trigger_elem['#gredidam_category']['name'];
        $this->currentCategory->parts[] = $trigger_elem['#gredidam_category']['name'];
      }

      if ($trigger_elem['#name'] === 'gredidam_pager') {
        $this->currentCategory->name = $trigger_elem['#current_category']->name ?? NULL;
        $this->currentCategory->parts[] = $trigger_elem['#gredidam_category']['name'] ?? NULL;

        // Set the current category id to the id of the category, was clicked.
        $page = intval($trigger_elem['#gredidam_page']);
        $offset = $num_per_page * $page;
      }

      if ($trigger_elem['#name'] === 'breadcrumb') {
        $this->currentCategory->name = $trigger_elem["#category_name"];
        $this->currentCategory->parts[] = $trigger_elem['#gredidam_category']['name'];
      }
    }

    // Get breadcrumb.
    $form += $this->getBreadcrumb($this->currentCategory);

    // Add the filter and sort options to the form.
    $form += $this->getFilterSort();

    // Get folders content from customer id.
    $folders_content = $this->grediDamClient->getCustomerContent()['folders'];

    $contents = [];

    $form['asset-container'] = [
      '#type' => 'container',
      // Store the current category id in the form so it can be retrieved
      // from the form state.
      '#gredidam_category_id' => $this->currentCategory->id,
      '#attributes' => [
        'class' => ['gredidam-asset-browser row'],
      ],
    ];

    // Parameters for pagination.
    $params = [
      'limit' => $num_per_page,
      'offset' => $offset,
    ];

    if ($this->currentCategory->id == NULL) {
      $contents[] = $this->grediDamClient->getCustomerContent($params)['assets'];

      $this->getCategoryFormElements($folders_content, $modulePath, $form);
    }
    else {
      $assets = $this->grediDamClient->getFolderContent($this->currentCategory->id, $params)['assets'];
      if (isset($assets)) {
        $contents[] = $assets;
      }
      $folder_content = $this->grediDamClient->getFolderContent($this->currentCategory->id);
      if (isset($folder_content['folders'])) {
        $this->getCategoryFormElements($folder_content['folders'], $modulePath, $form);
      }
    }

    $initial_key = 0;
    $this->assets = [];

    foreach ($contents as $content) {
      if (empty($content)) {
        continue;
      }
      foreach ($content as $asset) {
        $this->assets[$asset->id] = $this->layoutMediaEntity($asset, $initial_key);
        $initial_key++;
      }
    }

    $form['asset-container']['assets'] = [
      '#type' => 'checkboxes',
      '#theme_wrappers' => ['checkboxes__gredidam_assets'],
      '#title_display' => 'invisible',
      '#options' => $this->assets,
      '#attached' => [
        'library' => [
          'helfi_gredi_image/asset_browser',
        ],
      ],
    ];

    if (isset($this->currentCategory->id)) {
      $totalAssets = count($this->grediDamClient->getFolderContent($this->currentCategory->id));
    }
    else {
      $totalAssets = count($this->grediDamClient->getCustomerContent()['assets']);
    }

    if ($totalAssets > $num_per_page) {
      // Add the pager to the form.
      $form['actions'] += $this
        ->getPager($totalAssets, $page, $num_per_page, 'listing', $this->currentCategory);
    }

    return $form;
  }

  /**
   * Create pagination and set current page.
   *
   * @param int $total_count
   *   Total count.
   * @param int $page
   *   Page.
   * @param int $num_per_page
   *   Number per page.
   * @param string $page_type
   *   Page type.
   * @param \Drupal\helfi_gredi_image\Entity\Category|null $category
   *   Category.
   *
   * @return array
   *   Form.
   */
  public function getPager(int $total_count, int $page, int $num_per_page, string $page_type = "listing", Category $category = NULL) {
    // Add container for pager.
    $form['pager-container'] = [
      '#type' => 'container',
      // Store page number in container so it can be retrieved from form state.
      '#page' => $page,
      '#attributes' => [
        'class' => ['gredidam-asset-browser-pager'],
      ],
    ];
    // If not on the first page.
    if ($page > 0) {
      // Add a button to go to the first page.
      $form['pager-container']['first'] = [
        '#type' => 'button',
        '#value' => '<<',
        '#name' => 'gredidam_pager',
        '#page_type' => $page_type,
        '#current_category' => $category,
        '#gredidam_page' => 0,
        '#attributes' => [
          'class' => ['page-button', 'page-first'],
        ],
      ];
      // Add a button to go to the previous page.
      $form['pager-container']['previous'] = [
        '#type' => 'button',
        '#value' => '<',
        '#name' => 'gredidam_pager',
        '#page_type' => $page_type,
        '#gredidam_page' => $page - 1,
        '#current_category' => $category,
        '#attributes' => [
          'class' => ['page-button', 'page-previous'],
        ],
      ];
    }
    // Last available page based on number of assets in category
    // divided by number of assets to show per page.
    $last_page = floor(($total_count - 1) / $num_per_page);
    // First page to show in the pager.
    // Try to put the button for the current page in the middle by starting at
    // the current page number minus 4.
    $start_page = max(0, $page - 4);
    // Last page to show in the pager.  Don't go beyond the last available page.
    $end_page = min($start_page + 9, $last_page);
    // Create buttons for pages from start to end.
    for ($i = $start_page; $i <= $end_page; $i++) {
      $form['pager-container']['page_' . $i] = [
        '#type' => 'button',
        '#value' => $i + 1,
        '#name' => 'gredidam_pager',
        '#page_type' => $page_type,
        '#gredidam_page' => $i,
        '#current_category' => $category,
        '#attributes' => [
          'class' => [($i == $page ? 'page-current' : ''), 'page-button'],
        ],
      ];
    }
    // If not on the last page.
    if ($end_page > $page) {
      // Add a button to go to the next page.
      $form['pager-container']['next'] = [
        '#type' => 'button',
        '#value' => '>',
        '#name' => 'gredidam_pager',
        '#current_category' => $category,
        '#page_type' => $page_type,
        '#gredidam_page' => $page + 1,
        '#attributes' => [
          'class' => ['page-button', 'page-next'],
        ],
      ];
      // Add a button to go to the last page.
      $form['pager-container']['last'] = [
        '#type' => 'button',
        '#value' => '>>',
        '#name' => 'gredidam_pager',
        '#current_category' => $category,
        '#gredidam_page' => $last_page,
        '#page_type' => $page_type,
        '#attributes' => [
          'class' => ['page-button', 'page-last'],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $media_bundle = $this->entityTypeManager->getStorage('media_type')
        ->load('gredi_dam_assets');

      // Load the file settings to validate against.
      $field_map = $media_bundle->getFieldMap();

      if (!isset($field_map['media_image'])) {
        $message = $this->t('Missing file mapping. Check your media configuration.');
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
        return;
      }

      // The form input uses checkboxes which returns zero for unchecked assets.
      // Remove these unchecked assets.
      $assets = array_values($form_state->getUserInput()['assets']);
      $content = [];
      foreach ($assets as $asset) {
        if ($asset == NULL) {
          continue;
        }
        $content[] = $asset;
      }
      // Get the cardinality for the media field that is being populated.
      $field_cardinality = $form_state->get([
        'entity_browser',
        'validators',
        'cardinality',
        'cardinality',
      ]);

      if (!count($content)) {
        $form_state->setError($form['widget']['asset-container'], $this->t('Please select an asset.'));
        return;
      }

      if (count($content) != 1) {
        $form_state->setError($form['widget']['asset-container'], $this->t('You can select maximum 1 asset.'));
        return;
      }

      // If the field cardinality is limited and the number of assets selected
      // is greater than the field cardinality.
      if ($field_cardinality > 0 && count($content) > $field_cardinality) {
        $message = $this->formatPlural($field_cardinality,
          'You can not select more than 1 entity.',
          'You can not select more than @count entities.');

        $form_state->setError($form['widget']['asset-container']['assets'], $message);
        return;
      }

      // Get information about the file field used to handle the asset file.
      $field_definitions = $this->entityFieldManager
        ->getFieldDefinitions('media', $media_bundle->id());
      $field_definition = $field_definitions[$field_map['media_image']]->getItemDefinition();

      // Invoke the API to get all the information about the selected assets.
      $expand = ['meta', 'attachments'];
      $dam_assets = $this->grediDamClient->getMultipleAsset($content, $expand);

      // If the media is only referencing images, we only validate that
      // referenced assets are images. We don't check the extension as we are
      // downloading the png version anyway.
      // Get the list of allowed extensions for this media bundle.
      $file_extensions = $field_definition->getSetting('file_extensions');
      $supported_extensions = explode(',',
          preg_replace('/,?\s/', ',', $file_extensions));

      // Browse the selected assets to validate the extensions are allowed.
      foreach ($dam_assets as $asset) {
        $filetype = pathinfo($asset->name, PATHINFO_EXTENSION);
        $type_is_supported = in_array(strtolower($filetype), $supported_extensions);

        if (!$type_is_supported) {
          $message = $this
            ->t('Please make another selection.
              The "@filetype" file type is not one of the supported file types (@supported_types).', [
                '@filetype' => $filetype,
                '@supported_types' => implode(', ', $supported_extensions),
              ]);
          $form_state->setError($form['widget']['asset-container']['assets'], $message);
        }
      }
    }
  }

  /**
   * Create form elements for sorting and filtering/searching.
   */
  public function getFilterSort() {
    // Add container for pager.
    $form['filter-sort-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['filter-sort-container'],
      ],
    ];
    // Add dropdown for sort by.
    $form['filter-sort-container']['sortby'] = [
      '#type' => 'select',
      '#title' => 'Sort by',
      '#options' => [
        'filename' => $this->t('File name'),
        'size' => $this->t('File size'),
        'created_date' => $this->t('Date created'),
        'last_update_date' => $this->t('Date modified'),
      ],
      '#default_value' => 'created_date',
    ];
    // Add dropdown for sort direction.
    $form['filter-sort-container']['sortdir'] = [
      '#type' => 'select',
      '#title' => 'Sort direction',
      '#options' => [
        'asc' => $this->t('Ascending'),
        'desc' => $this->t('Descending'),
      ],
      '#default_value' => 'asc',
    ];
    // Add dropdown for filtering on asset type.
    // $form['filter-sort-container']['format_type'] = [
    // '#type' => 'select',
    // '#title' => 'File format',
    // '#options' => Asset::getFileFormats(),
    // '#default_value' => 0,
    // ];
    // Add textfield for keyword search.
    $form['filter-sort-container']['query'] = [
      '#type' => 'textfield',
      '#title' => 'Search',
      '#size' => 24,
    ];
    // Add submit button to apply sort/filter criteria.
    $form['filter-sort-container']['filter-sort-submit'] = [
      '#type' => 'button',
      '#value' => 'Apply',
      '#name' => 'filter_sort_submit',
    ];
    // Add form reset button.
    $form['filter-sort-container']['filter-sort-reset'] = [
      '#type' => 'button',
      '#value' => 'Reset',
      '#name' => 'filter_sort_reset',
    ];

    return $form;
  }

  /**
   * Get Breadcrumb.
   */
  public function getBreadcrumb(Category $category) {
    // Create a container for the breadcrumb.
    $form['breadcrumb-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['breadcrumb gredidam-browser-breadcrumb-container'],
      ],
    ];
    // Placeholder to keep parts information for breadcrumbs.
    $level = [];
    // Add the home breadcrumb buttons to the form.
    $form['breadcrumb-container'][0] = [
      '#type' => 'button',
      '#value' => "Home",
      '#name' => 'breadcrumb',
      '#category_name' => NULL,
      '#parts' => $level,
      '#prefix' => '<li>',
      '#suffix' => '</li>',
      '#attributes' => [
        'class' => ['gredidam-browser-breadcrumb'],
      ],
    ];

    // Add the breadcrumb buttons to the form.
    foreach ($category->parts as $key => $category_name) {
      $level[] = $category_name;
      // Increment it so doesn't overwrite the home.
      $key++;
      $form['breadcrumb-container'][$key] = [
        '#type' => 'button',
        '#value' => $category_name,
        '#category_name' => $category_name,
        '#name' => 'breadcrumb',
        '#parts' => $level,
        '#prefix' => '<li>',
        '#suffix' => '</li>',
        '#attributes' => [
          'class' => ['gredidam-browser-breadcrumb'],
        ],
      ];
    }

    return $form;
  }

  /**
   * Get categories.
   */
  public function getCategoryFormElements($categories, $modulePath, &$form) {
    foreach ($categories as $category) {
      $form['asset-container']['categories'][$category->name] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['gredidam-browser-category-link'],
          'style' => 'background-image:url("/' . $modulePath . '/images/category.png")',
        ],
      ];
      $form['asset-container']['categories'][$category->name][$category->id] = [
        '#type' => 'button',
        '#value' => $category->name,
        '#name' => 'gredidam_category',
        '#gredidam_category' => $category->jsonSerialize(),
        '#attributes' => [
          'class' => ['gredidam-category-link-button'],
        ],
      ];
      $form['asset-container']['categories'][$category->name]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $category->name,
      ];
    }

    return $form;
  }

  /**
   * Format display of one asset in media browser.
   *
   * @return string
   *   Element HTML markup.
   *
   * @var string $gredidamAsset
   */
  public function layoutMediaEntity(Asset $gredidamAsset, $key) {
    $assetName = $gredidamAsset->name;
    if (!empty($gredidamAsset->attachments)) {
      $thumbnail = '<div class="gredidam-asset-thumb"><img src="' . $gredidamAsset->attachments
        . '" width="150px" height="150px" /></div>';
    }
    else {
      $thumbnail = '<span class="gredidam-browser-empty">No preview available.</span>';
    }
    $element = '<div class="js-form-item form-item js-form-type-checkbox form-type--checkbox
     form-type--boolean js-form-item-assets-' .
      $key . ' form-item--assets-' . $key . '">
    <input data-drupal-selector="edit-assets-' .
      $key . '" type="checkbox" id="edit-assets-' .
      $key . '" name="assets[' . $key . ']" value="' .
      $gredidamAsset->external_id . '" class="form-checkbox form-boolean form-boolean--type-checkbox">';

    $element .= '<label for="edit-assets-' . $key . '"><div class="gredidam-asset-checkbox">' .
      $thumbnail . '<div class="gredidam-asset-details"><p class="gredidam-asset-filename">' .
      $assetName . '</p></div></label></div>';
    $element .= '</div>';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $assets = [];
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $assets = $this->prepareEntities($form, $form_state);
    }
    $this->selectEntities($assets, $form_state);
  }

  /**
   * Prepare entity and create media.
   *
   * @param array $form
   *   Form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    // Get asset id's from form state.
    $asset_ids = array_values($form_state->getUserInput()['assets']);

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')
      ->load($this->configuration['media_type']);

    // Get the source field for this type which stores the asset id.
    $source_field = $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();

    // Query for existing entities.
    $existing_ids = $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->condition('bundle', $media_type->id())
      ->condition('field_external_id', $asset_ids, 'IN')
      ->execute();

    $entities = $this->entityTypeManager->getStorage('media')
      ->loadMultiple($existing_ids);

    if (!empty($entities)) {
      return [end($entities)];
    }
    $expand = ['meta', 'attachments'];
    $assets = $this->grediDamClient->getMultipleAsset($asset_ids, $expand);

    foreach ($assets as $asset) {
      if ($asset == NULL) {
        continue;
      }
      $image_name = $asset->name . '.' . $this->getExtension($asset->mimeType);
      $image_uri = 'public://gredidam/' . $image_name;
      $resource = fopen($image_uri, 'w');
      $stream = Utils::streamFor($resource);
      $this->guzzleClient->request('GET', $asset->attachments, ['save_to' => $stream]);

      $file = File::create([
        'uid' => $this->user->id(),
        'filename' => $image_name,
        'uri' => $image_uri,
        'status' => 1,
      ]);
      $file->save();

      $entity = Media::create([
        'bundle' => $media_type->id(),
        'uid' => $this->user->id(),
        'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
        // @todo Find out if we can use status from Gredi Dam.
        'status' => 1,
        'name' => $asset->name,
        'field_media_image' => [
          'target_id' => $file->id(),
        ],
        $source_field => [
          'asset_id' => $asset->external_id,
        ],
        'created' => strtotime($asset->created),
        'changed' => strtotime($asset->modified),
      ]);

      $entity->save();

      // Reload the entity to make sure we have everything populated properly.
      $entity = $this->entityTypeManager->getStorage('media')
        ->load($entity->id());

      // Add the new entity to the array of returned entities.
      $entities[] = $entity;
    }

    return $entities;
  }

  /**
   * Get file extension.
   *
   * @param string $mime_type
   *   Mime type.
   *
   * @return string
   *   File extension.
   */
  public function getExtension(string $mime_type) {
    $extensions = [
      'image/jpeg' => 'jpg',
      'image/jpg' => 'jpg',
      'image/png' => 'png',
    ];

    // Add as many other Mime Types / File Extensions as you like.
    return $extensions[$mime_type];
  }

}
