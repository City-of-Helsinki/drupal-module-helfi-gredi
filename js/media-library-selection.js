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
            let formInput = mediaLibrary.querySelector('input[name="gredi_folder_id_hidden"]');
            formInput.value = folderId;
            console.log(formInput.value);
            let formSubmit = mediaLibrary.querySelector('.views-exposed-form input.form-submit');
            console.log(formSubmit);
            formSubmit.click();
          });
        }
        // Apply the myCustomBehaviour effect to the elements only once.
      });
    }
  };
})(jQuery, Drupal, once);
