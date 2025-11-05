(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.sentinelReportsDatePicker = {
    attach: function (context) {
      var $container = $('#failures-search-form__date-range', context);

      if (!$container.length || $container.data('sentinelReportsDatePicker')) {
        return;
      }

      $container.data('sentinelReportsDatePicker', true);

      $container.dateRangePicker({
        separator: ' to ',
        getValue: function () {
          if ($('#edit-date-from').val() && $('#edit-date-to').val()) {
            return $('#edit-date-from').val() + ' to ' + $('#edit-date-to').val();
          }
          return '';
        },
        setValue: function (s, s1, s2) {
          $('#edit-date-from').val(s1);
          $('#edit-date-to').val(s2);
          setTimeout(function () {
            $('#edit-date-from').trigger('blur');
            $('#edit-date-to').trigger('blur');
          }, 0);
        },
        startDate: $('#edit-date-from').attr('data-min-date')
      });
    }
  };
})(jQuery, Drupal, drupalSettings);


