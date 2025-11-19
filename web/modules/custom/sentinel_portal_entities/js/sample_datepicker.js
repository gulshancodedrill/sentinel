(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.sentinelSampleDatepicker = {
    attach: function (context, settings) {
      // Wait for jQuery UI datepicker to be available
      function initDatepickers() {
        if (typeof $.fn.datepicker === 'undefined') {
          // Retry after a short delay if jQuery UI is still loading
          setTimeout(initDatepickers, 100);
          return;
        }

        once('datepicker-init', '.datepicker-popup', context).forEach(function (element) {
          var $element = $(element);
          
          // Initialize datepicker
          $element.datepicker({
            dateFormat: 'mm/dd/yy',
            changeMonth: true,
            changeYear: true,
            yearRange: '1900:2100',
            showButtonPanel: true
          });
        });
      }
      
      initDatepickers();
    }
  };

})(jQuery, Drupal, once);

