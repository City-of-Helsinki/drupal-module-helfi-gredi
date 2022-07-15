<?php

namespace Drupal\helfi_gredi_image\Service;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

class CreateTestSetUp
{
  public function setMedia($asset) {

    $asset = json_decode($asset);
    $image_name = $asset->name . '.jpg';
    $image_uri = 'public://gredidam/' . $image_name;

    $file = File::create([
      'uid' => 1,
      'filename' => $image_name,
      'uri' => $image_uri,
      'status' => 1,
    ]);
    $file->save();

    $entity = Media::create([
      'bundle' => 'gredi_dam_assets',
      'uid' => 1,
      'langcode' => 'en',
      // @todo Find out if we can use status from Gredi Dam.
      'status' => 1,
      'name' => $asset->name,
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
      'field_external_id' => [
        'asset_id' => $asset->external_id,
      ],
      'created' => strtotime($asset->created),
      'changed' => strtotime($asset->modified),
    ]);
    $entity->save();

    return $entity;
  }
}
