(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.pagerBegavior = {
    attach: function (context, settings) {
      once('pagerBegavior', 'html', context).forEach( function (element) {

console.log(drupalSettings);
          var container = $('#pagination-demo2');
          container.pagination({
            dataSource: drupalSettings.helfi_gredi_image.dataAssets,
            pageSize: drupalSettings.helfi_gredi_image.numPerPage,
            showPageNumbers: true,
            showPrevious: true,
            showNext: true,
            showNavigator: true,
            showGoInput: true,
            showGoButton: true,
            showFirstOnEllipsisShow: true,
            showLastOnEllipsisShow: true,
            callback: function(data, pagination) {
              // template method of yourself
              var dataHtml = '';
              data.forEach(function (item) {
                dataHtml += item;
              })
              container.prev().html(dataHtml);
            }
          })

      })
    }
  }
} (jQuery, Drupal, drupalSettings, once));
