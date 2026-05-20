/**
 * @file
 * Triggers Drupal AJAX after property address autocomplete selection.
 */
(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.sentinelAnonymousAddressAutocomplete = {
    attach(context) {
      const selector =
        '#anonymous-sample-property-details-form input[name="property_address_search"]';
      once('anon-property-address-ajax', selector, context).forEach((element) => {
        const $input = $(element);
        $input.on('autocompleteselect', () => {
          window.setTimeout(() => {
            $input.trigger('change');
          }, 0);
        });
      });
    },
  };
})(jQuery, Drupal, once);
