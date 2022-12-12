<?php

namespace Drupal\helfi_gredi_image\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\views_remote_data\Plugin\views\PropertyPluginTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * Default implementation of the base sort plugin.
 *
 * @ViewsSort("gredi_modified_sort")
 */
class ModifiedSort extends SortPluginBase {

  use PropertyPluginTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $this->definePropertyPathOption($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $this->propertyPathElement($form, $this->options);
    parent::buildOptionsForm($form, $form_state);
  }

}

