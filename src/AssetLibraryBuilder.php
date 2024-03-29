<?php

declare(strict_types=1);

namespace Drupal\helfi_gredi;

use Drupal\helfi_gredi\Plugin\media\Source\GrediAsset;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\views\ViewEntityInterface;

/**
 * Decorates the media library builder to add our customizations.
 *
 * @phpstan-ignore-next-line
 */
final class AssetLibraryBuilder extends MediaLibraryUiBuilder {

  /**
   * Build the Gredi media library view.
   *
   * @param \Drupal\media_library\MediaLibraryState $state
   *   (Optional) The current state of the media library.
   *
   * @return array
   *   The render array for the media library.
   */
  protected function buildMediaLibraryView(MediaLibraryState $state) : array {
    // @todo remove after https://www.drupal.org/project/drupal/issues/2971209.
    // Currently, there is no way to influence the View ID used for a specific
    // media type.
    $selected_type = $state->getSelectedTypeId();
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($selected_type);
    $source = $media_type->getSource();
    if ($media_type instanceof MediaTypeInterface && !$media_type->getSource() instanceof GrediAsset) {
      return parent::buildMediaLibraryView($state);
    }

    $view_id = 'gredi_asset_library';
    $display_id = 'widget';

    // We have to completely copy the code from the parent in order to render
    // our specific Media Library view.
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    assert($view instanceof ViewEntityInterface);
    $view_executable = $this->viewsExecutableFactory->get($view);
    $display_id = $state->get('views_display_id', $display_id);
    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $view_request = $view_executable->getRequest();
    $view_request->query->add($state->all());
    $view_executable->setRequest($view_request);

    $args = [$state->getSelectedTypeId()];

    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $request = $view_executable->getRequest();
    $request->query->add($state->all());
    $view_executable->setRequest($request);

    try {
      $view_executable->setDisplay($display_id);
      $view_executable->preExecute($args);
      $view_executable->execute($display_id);
    }
    catch (\Exception $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
      return [
        '#markup' => $exception->getMessage(),
      ];
    }

    return $view_executable->buildRenderable($display_id, $args, FALSE);
  }

}
