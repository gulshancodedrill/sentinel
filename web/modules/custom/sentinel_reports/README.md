# Sentinel Reports Module

This module provides functionality for generating reports with statistics, charts, and CSV exports.

## Features

1. **Stats Search Form**: Form with date range picker, installer name, and location filters
2. **AJAX Statistics**: Real-time statistics display with D3.js hierarchical pie charts
3. **CSV Export**: Batch processing for exporting sample data to CSV
4. **Statistics Categories**: Three categories of concern analysis:
   - Concerns Relating to Inhibitor
   - Concerns Relating to Clean
   - Concerns Relating to Both Clean and Inhibitor

## Conversion Notes (Drupal 7 to Drupal 11)

This module was converted from a Drupal 7 sub-module within `sentinel_stats`.

### Module Structure

- `sentinel_reports.info.yml` - Module definition
- `sentinel_reports.module` - Main module file with theme hooks
- `sentinel_reports.routing.yml` - Route definitions
- `sentinel_reports.libraries.yml` - JavaScript and CSS libraries
- `sentinel_reports.services.inc` - Helper functions
- `src/Form/FailuresSearchForm.php` - Search form with AJAX
- `src/Controller/ReportsController.php` - CSV export and download controller
- `templates/` - Twig templates for statistics display
- `js/` - JavaScript libraries (daterangepicker, D3 hierarchical pie chart, custom scripts)

### Routes

- `/portal/explore-your-stats` - Main statistics search and display page
- `/portal/export-your-stats/{key_name}` - Batch CSV export initiation
- `/portal/download-stats/{key_name}` - CSV file download

### JavaScript

The module includes:
- jQuery Date Range Picker for date selection
- D3.js hierarchical pie chart library for visualizations
- Custom scripts for chart initialization and interaction

### Statistics Classes

The module uses PHP classes for calculating statistics (not yet converted - requires database schema knowledge):
- `CategoryStatsBase` - Base class for statistics calculations
- `ConcernsRelatingToInhibitor` - Statistics for inhibitor concerns
- `ConcernsRelatingToClean` - Statistics for cleaning concerns
- `ConcernsRelatingToBothCleanAndInhibitor` - Statistics for combined concerns
- `CategoryStatsFactory` - Factory for creating category objects

These classes need to be converted to D11 namespace and updated to use D11 database API.

### Dependencies

- `sentinel_stats` - Required for stat entities
- `sentinel_portal_module` - Required for permissions
- `uuid` - Required for cache key generation

### Permissions

Requires `sentinel portal` permission for accessing reports.

### CSV Export

The module supports batch CSV export of sample data with the following fields:
- id, pack_reference_number, pack_type, dates, addresses, system info, boiler info, company info, installer info

### Statistics Calculation

Statistics are calculated based on:
- Date range filtering
- Client ID filtering (for users without "view all" permission)
- Location filtering (town/city)
- Installer name filtering

The statistics aggregate pass/fail data and categorize failures into specific concern types for display in hierarchical charts.


