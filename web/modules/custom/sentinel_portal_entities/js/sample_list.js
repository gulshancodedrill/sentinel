/**
 * @file
 * JavaScript for Sentinel Sample list builder select all functionality.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Attach select all checkbox behavior.
   */
  Drupal.behaviors.sentinelSampleListSelectAll = {
    attach: function (context, settings) {
      // Use once library for Drupal 11
      once('select-all', '.select-all', context).forEach(function (selectAll) {
        var entitySelects = context.querySelectorAll('.entity-select');

        // Handle select all checkbox change
        selectAll.addEventListener('change', function () {
          var checked = this.checked;
          entitySelects.forEach(function (checkbox) {
            checkbox.checked = checked;
          });
        });

        // Update select all checkbox when individual checkboxes change
        once('entity-select', '.entity-select', context).forEach(function (entitySelect) {
          entitySelect.addEventListener('change', function () {
            var total = context.querySelectorAll('.entity-select').length;
            var checked = context.querySelectorAll('.entity-select:checked').length;
            selectAll.checked = (total === checked && total > 0);
            selectAll.indeterminate = (checked > 0 && checked < total);
          });
        });

        // Initialize select all state
        var total = context.querySelectorAll('.entity-select').length;
        var checked = context.querySelectorAll('.entity-select:checked').length;
        if (total > 0) {
          selectAll.checked = (total === checked);
          selectAll.indeterminate = (checked > 0 && checked < total);
        }
      });
    }
  };

})(Drupal, once);

