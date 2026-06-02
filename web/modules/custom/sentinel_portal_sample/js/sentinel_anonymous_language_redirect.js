(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.sentinelAnonymousLanguageRedirect = {
    attach: function attach() {
      var redirects = (((drupalSettings || {}).sentinelPortalSample || {}).languageRedirects) || {};

      once('sentinel-anon-language-redirect', 'select[data-language-redirect-key]').forEach(function (el) {
        var key = el.getAttribute('data-language-redirect-key');
        if (!key || !redirects[key]) {
          return;
        }

        el.addEventListener('change', function () {
          var next = redirects[key][el.value];
          if (next) {
            window.location.href = next;
          }
        });
      });
    }
  };
})(Drupal, once, drupalSettings);

