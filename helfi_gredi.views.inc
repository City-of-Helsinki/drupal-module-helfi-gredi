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
  $data['gredi_asset']['gredi_folder_id'] = [
    'title' => t('Folder id filter'),
    'help' => t('Used for folder id filter'),
    'real field' => 'gredi_folder_id',
    'filter' => [
      'id' => 'gredi_folder_id',
    ],
  ];

  $data['gredi_asset']['gredi_parent_ids'] = [
    'title' => t('Parent ids navigation history'),
    'help' => t('Used for parent folder id navigation'),
    'real field' => 'gredi_parent_ids',
    'filter' => [
      'id' => 'gredi_folder_id',
    ],
  ];

  $data['media_field_data']['gredi_removed'] = [
    'title' => t('Asset exists in Gredi'),
    'help' => t('Whether or not the asset still exists in the Gredi API.'),
    'field' => [
      'id' => 'boolean',
    ],
  ];

  return $data;

}
