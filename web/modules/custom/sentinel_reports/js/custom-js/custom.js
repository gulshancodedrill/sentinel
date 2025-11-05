/**
 * @file
 * Custom JavaScript for Sentinel Reports hierarchical pie chart.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.sentinelReportsChart = {
    attach: function (context, settings) {
      var pass_fails = settings.sentinel_reports ? settings.sentinel_reports.pass_fails : null;

      if (!pass_fails || !pass_fails.length) {
        return;
      }

      var holder = $('#hierarchical-pie-demo', context);
      
      // Check if already initialized and remove old chart.
      if (holder.length && holder.children().length > 0) {
        holder.empty();
        holder.removeData('sentinel-reports-chart');
      }

      if (holder.length && typeof HierarchicalPie !== 'undefined') {
        holder.empty();

        new HierarchicalPie({
          chartId: '#hierarchical-pie-demo',
          data: pass_fails,
          legendContainer: '#pie-chart-legend-1',
          navigation: '#chart-navigator-1',
          hideNavOnRoot: true,
          width: 250,
          height: 250,
          dataSchema: {
            idField: 'id_category',
            valueField: 'value',
            labelField: 'category',
            childrenField: 'categories',
            export_link: 'export_link'
          }
        });

        var num_tests_passed = pass_fails[0].value;
        var num_tests_failed = pass_fails[1].value;
        var total_num_tests = parseInt(num_tests_passed) + parseInt(num_tests_failed);

        var success_perc = Math.round((num_tests_passed / total_num_tests) * 100);

        $('#failures-search-form__success-rate__figure').text(success_perc + '%');
        $('#failures-search-form__total-tests__tests').text(total_num_tests);
        $('#failures-search-form__total-tests__passed').text(num_tests_passed);
        $('#failures-search-form__total-tests__failed').text(num_tests_failed);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);


