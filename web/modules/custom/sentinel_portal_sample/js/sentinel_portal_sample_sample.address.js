(function($, Drupal, once)
{
    'use strict';
    
    Drupal.behaviors.sentinelPortalSampleAddress = {
        attach: function(context, settings) {
            // Wait for Drupal.ajax to be available before adding commands
            function addAjaxCommand() {
                if (typeof Drupal !== 'undefined' && Drupal.ajax && Drupal.ajax.prototype && Drupal.ajax.prototype.commands) {
                    // Our function name is prototyped as part of the Drupal.ajax namespace, adding to the commands:
                    Drupal.ajax.prototype.commands.sample_address_update = function(ajax, response, status)
                    {
                        // Extract the address data from the response.
                        var address_data = response.data;

                        // Set a hard limit to stop the timeout going forever.
                        var maxCheckExistLimit = 100;

                        // Periodically check that an element on the form exists that we can use.
                        var checkExist = setInterval(function() {
                            if (maxCheckExistLimit == 0) {
                                // Looks like we weren't able to find the field in time so we
                                // stop the timer.
                                clearInterval(checkExist);
                            }

                            if ($('#edit-field-sentinel-sample-address-und-form-field-address-und-0-sub-premise').length) {
                                $('#sentinel-portal-sample-submission-form #edit-field-sentinel-sample-address-und-form-field-address').slideDown();

                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-country').val(address_data.field_address_country);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-thoroughfare').val(address_data.field_address_thoroughfare);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-premise').val(address_data.field_address_premise);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-sub-premise').val(address_data.field_address_sub_premise);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-locality').val(address_data.field_address_locality);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-administrative-area').val(address_data.field_address_administrative_area);
                                $('#edit-field-sentinel-sample-address-und-form-field-address-und-0-postal-code').val(address_data.field_address_postal_code);

                                // Stop the timer.
                                clearInterval(checkExist);
                            }
                            else {
                                // Decrement the hard limit.
                                maxCheckExistLimit--;
                            }
                        }, 100); // check every 100ms
                    };
                } else {
                    // If Drupal.ajax is not ready, try again in 100ms
                    setTimeout(addAjaxCommand, 100);
                }
            }

            // Initialize the AJAX command
            addAjaxCommand();

            // Handle the "Enter address manually" button click
            once('sample-address-toggle', '.sample-address-add-button', context).forEach(function(element) {
                $(element).on('click', function(e) {
                    e.preventDefault();
                    console.log('Address button clicked');
                    var $fields = $('.sample-address-fields');
                    console.log('Found address fields:', $fields.length);
                    $fields.slideDown();
                    $(this).slideUp();
                    return false;
                });
            });

            // Legacy support for D7 field structure
            once('legacy-sample-address', '#edit-field-sentinel-sample-address-und-form-sample-address-add', context).forEach(function(element) {
                $(element).on('click', function(e) {
                    e.preventDefault();
                    $('#sentinel-portal-sample-submission-form #edit-field-sentinel-sample-address-und-form-field-address').slideDown();
                    $(this).slideUp();
                    return false;
                });
            });
        }
    };
}(jQuery, Drupal, once));