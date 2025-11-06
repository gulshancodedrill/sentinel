(function($, Drupal, once)
{
    'use strict';
    
    Drupal.behaviors.sentinelPortalSampleLandlord = {
        attach: function(context, settings) {
            var $manualContainer = $('.sample-landlord-field');
            var $manualInput = $('#edit-landlord').length ? $('#edit-landlord') : $('.sample-landlord-field input[type="text"]');

            if ($manualContainer.length) {
                if ($manualInput.length && $manualInput.val()) {
                    $manualContainer.show();
                }
                else {
                    $manualContainer.hide();
                }
            }

            var revealLandlordField = function(value) {
                var $container = $('.sample-landlord-field');
                if ($container.length) {
                    $container.stop(true, true).slideDown();
                }

                if (value !== undefined) {
                    var $field = $('#edit-landlord');
                    if (!$field.length) {
                        $field = $('.sample-landlord-field input[type="text"]');
                    }
                    if ($field.length) {
                        $field.val(value);
                    }
                }
            };

            var toggleHiddenForm = function ($btn) {
                revealLandlordField();
                $btn.stop(true, true).slideUp();
            };

            // Wait for Drupal.ajax to be available before adding commands
            function addAjaxCommand() {
                if (typeof Drupal !== 'undefined' && Drupal.ajax && Drupal.ajax.prototype && Drupal.ajax.prototype.commands) {
                    Drupal.ajax.prototype.commands.sample_landlord_update = function(ajax, response, status)
                    {
                        if (!response.data) {
                            return;
                        }

                        var maxCheckExistLimit = 100;
                        var checkExist = setInterval(function() {
                            if (maxCheckExistLimit === 0) {
                                clearInterval(checkExist);
                                return;
                            }

                            var selector = response.data.landlord_field_selector || '#edit-landlord';
                            var $field = $(selector);
                            if ($field.length) {
                                var value = response.data.raw || response.data.term_name || (response.element ? response.element.value : '') || $('#edit-landlord-selection').val() || $('#edit-system-details-landlord-selection').val();
                                revealLandlordField(value);
                                $('.sample-landlord-add-button').stop(true, true).slideUp();

                                clearInterval(checkExist);
                            }
                            else {
                                maxCheckExistLimit--;
                            }
                        }, 100);
                    };
                } else {
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

            // Immediately populate landlord field when a suggestion is chosen.
            once('sample-landlord-autocomplete', '#edit-system-details-landlord-selection, #edit-landlord-selection', context).forEach(function(element) {
                var $input = $(element);

                $input.on('autocompleteselect', function() {
                    // Trigger change so the AJAX request mirrors Drupal 7 behaviour.
                    setTimeout(function($el) {
                        $el.trigger('change');
                    }, 0, $input);
                });

                $input.on('change', function() {
                    var value = $input.val();
                    if (!value) {
                        return;
                    }

                    revealLandlordField(value);
                    $('.sample-landlord-add-button').stop(true, true).slideUp();
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