(function($, Drupal, once)
{
    'use strict';
    
    Drupal.behaviors.sentinelPortalSampleCompanyAddress = {
        attach: function(context, settings) {
            // Wait for Drupal.ajax to be available before adding commands
            function addAjaxCommand() {
                if (typeof Drupal !== 'undefined' && Drupal.ajax && Drupal.ajax.prototype && Drupal.ajax.prototype.commands) {
                    // Our function name is prototyped as part of the Drupal.ajax namespace, adding to the commands:
                    Drupal.ajax.prototype.commands.company_address_update = function(ajax, response, status)
                    {
                        // Extract the address data from the response.
                        var address_data = response.data;

                        if (address_data.addresstype == 'company') {
                            // Click on the address lookup button.
                            $('#address_company_address_field_address_und_0-addressfield_lookup_cancel').trigger('mousedown');

                            // Set a hard limit to stop the timeout going forever.
                            var maxCheckExistLimit = 100;

                            // Periodically check that an element on the form exists that we can use.
                            var checkExist = setInterval(function () {
                                if (maxCheckExistLimit == 0) {
                                    // Looks like we weren't able to find the field in time so we
                                    // stop the timer.
                                    clearInterval(checkExist);
                                }

                                if ($('#edit-field-company-address-und-form-field-address-und-0-sub-premise').length) {
                                    // Add the data we have to the address fields in the form.
                                    $('#edit-field-company-address-und-form-field-address-und-0-organisation-name').val(address_data.field_address_organisation_name);
                                    $('#edit-field-company-address-und-form-field-address-und-0-sub-premise').val(address_data.field_address_sub_premise);
                                    $('#edit-field-company-address-und-form-field-address-und-0-premise').val(address_data.field_address_premise);
                                    $('#edit-field-company-address-und-form-field-address-und-0-thoroughfare').val(address_data.field_address_thoroughfare);
                                    $('#edit-field-company-address-und-form-field-address-und-0-locality').val(address_data.field_address_locality);
                                    $('#edit-field-company-address-und-form-field-address-und-0-administrative-area').val(address_data.field_address_administrative_area);
                                    $('#edit-field-company-address-und-form-field-address-und-0-postal-code').val(address_data.field_address_postal_code);

                                    // Stop the timer.
                                    clearInterval(checkExist);
                                }
                                else {
                                    // Decrement the hard limit.
                                    maxCheckExistLimit--;
                                }
                            }, 100); // check every 100ms
                        }
                    };
                } else {
                    // If Drupal.ajax is not ready, try again in 100ms
                    setTimeout(addAjaxCommand, 100);
                }
            }

            // Initialize the AJAX command
            addAjaxCommand();
        }
    };
}(jQuery, Drupal, once));