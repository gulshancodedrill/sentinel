(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.onHoldSamplesBulkOps = {
    attach: function (context) {
      // Select all checkbox
      $('#select-all-samples', context).once('select-all').on('click', function () {
        var checked = $(this).prop('checked');
        $('.sample-checkbox').prop('checked', checked);
      });

      // Update select-all when individual checkboxes change
      $('.sample-checkbox', context).once('sample-checkbox').on('change', function () {
        var total = $('.sample-checkbox').length;
        var checked = $('.sample-checkbox:checked').length;
        $('#select-all-samples').prop('checked', total === checked);
      });
    }
  };

})(jQuery, Drupal);




