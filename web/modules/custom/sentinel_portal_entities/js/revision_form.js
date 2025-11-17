(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.sentinelRevisionForm = {
    attach: function (context, settings) {
      // Handle the button in the table header.
      once('revision-form-button', 'button#edit-submit', context).forEach(function (button) {
        button.addEventListener('click', function (e) {
          // Find the form.
          var form = button.closest('form');
          if (form) {
            // Find the hidden submit button and click it to ensure proper form processing.
            var hiddenSubmit = form.querySelector('input[type="submit"][style*="display: none"], input[type="submit"][style*="display:none"]');
            if (hiddenSubmit) {
              e.preventDefault();
              e.stopPropagation();
              // Set the op value to match.
              hiddenSubmit.value = button.value || 'Compare';
              hiddenSubmit.click();
            }
            // If no hidden button, let the default submit happen.
          }
        });
      });
    }
  };

})(Drupal, once);

