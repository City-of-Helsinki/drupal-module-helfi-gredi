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
            let parentId = input.getAttribute('data-gredi-parent-id');
            let formInputFolderId = mediaLibrary.querySelector('input[name="gredi_folder_id"]');
            let formInputParentIds = mediaLibrary.querySelector('input[name="gredi_parent_ids"]');
            let parentIds = formInputParentIds.value.split('|');
            parentIds.push(parentId);
            formInputParentIds.value = parentIds.join('|');
            formInputFolderId.value = folderId;
            // @todo should we clear the search when navigating the folder??
            let formSubmit = mediaLibrary.querySelector('.views-exposed-form input.form-submit, .view-filters input.form-submit');
            formSubmit.click();
          });
        }
      });

      once('grediBackParentFolderId', '.gredi-parent-folder-back-link', context).forEach(function (element) {
        element.addEventListener('click', function(event) {
          event.preventDefault();
          let mediaLibrary = event.currentTarget.closest('.media-library-view');
          let formWrapper = mediaLibrary.querySelector('.views-exposed-form, .view-filters');
          let formInputFolderId = mediaLibrary.querySelector('input[name="gredi_folder_id"]');
          let formInputParentIds = mediaLibrary.querySelector('input[name="gredi_parent_ids"]');
          let parentIds = formInputParentIds.value.split('|');
          let goParentId = parentIds.pop();
          formInputParentIds.value = parentIds.join('|');
          formInputFolderId.value = goParentId;
          let formSubmit = formWrapper.querySelector('input.form-submit');
          formSubmit.click();
        });
      });

      once('grediResetLink', '.gredi-reset-link', context).forEach(function (element) {
        element.addEventListener('click', function(event) {
          event.preventDefault();
          let mediaLibrary = event.currentTarget.closest('.media-library-view');
          let formWrapper = mediaLibrary.querySelector('.views-exposed-form, .view-filters');
          let formInputFolderId = mediaLibrary.querySelector('input[name="gredi_folder_id"]');
          let formInputParentIds = mediaLibrary.querySelector('input[name="gredi_parent_ids"]');
          formInputParentIds.value = '';
          formInputFolderId.value = '';
          let formSubmit = formWrapper.querySelector('input.form-submit');
          formSubmit.click();
        });
      });


    }
  };
})(jQuery, Drupal, once);
