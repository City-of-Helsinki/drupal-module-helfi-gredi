<?php

namespace Drupal\helfi_gredi\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_gredi\GrediClient;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Defines a select filter for a custom field.
 *
 * @ViewsFilter("gredi_folder_id")
 */
final class FolderId extends StringFilter
{

  /**
   * The client.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  protected GrediClient $client;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Constructs a MyStringFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, GrediClient $client, ModuleHandler $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
    $this->connection = $connection;
    $this->client = $client;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('helfi_gredi.dam_client'),
      $container->get('module_handler'),
    );
  }

  /**
   * Overrides \Drupal\views\Plugin\views\filter\StringFilter::valueForm().
   *
   * Provides a select element for the filter value.
   */
  public function valueForm(&$form, $form_state) {
    $form['value']['#type'] = 'hidden';

//    $input = $form_state->getUserInput();
//    if (array_key_exists('folder_id', $input)) {
//      if ($input['folder_id']) {
//        $form['value']['#value'] = $input['folder_id'];
//        // @todo figure out a way to retrieve and store parentId
//      }
//    }

  }


  /**
   * Returns the select options for the filter.
   *
   * @return array
   *   An array of options for the select element.
   */
  protected function getSelectOptions() {
    $folderTree = $this->client->getFolderTree();

    return $this->getNestedOptions($folderTree);
  }

  /**
   * @param $folders
   * @param $prefix
   * @return array
   */
  public function getNestedOptions($folders, $prefix = '') {
    $options = [];
    foreach ($folders as $id => $folder) {
      $name = $folder['name'];
      if (!empty($prefix)) {
        $name = $prefix . ' / ' . $name;
      }
      $options[$id] = $name;
      if (!empty($folder['subfolders'])) {
        $suboptions = self::getNestedOptions($folder['subfolders'], $name);
        $options += $suboptions;
      }
    }
    return $options;
  }

}
