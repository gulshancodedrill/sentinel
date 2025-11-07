(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.holdStatesExportDownload = {
    attach: function (context) {
      once('hold-states-export-download', document.body).forEach(function () {
        var settings = drupalSettings.hold_states || drupalSettings.holdStates;
        if (!settings || !settings.downloadUrl) {
          return;
        }

        window.setTimeout(function () {
          var downloadUrl = settings.downloadUrl;
          if (!downloadUrl) {
            return;
          }

          var anchor = document.createElement('a');
          anchor.href = downloadUrl;
          anchor.download = '';
          anchor.style.display = 'none';
          document.body.appendChild(anchor);
          anchor.click();
          document.body.removeChild(anchor);

          if (window.history && window.history.replaceState) {
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('download');
            currentUrl.searchParams.delete('name');
            currentUrl.searchParams.delete('filters');
            window.history.replaceState(null, '', currentUrl.toString());
          }
        }, 0);
      });
    }
  };

})(Drupal, drupalSettings, once);


