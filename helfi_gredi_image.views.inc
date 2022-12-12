<?php

/**
 * @file
 * Provide views data for helfi_gredi_image.
 */

//use Drupal\helfi_gredi_image\BundleFieldViewsData;

/**
 * Implements hook_views_data().
 */
function helfi_gredi_image_views_data(): array {

  $data = [];
  $data['gredidam_assets']['table']['group'] = t('Gredi DAM Assets');
  $data['gredidam_assets']['table']['entity type'] = 'media';
  $data['gredidam_assets']['table']['entity revision'] = FALSE;
  $data['gredidam_assets']['table']['base'] = [
    'title' => t('Gredi DAM Assets'),
    'query_id' => 'views_remote_data_query',
  ];

  $data['gredidam_assets']['media_library_select_form'] = [
    'title' => t('Select asset'),
    'help' => t('Provides a field for selecting Assets in our media library view'),
    'real field' => 'id',
    'field' => [
      'id' => 'gredidam_media_library_select_form',
    ],
  ];

  $data['gredidam_assets']['search'] = [
    'title' => t('Search'),
    'help' => t('Search text'),
    'filter' => [
      'field' => 'search',
      'id' => 'asset_search_filter',
    ],
  ];

  $data['gredidam_assets']['modified'] = [
    'title' => t('Modified'),
    'help' => t('Sort by last modified.'),
    'sort' => [
      'id' => 'gredi_modified_sort',
    ],
  ];

  return $data;

}
