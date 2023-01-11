<?php

namespace Drupal\Tests\helfi_gredi\Kernel;

use Drupal\helfi_gredi\Plugin\media\Source\GrediAsset;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Tests the media source functionality.
 *
 * @coversDefaultClass \Drupal\helfi_gredi\Plugin\media\Source\GrediAsset
 *
 * @group helfi_gredi
 */
class GrediAssetSourceTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'helfi_gredi'];

  /**
   * @covers ::getMetadata
   */
  public function testGetMetadata() {
    $configuration = [
      'source_field' => 'field_test_gredi_asset',
    ];
    $plugin = GrediAsset::create($this->container, $configuration, 'gredi_asset', []);
    // Test that NULL is returned for a media item with no source value.
    $media = $this->prophesize('\Drupal\media\MediaInterface');
    $field_items = $this->prophesize('\Drupal\Core\Field\FieldItemListInterface');
    $field_items->isEmpty()->willReturn(TRUE);
    $media->get($configuration['source_field'])->willReturn($field_items->reveal());
    $this->assertNull($plugin->getMetadata($media->reveal(), 'type'));
  }

//  /**
//   * Test the media source.
//   */
//  public function testGrediAssetSource() {
    // Create a test asset on the Gredi DAM.
//    $asset_id = '1';
//    $asset = $this->createGrediAsset($asset_id);
//
//    // Create a media entity that references the test asset.
//    $media = Media::create([
//      'bundle' => 'gredi_asset',
//      'name' => $asset['name'],
//      'url' => $asset['apiPreviewLink'],
//      'field_keywords' => $asset['metaById']['custom:meta-field-257_en'],
//      'field_alt_text' => $asset['metaById']['custom:meta-field-257_en'],
//    ]);
//    $media->save();
//
//    // Ensure the media source field value is correctly stored.
//    $this->assertEquals($asset['name'], $media->get('name')->value);
//    $this->assertEquals($asset['apiPreviewLink'], $media->get('url')->value);

    // Ensure the media source thumbnail is correctly generated.
//    $thumbnail_uri = $media->getSource()->getMetadata($media, 'thumbnail_uri');
//    $this->assertFileExists($thumbnail_uri);

    // Ensure the media source has metadata.
//    $metadata = $media->getSource()->getMetadata($media, 'metadata');
//    $this->assertEquals($asset['name'], $metadata['name']);
//    $this->assertEquals($asset['url'], $metadata['url']);

//  }

  /**
   * Generate an asset for test.
   *
   * @param $asset_id
   *    The asset id.
   *
   * @return array
   */
  protected function expectedGrediAsset($asset_id) {
    return [
      'id' => $asset_id,
      'linkFileId' => '14378736',
      'concreteFileId' => '14378736',
      'parentId' => '15933642',
      'linkParentId' => '15933642',
      'concreteParentId' => '15933642',
      'inCart' => FALSE,
      'fileType' => 'nt=>file',
      'concreteFileType' => 'nt=>file',
      'linkFileType' => 'nt=>file',
      'contentStatus' => 'EXISTS',
      'previewStatus' => 'EXISTS',
      'imageMetaStatus' => 'UNAVAILABLE',
      'analyzeStatus' => 'UNAVAILABLE',
      'name' => 'Yrjönkadun iso allas.jpg',
      'path' => '/_customers/helsinki/_material/01_Kuvat ja aineistot/Kulttuuri ja vapaa-aika/Liikunta/Yrjönkadun iso allas.jpg',
      'linkFilePath' => '/_customers/helsinki/_material/01_Kuvat ja aineistot/Kulttuuri ja vapaa-aika/Liikunta/Yrjönkadun iso allas.jpg',
      'concreteFilePath' => '/_customers/helsinki/_material/01_Kuvat ja aineistot/Kulttuuri ja vapaa-aika/Liikunta/Yrjönkadun iso allas.jpg',
      'apiLink' => '/api/v1/files/14378736',
      'apiContentLink' => '/api/v1/files/14378736/contents/original',
      'apiPreviewLink' => '/api/v1/files/14378736/contents/preview',
      'fileSize' => 195072,
      'created' => '2022-07-22T05=>50=>44.000Z',
      'linkCreated' => '2022-07-22T05=>50=>44.000Z',
      'modified' => '2022-08-12T11=>14=>01.000Z',
      'propertiesById' => [
        'nibo:name' => 'Yrjönkadun iso allas.jpg'
      ],
      'concretePropertiesById' => [
        'nibo:name' => 'Yrjönkadun iso allas.jpg'
      ],
      'metaById' => [
        'custom:meta-field-1399_fi' => 'Susanna  Sinervuo',
        'custom:meta-field-1397_fi' => 'Susanna  Sinervuo',
        'custom:meta-field-1396_date' => '2018 6=>51-9-27',
        'custom:meta-field-1395_fi' => 'Yrjönkadun iso allas',
        'custom:meta-field-1398_fi' => '2018 7=>39-9-27',
        'custom:meta-field-1398_date' => '2018 7=>39-9-27',
        'custom:meta-field-1285_fi' => 'Helsingin kaupunki',
        'versioning' => '',
        'custom:meta-field-1514_fi' => '147419.jpg',
        'custom:meta-field-1396_fi' => '2018 6=>51-9-27',
        'custom:meta-field-257_fi' => 'keywordsFI',
        'custom:meta-field-257_en' => 'keywordsEN',
        'custom:meta-field-1410_fi' => 'alt_text_FI',
        'custom:meta-field-1410_en' => 'alt_text_EN',
      ],
      'inheritedPropertiesById' => [],
      'inheritedMetaById' => [],
      'thumbnails' => [],
      'isInheritNode' => FALSE,
      'namesByLang' => [
        'fi' => 'Yrjönkadun iso allas.jpg',
        'en' => 'Yrjönkadun iso allas.jpg',
        'se' => 'Yrjönkadun iso allas.jpg'
      ],
      'concreteNamesByLang' => [
        'fi' => 'Yrjönkadun iso allas.jpg',
        'en' => 'Yrjönkadun iso allas.jpg',
        'se' => 'Yrjönkadun iso allas.jpg'
      ],
      'materialTypes' => [
        '6' => [
          'id' => 6,
          'name' => [
            'fi' => 'Versio 2'
          ]
        ],
        '5' => [
          'id' => 5,
          'name' => [
            'fi' => 'Versio 1'
          ]
        ]
      ],
      'mimeGroup' => 'picture',
      'concreteMimeGroup' => 'picture',
      'allowedShareOut' => FALSE,
      'indicateSynkka' => FALSE,
      'metaLabelById' => [],
      'inProcessing' => FALSE,
      'previewInProcessing' => FALSE,
      'longProcessing' => FALSE,
      'folder' => FALSE,
      'cart' => FALSE,
      'userProduct' => FALSE,
      'linked' => FALSE,
      'masterProduct' => FALSE,
    ];
  }

}
