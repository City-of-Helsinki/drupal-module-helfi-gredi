<?php

namespace Drupal\helfi_gredi_image\Entity;

/**
 * Class Category.
 *
 * Describes Gredi DAM's Category data type.
 */
class Category implements EntityInterface, \JsonSerializable {

  /**
   * ID of the category.
   *
   * @var string
   */
  public $id;

  /**
   * The Parent ID of the category.
   *
   * @var string
   */
  public $parentId;

  /**
   * Name of the category.
   *
   * @var string
   */
  public $name;

  /**
   * {@inheritdoc}
   */
  public static function fromJson($json) {
    if (is_string($json)) {
      $json = json_decode($json);
    }

    $properties = [
      'id',
      'parentId',
      'name',
    ];

    $category = new static();
    foreach ($properties as $property) {
      if (isset($json[$property])) {
        $category->{$property} = $json[$property];
      }
    }

    return $category;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize() {
    $properties = [
      'id' => $this->id,
      'parentId' => $this->parentId,
      'name' => $this->name,
    ];

    return $properties;
  }

}
