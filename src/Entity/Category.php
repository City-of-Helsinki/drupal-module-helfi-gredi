<?php

namespace Drupal\helfi_gredi_image\Entity;

/**
 * Class Category.
 *
 * Describes Gredi DAM's Category data type.
 */
class Category implements EntityInterface, \JsonSerializable {

  /**
   * ID of the Category.
   *
   * @var string
   */
  public $id;

  /**
   * The Parent ID of the asset.
   *
   * @var string
   */
  public $parentId;

  /**
   * Name of the Category.
   *
   * @var string
   */
  public $name;

  /**
   * Description of the category.
   *
   * @var string
   */
  public $description;

  /**
   * Created time.
   *
   * @var string
   */
  public $created;

  /**
   * Updated time.
   *
   * @var string
   */
  public $modified;

  /**
   * An array of sub categories.
   *
   * @var Category[]
   */
  public $categories;

  /**
   * Parts information, useful in rendering breadcrumbs.
   *
   * @var array
   */
  public $parts = [];

  /**
   * Root folder id.
   *
   * @var string
   */
  public $rootFolder;

  /**
   * {@inheritdoc}
   */
  public static function fromJson($json) {
    if (is_string($json)) {
      $json = json_decode($json);
    }

    $subCategories = [];
    if (isset($json->total_count) && $json->total_count > 0) {
      foreach ($json as $subcategory_data) {
        if ($subcategory_data['folder']) {
          $subCategories[] = Category::fromJson($subcategory_data);
        }
      }

      return $subCategories;
    }
    elseif (isset($json->total_count) && $json->total_count === 0) {
      return $subCategories;
    }
    $properties = [
      'id',
      'parentId',
      'name',
      'description',
      'created',
      'modified',
    ];

    $category = new static();
    foreach ($properties as $property) {
      if (isset($json[$property])) {
        $category->{$property} = $json[$property];
      }
    }
    $category->rootFolder = \Drupal::service('helfi_gredi_image.dam_client')->getFolderRootId();

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
      'description' => 'category',
      'parts' => $this->parts,
      'created' => $this->created,
      'modified' => $this->modified,
    ];

    if (!empty($this->categories)) {
      $properties['categories'] = $this->categories;
    }

    return $properties;
  }

}
