(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.onHoldSamplesBulkOps = {
    attach: function (context) {
      // Select all checkbox
      once('hold-states-select-all', '#select-all-samples', context).forEach(function (element) {
        $(element).on('click', function () {
          var checked = $(this).prop('checked');
          $('.sample-checkbox').prop('checked', checked);
        });
      });

      // Update select-all when individual checkboxes change
      once('hold-states-sample-checkbox', '.sample-checkbox', context).forEach(function (element) {
        $(element).on('change', function () {
          var total = $('.sample-checkbox').length;
          var checked = $('.sample-checkbox:checked').length;
          $('#select-all-samples').prop('checked', total === checked);
        });
      });
    }
  };

})(jQuery, Drupal, once);










