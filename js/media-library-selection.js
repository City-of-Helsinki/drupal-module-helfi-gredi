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
            // @todo disable insert selected button when folder is clicked.
            let formInputFolderId = mediaLibrary.querySelector('input[name="gredi_folder_id"]');
            let formInputParentIds = mediaLibrary.querySelector('input[name="gredi_parent_ids"]');
            let parentIds = formInputParentIds.value.split('|');
            // @todo when searching, we might get results from deeper subfolders,
            //  so if parentId of clicked folder != current folder than when back navigating, we might jump folders from the tree.
            // so should we leave it like this? or reset completly the parent ids and than user should press reset button?
            parentIds.push(parentId);
            formInputParentIds.value = parentIds.join('|');
            formInputFolderId.value = folderId;
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
    }
  };
})(jQuery, Drupal, once);
