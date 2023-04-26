<?php

namespace Drupal\helfi_gredi\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_gredi\GrediClient;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a filter for a custom field.
 *
 * @ViewsFilter("gredi_button_filter_back")
 */
class MediaFilterBackButtonForm extends FilterPluginBase
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

    $form['value']['#type'] = 'button';
    $form['value']['#value'] = 'Back';

    $input = $form_state->getUserInput();
    if (isset($input['op']) && $input['op'] === 'Back') {
      if (isset($input['folder_id'])) {
        // @todo Do request for parent id folder.
        $parentId = $input['folder_id']['#attributes']['parent_id'];
      }
      else {
        $form['value']['#disabled'] = TRUE;
      }
    }
  }


}
