/**
 * @file
 * Resize the asset browser frame.
 */

(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.acquiadamAssetBrowser = {
    attach: function (context, settings) {
      once('acquiadamAssetBrowser', 'html', context).forEach( function (element) {
        $(".acquiadam-asset-browser").height($(window).height() - $(".filter-sort-container").height());
        $(window).on('resize', function () {
          $(".acquiadam-asset-browser").height($(window).height() - $(".filter-sort-container").height());
        });
      });
    }
  };

} (jQuery, Drupal, drupalSettings, once));
