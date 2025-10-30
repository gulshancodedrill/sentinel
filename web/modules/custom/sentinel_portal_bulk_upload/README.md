# Sentinel Portal Bulk Upload Module

## Overview
This module allows bulk uploading of samples via CSV file to the Sentinel Portal.

## Features
- CSV file upload with validation
- Batch processing for large files
- Header row detection
- Comprehensive validation of sample data
- Error reporting via notices
- Automatic file cleanup after processing

## Installation
The module is already enabled in Drupal 11.

## Usage
1. Navigate to `/portal/bulk-upload`
2. Download the template CSV file or guide
3. Fill in the template with sample data
4. Upload the CSV file
5. Check "First row is header" if your CSV has headers (default)
6. Click "Upload and Process"
7. Monitor progress via the batch operation
8. Check notices for any validation errors

## File Structure
- `src/Form/BulkUploadForm.php` - Main upload form
- `src/BulkUploadBatch.php` - Batch processing logic
- `sentinel_portal_bulk_upload.module` - Validation functions
- `includes/template.csv` - Template CSV file
- `includes/bulk_uploader_guide.pdf` - User guide

## Route
- `/portal/bulk-upload` - Main bulk upload form

## Permissions
- `sentinel portal bulk upload` - Access to bulk upload functionality

## Configuration
The module stores configuration in `sentinel_portal_bulk_upload.settings`:
- `delete_file` - Whether to delete uploaded files after processing (default: true)

## CSV Format
The CSV should contain the following fields:
- pack_reference_number (required)
- company_email (required)
- installer_name (required)
- company_name (required)
- company_tel (required)
- property_number (required)
- postcode (required)
- system_age (required)
- boiler_manufacturer (required)
- date_sent (YYYY-MM-DD format)
- date_installed (YYYY-MM-DD format)
- And other optional fields...

## Validation
Each row is validated for:
- Valid pack reference number format
- Valid email address
- Required fields present
- Date format validation

## Drupal 7 to Drupal 11 Conversion Notes
### Key Changes:
1. **Form API**: Converted from `hook_menu()` and form callbacks to FormBase class
2. **Batch API**: Moved batch processing to static class methods
3. **File Handling**: Updated to use Drupal 11 file system service
4. **Routing**: Created `.routing.yml` file instead of `hook_menu()`
5. **Permissions**: Created `.permissions.yml` file instead of `hook_permission()`
6. **Validation**: Kept validation in `.module` file for compatibility
7. **Dependency Injection**: Added proper service injection in form class
8. **Messenger Service**: Replaced `drupal_set_message()` with messenger service
9. **Database**: Updated database queries to use Drupal 11 database service
10. **Translation**: Updated `format_plural()` to `formatPlural()` service

### Files Converted:
- `sentinel_portal_bulk_upload.info` → `sentinel_portal_bulk_upload.info.yml`
- `sentinel_portal_bulk_upload.module` → Validation functions kept, hooks removed
- Form callback → `src/Form/BulkUploadForm.php`
- Batch functions → `src/BulkUploadBatch.php`
- Added routing, permissions, and config schema files

## Dependencies
- sentinel_portal_entities
- sentinel_portal_module
- file
- user
- system

## Author
Converted from Drupal 7 to Drupal 11



