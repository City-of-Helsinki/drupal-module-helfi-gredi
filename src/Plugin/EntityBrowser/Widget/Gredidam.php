<?php

namespace Drupal\helfi_gredi_image\Plugin\EntityBrowser\Widget;

use cweagans\webdam\Entity\Folder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\WidgetBase;
use Drupal\helfi_gredi_image\Entity\Asset;
use Drupal\helfi_gredi_image\Entity\Category;
use Drupal\helfi_gredi_image\GredidamInterface;
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
use Drupal\Core\Link;

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
   * @var \Drupal\helfi_gredi_image\GredidamInterface
   */
  protected $gredidam;

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
   * Gredidam constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, WidgetValidationManager $validation_manager, GredidamInterface $gredidam, AccountInterface $account, LanguageManagerInterface $languageManager, ModuleHandlerInterface $moduleHandler, MediaSourceManager $sourceManager, UserDataInterface $userData, RequestStack $requestStack, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->gredidam = $gredidam;
    $this->user = $account;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $moduleHandler;
    $this->sourceManager = $sourceManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->userData = $userData;
    $this->requestStack = $requestStack;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'), $container->get('entity_type.manager'), $container->get('entity_field.manager'), $container->get('plugin.manager.entity_browser.widget_validation'), $container->get('helfi_gredi_image.gredidam_user_creds'), $container->get('current_user'), $container->get('language_manager'), $container->get('module_handler'), $container->get('plugin.manager.media.source'), $container->get('user.data'), $container->get('request_stack'), $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add more settings for configuring this widget.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $media_type_options = [];
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadByProperties(['source' => 'image']);
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
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $media_type_storage = $this->entityTypeManager->getStorage('media_type');
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    if (!$this->configuration['media_type'] || !($media_type = $media_type_storage->load($this->configuration['media_type']))) {
      return ['#markup' => $this->t('The media type is not configured correctly.')];
    }
    elseif ($media_type->id() != 'gredi_image') {

      return ['#markup' => $this->t('The configured media type is not using the gredi_image plugin.')];
    }
    // If this is not the current entity browser widget being rendered.
    elseif ($this->uuid() != $form_state->getStorage()['entity_browser_current_widget']) {
      // Return an empty array.
      return [];
    }

    dump($this->gredidam->getCustomerContent(6));

    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    // Attach the modal library.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    // This form is submitted and rebuilt when a category is clicked.
    // The triggering element identifies which category button was clicked.
    $trigger_elem = $form_state->getTriggeringElement();

    // Initialize current_category.
    $current_category = new Category();
    // Default current category name to NULL which will act as root category.
    $current_category->name = NULL;
    $current_category->parts = [];
    // Default current page to first page.
    $page = 0;
    // Number of assets to show per page.
    $num_per_page = 5;
    // Total number of assets.
    $total_asset = 0;
    // If the form state contains the widget AND the reset button hadn't been
    // clicked then pull values for the current form state.
    if (isset($form_state->getCompleteForm()['widget']) && isset($trigger_elem) && $trigger_elem['#name'] != 'filter_sort_reset') {
      // Assign $widget for convenience.
      $widget = $form_state->getCompleteForm()['widget'];
      if (isset($widget['pager-container']) && is_numeric($widget['pager-container']['#page'])) {
        // Set the page number to the value stored in the form state.
        $page = intval($widget['pager-container']['#page']);
      }
      if (isset($widget['asset-container']) && isset($widget['asset-container']['#gredidam_category'])) {
        // Set current category to the value stored in the form state.
        $current_category->name = $widget['asset-container']['#gredidam_category']['name'];
        $current_category->parts = $widget['asset-container']['#gredidam_category']['parts'];
        $current_category->links = $widget['asset-container']['#gredidam_category']['links'];
        $current_category->categories = $widget['asset-container']['#gredidam_category']['categories'];
      }
      if ($form_state->getValue('assets')) {
        $current_selections = $form_state->getValue('current_selections', []) + array_filter($form_state->getValue('assets', []));
        $form['current_selections'] = [
          '#type' => 'value',
          '#value' => $current_selections,
        ];
      }
    }

    // Use "listing" for category view or "search" for search view.
    $page_type = "listing";

    // If the form has been submitted.
    if (isset($trigger_elem)) {
      // If a category button has been clicked.
      if ($trigger_elem['#name'] === 'gredidam_category') {
        // Update the required information of selected category.
        $current_category->name = $trigger_elem['#gredidam_category']['name'];
        $current_category->parts = $trigger_elem['#gredidam_category']['parts'];
        $current_category->links = $trigger_elem['#gredidam_category']['links'];
        // Reset page to zero if we have navigated to a new category.
        $page = 0;
      }
      // Set the parts value from the breadcrumb button, so selected category
      // can be loaded.
      if ($trigger_elem['#name'] === 'breadcrumb') {
        $current_category->name = $trigger_elem["#category_name"];
        $current_category->parts = $trigger_elem["#parts"];
      }
      // If a pager button has been clicked.
      if ($trigger_elem['#name'] === 'gredidam_pager') {
        $page_type = $trigger_elem['#page_type'];
        $current_category->name = $trigger_elem['#current_category']->name ?? NULL;
        $current_category->parts = $trigger_elem['#current_category']->parts ?? [];
        // Set the current category id to the id of the category, was clicked.
        $page = intval($trigger_elem['gredidam_page']);
      }
      // If the filter/sort submit button has been clicked.
      if ($trigger_elem['#name'] === 'filter_sort_submit') {
        $page_type = "search";
        // Reset page to zero.
        $page = 0;
      }
      // If the reset submit button has been clicked.
      if ($trigger_elem['#name'] === 'filter_sort_reset') {
        // Fetch the user input.
        $user_input = $form_state->getUserInput();
        // Fetch clean values keys (system related, not user input).
        $clean_val_key = $form_state->getCleanValueKeys();
        // Loop through user inputs.
        foreach ($user_input as $key => $item) {
          // Unset only the User Input values.
          if (!in_array($key, $clean_val_key)) {
            unset($user_input[$key]);
          }
        }
        // Reset the user input.
        $form_state->setUserInput($user_input);
        // Set values to user input.
        $form_state->setValues($user_input);
        // Rebuild the form state values.
        $form_state->setRebuild();
        // Get back to first page.
        $page = 0;
      }
    }
    // Offset used for pager.
    $offset = $num_per_page * $page;
    // Sort By field along with sort order.
    $sort_by = ($form_state->getValue('sortdir') == 'desc') ? '-' . $form_state->getValue('sortby') : $form_state->getValue('sortby');
    // Filter By asset type.
    $filter_type = $form_state->getValue('format_type') ? 'ft:' . $form_state->getValue('format_type') : '';
    // Search keyword.
    $keyword = $form_state->getValue('query');
    // Generate search query based on search keyword and search filter.
    $search_query = trim($keyword . ' ' . $filter_type);
    // Parameters for searching, sorting, and filtering.
    $params = [
      'limit' => $num_per_page,
      'offset' => $offset,
      'sort' => $sort_by,
      'query' => $search_query,
      'expand' => 'thumbnails',
    ];
    // Load search results if filter is clicked.
    if ($page_type == "search") {
      $search_results = $this->gredidam->searchAssets($params);
      $items = $search_results['assets'] ?? [];
      // Total number of assets.
      $total_asset = $search_results['total_count'] ?? 0;
    }
    // Load categories data.
    else {
      $category_name = '';
      $categories = $this->gredidam->getCategoryData($current_category);
      // Total number of categories.
      $total_asset = $total_category = count($categories);
      // Update offset value if category contains both sub category and asset.
      if ($total_category <= $offset) {
        $params['offset'] = $offset - $total_category;
      }
      // Update Limit value if sub categories number is less than the number
      // of items per page.
      if ($total_category < $num_per_page) {
        $params['limit'] = $num_per_page - $total_category;
      }
      // Reset limit value after all the categories are already displayed
      // in previous page.
      if ($offset > $total_category) {
        $params['limit'] = $num_per_page;
      }
      if (count($current_category->parts) > 0) {
        $category_name = implode('/', $current_category->parts);
      }
      $category_assets = $this->gredidam->getAssetsByCategory($category_name, $params);
      if ($total_category == 0 || $total_category <= $offset || $total_category < $num_per_page) {
        $items = $category_assets['assets'] ?? [];
      }
      // Total asset conatins both asset and subcategory(if any).
      $total_asset += $category_assets['total_count'] ?? 0;
    }

    // Add the filter and sort options to the form.
    $form += $this->getFilterSort();
    // Add the breadcrumb to the form.
    $form += $this->getBreadcrumb($current_category);
    // Add container for assets (and category buttons)
    $form['asset-container'] = [
      '#type' => 'container',
      // Store the current category id in the form so it can be retrieved
      // from the form state.
      '#gredidam_category_id' => $current_category->id,
      '#attributes' => [
        'class' => ['gredidam-asset-browser'],
      ],
    ];

    // Get module path to create URL for background images.
    $modulePath = $this->moduleHandler->getModule('helfi_gredi_image')->getPath();

    // If no search terms, display Gredi DAM Categories.
    if (!empty($categories) && ($offset < count($categories))) {
      $initial = 0;
      if ($page != 0) {
        $offset = $num_per_page * $page;
        $categories = array_slice($categories, $offset);
      }
      // Add category buttons to form.
      foreach ($categories as $category) {
        if ($initial < $num_per_page) {
          $this->getCategoryFormElements($category, $modulePath, $form);
          $initial++;
        }
      }
    }
    // Assets are rendered as #options for a checkboxes element.
    // Start with an empty array.
    $assets = [];
    // Add to the assets array.
    if (isset($items)) {
      foreach ($items as $category_item) {
        $assets[$category_item->id] = $this->layoutMediaEntity($category_item);
      }
    }
    // Add assets to form.
    // IMPORTANT: Do not add #title or #description properties.
    // This will wrap elements in a fieldset and will cause styling problems.
    // See: \core\lib\Drupal\Core\Render\Element\CompositeFormElementTrait.php.
    $form['asset-container']['assets'] = [
      '#type' => 'checkboxes',
      '#theme_wrappers' => ['checkboxes__gredidam_assets'],
      '#title_display' => 'invisible',
      '#options' => $assets,
      '#attached' => [
        'library' => [
          'helfi_gredi_image/asset_browser',
        ],
      ],
    ];
    // If the number of assets in the current category is greater than
    // the number of assets to show per page.
    if ($total_asset > $num_per_page) {
      // Add the pager to the form.
      $form['actions'] += $this->getPager($total_asset, $page, $num_per_page, $page_type, $current_category);
    }

    return $form;

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
    $form['filter-sort-container']['format_type'] = [
      '#type' => 'select',
      '#title' => 'File format',
      '#options' => Asset::getFileFormats(),
      '#default_value' => 0,
    ];
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
   * {@inheritdoc}
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
   * {@inheritDoc}
   */
  public function getCategoryFormElements($category, $modulePath, &$form) {
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

  /**
   * Format display of one asset in media browser.
   *
   * @return string
   *   Element HTML markup.
   *
   * @var \Drupal\helfi_gredi_image\Entity\Asset $gredidamAsset
   */
  public function layoutMediaEntity(Asset $gredidamAsset) {
    $modulePath = $this->moduleHandler->getModule('helfi_gredi_image')->getPath();

    $assetName = $gredidamAsset->filename;
    if (!empty($gredidamAsset->thumbnails)) {
      $thumbnail = '<div class="gredidam-asset-thumb"><img src="' . $gredidamAsset->thumbnails->{"300px"}->url . '" alt="' . $assetName . '" /></div>';
    }
    else {
      $thumbnail = '<span class="gredidam-browser-empty">No preview available.</span>';
    }
    $element = '<div class="gredidam-asset-checkbox">' . $thumbnail . '<div class="gredidam-asset-details"><a href="/gredidam/asset/' . $gredidamAsset->id . '" class="use-ajax" data-dialog-type="modal"><img src="/' . $modulePath . '/img/ext-link.png" alt="category link" class="gredidam-asset-browser-icon" /></a><p class="gredidam-asset-filename">' . $assetName . '</p></div></div>';
    return $element;
  }


  /**
   * {@inheritdoc}
   *
   * Create a custom pager.
   */
  public function getPager($total_count, $page, $num_per_page, $page_type = "listing", Category $category = NULL) {
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
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    return TRUE;
  }

}
