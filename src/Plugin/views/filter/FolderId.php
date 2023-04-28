<?php

namespace Drupal\helfi_gredi\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Defines a select filter for a custom field.
 *
 * @ViewsFilter("gredi_folder_id")
 */
final class FolderId extends StringFilter {

  /**
   * Overrides \Drupal\views\Plugin\views\filter\StringFilter::valueForm().
   *
   * Provides a select element for the filter value.
   */
  public function valueForm(&$form, $form_state) {
    $form['value']['#type'] = 'hidden';
  }

}
