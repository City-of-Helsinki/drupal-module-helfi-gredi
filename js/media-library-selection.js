(function ($, Drupal, once) {
  Drupal.behaviors.grediMediaLibrarySelection = {
    attach: function (context, settings) {
      once('grediFolderSelection', 'input.gredi-folder-id-input-selection', context).forEach(function (element) {
        let wrapper = element.closest('.js-click-to-select.media-library-item');
        if (wrapper) {
          wrapper.addEventListener('click', function(event) {
            let input = event.currentTarget.querySelector('input.gredi-folder-id-input-selection');
            let mediaLibrary = event.currentTarget.closest('.media-library-view');
            let folderId = input.value;
            // @todo disable insert selected button when folder is clicked.
            let formInput = mediaLibrary.querySelector('select[name="folder_id"]');
            formInput.value = folderId;
            let formSubmit = mediaLibrary.querySelector('.views-exposed-form input.form-submit');
            formSubmit.click();
          });
        }
        // Apply the myCustomBehaviour effect to the elements only once.
      });
    }
  };
})(jQuery, Drupal, once);
