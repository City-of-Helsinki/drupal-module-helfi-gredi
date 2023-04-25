<?php

namespace Drupal\helfi_gredi\Plugin\views\filter;

use Drupal\helfi_gredi\GrediClient;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Defines a select filter for a custom field.
 *
 * @ViewsFilter("gredi_folder_filter_select")
 */
final class MediaFilterSelectForm extends StringFilter
{

  /**
   * The client.
   *
   * @var \Drupal\helfi_gredi\GrediClient
   */
  public GrediClient $client;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, GrediClient $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
    $this->connection = $connection;
    $this->client = $client;
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
    );
  }

  /**
   * Overrides \Drupal\views\Plugin\views\filter\StringFilter::valueForm().
   *
   * Provides a select element for the filter value.
   */
  public function valueForm(&$form, $form_state) {

    $field_type = 'select';
    if ($module_handler = \Drupal::service('module_handler')->getModule('select2')) {
      $parts = explode('.', $module_handler->getFilename());
      $field_type = $parts[0];
    }

    $form['value']['#type'] = $field_type;
    $form['value']['#options'] = $this->getSelectOptions();
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
    $options = array();
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
