<?php

/**
 * @file
 * Provide views data for helfi_gredi.
 */

/**
 * Implements hook_views_data().
 */
function helfi_gredi_views_data(): array {

  $data = [];
  $data['gredi_asset']['table']['group'] = t('Gredi DAM Assets');
  $data['gredi_asset']['table']['entity type'] = 'media';
  $data['gredi_asset']['table']['entity revision'] = FALSE;
  $data['gredi_asset']['table']['base'] = [
    'title' => t('Gredi Assets'),
    'query_id' => 'views_remote_data_query',
  ];

  $data['gredi_asset']['media_library_select_form'] = [
    'title' => t('Select asset'),
    'help' => t('Provides a field for selecting Assets in our media library view'),
    'real field' => 'id',
    'field' => [
      'id' => 'gredi_media_library_select_form',
    ],
  ];

  return $data;

}
