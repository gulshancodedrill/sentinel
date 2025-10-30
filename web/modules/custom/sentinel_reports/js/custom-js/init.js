(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.sentinelReportsDatePicker = {
    attach: function (context, settings) {
      $('#failures-search-form__date-range', context).once('sentinel-reports-datepicker').each(function () {
        $(this).dateRangePicker({
          separator: ' to ',
          getValue: function() {
            if ($('#edit-date-from').val() && $('#edit-date-to').val()) {
              return $('#edit-date-from').val() + ' to ' + $('#edit-date-to').val();
            } else {
              return '';
            }
          },
          setValue: function(s, s1, s2) {
            $('#edit-date-from').val(s1);
            $('#edit-date-to').val(s2);
            $('#edit-date-from').blur();
          },
          startDate: $('#edit-date-from').attr('data-min-date')
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);


