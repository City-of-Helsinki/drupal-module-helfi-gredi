<?php

namespace Drupal\helfi_gredi_image\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Assets remote search filter.
 *
 * @ViewsFilter("asset_search_filter")
 */
class AssetSearchFilter extends StringFilter {

  /**
   * {@inheritDoc}
   */
  public function query() {
    $this->query->addWhere(
      $this->options['group'],
      $this->realField,
      $this->value,
      $this->operator
    );
  }

  /**
   * {@inheritDoc}
   */
  public function operators() {
    return [
      'contains' => [
        'title' => $this->t('Contains'),
        'short' => $this->t('contains'),
        'method' => 'opContains',
        'values' => 1,
      ],
    ];
  }

}
