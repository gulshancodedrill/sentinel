/**
 * @file
 * Export success page with countdown and automatic download.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.sentinelSampleExportSuccess = {
    attach: function (context, settings) {
      if (typeof drupalSettings.sentinelSampleExport === 'undefined') {
        return;
      }

      var countdown = drupalSettings.sentinelSampleExport.countdown || 3;
      var downloadUrl = drupalSettings.sentinelSampleExport.downloadUrl;
      var countdownElement = $('#countdown', context);
      var downloadLink = $('#export-download-link', context);

      if (countdownElement.length === 0 || !downloadUrl) {
        return;
      }

      // Update countdown display
      var updateCountdown = function() {
        countdownElement.text(countdown);
        if (countdown > 0) {
          countdown--;
          setTimeout(updateCountdown, 1000);
        } else {
          // Trigger download
          window.location.href = downloadUrl;
        }
      };

      // Start countdown
      updateCountdown();
    }
  };

})(jQuery, Drupal, drupalSettings);

