(function($, Drupal, once)
{
    'use strict';
    
    Drupal.behaviors.sentinelPortalSampleLandlord = {
        attach: function(context, settings) {
            var toggleHiddenForm = function ($btn) {
                $('.sample-landlord-field').slideDown();
                $btn.slideUp();
            };

            // Wait for Drupal.ajax to be available before adding commands
            function addAjaxCommand() {
                if (typeof Drupal !== 'undefined' && Drupal.ajax && Drupal.ajax.prototype && Drupal.ajax.prototype.commands) {
                    // Our function name is prototyped as part of the Drupal.ajax namespace, adding to the commands:
                    Drupal.ajax.prototype.commands.sample_landlord_update = function(ajax, response, status)
                    {
                        // Set a hard limit to stop the timeout going forever.
                        var maxCheckExistLimit = 100;

                        // Periodically check that an element on the form exists that we can use.
                        var checkExist = setInterval(function() {
                            if (maxCheckExistLimit == 0) {
                                // Looks like we weren't able to find the field in time so we
                                // stop the timer.
                                clearInterval(checkExist);
                            }

                            var $field = $('.sample-landlord-field input[type="text"]');
                            if ($field.length) {
                                $('.sample-landlord-field').slideDown();
                                $('.sample-landlord-add-button').slideUp();
                                $field.val(response.data.term_name);

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

            // Handle the "Enter landlord manually" button click
            once('sample-landlord-toggle', '.sample-landlord-add-button', context).forEach(function(element) {
                $(element).on('click', function(e) {
                    e.preventDefault();
                    console.log('Landlord button clicked');
                    toggleHiddenForm($(this));
                    return false;
                });
            });

            // Legacy support for D7 structure
            once('legacy-landlord', '#edit-sample-landlord-add', context).forEach(function(element) {
                $(element).on('click', function(e) {
                    e.preventDefault();
                    $('#sentinel-portal-sample-submission-form .form-item-landlord').slideDown();
                    $(this).slideUp();
                    return false;
                });
            });
        }
    };
}(jQuery, Drupal, once));