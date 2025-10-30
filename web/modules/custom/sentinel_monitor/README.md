# Sentinel Monitor Module (D11)

This module has been converted from Drupal 7 to Drupal 11. It provides functionality for collecting and managing Sentinel Monitor information.

## Overview

The original D7 module was a Features-based module. This D11 version maintains the same functionality but uses D11 patterns:
- Configuration stored in `config/install/` instead of Features code
- ECK entity type and bundle definitions via hooks
- Form alterations using hook_form_FORM_ID_alter()
- Routing via YAML files instead of hook_menu()

## Converted Components

### Core Files
- `sentinel_monitor.info.yml` - Module definition (converted from `.info`)
- `sentinel_monitor.module` - Main module file with hooks
- `sentinel_monitor.routing.yml` - Route definitions
- `sentinel_monitor.permissions.yml` - Permission definitions
- `sentinel_monitor.forms.inc` - Form alteration and validation functions

### Controllers
- `src/Controller/MonitorController.php` - Controller for monitor add form

### Configuration Files
- `config/install/user.role.monitor.yml` - Monitor user role

### Install File
- `sentinel_monitor.install` - Creates taxonomy vocabulary conditionally (only if it doesn't exist)

## Features Converted

1. **ECK Entity Type**: `sentinel_monitor` entity with `sentinel_monitor` bundle
2. **Routing**: `/portal/sample/monitor` route for adding monitor entities
3. **Form Alterations**: Form validation for serial numbers and field access control
4. **Permissions**: ECK entity permissions for add/edit/delete/view
5. **User Role**: Monitor role definition
6. **Taxonomy**: Last known statuses vocabulary

## Remaining Work

### Field Definitions
The field base definitions and field instances need to be created. In D11, these can be:
1. Exported as configuration and placed in `config/install/`
2. Or created programmatically in an install hook

Fields to create:
- field_monitor_serial_number (text)
- field_monitor_boiler_type (text)
- field_monitor_company_address_1 (text)
- field_monitor_company_address_2 (text)
- field_monitor_company_county (text)
- field_monitor_company_email (email)
- field_monitor_company_name (text)
- field_monitor_company_postcode (text)
- field_monitor_company_telephone (telephone)
- field_monitor_company_town (text)
- field_monitor_county (text)
- field_monitor_date_installed_ (datetime)
- field_monitor_installer_email (email)
- field_monitor_installer_name (text)
- field_monitor_landlord (text)
- field_monitor_last_known_status (taxonomy)
- field_monitor_pilot_unit (boolean)
- field_monitor_postcode (text)
- field_monitor_property_number (text)
- field_monitor_street (text)
- field_monitor_system_age (integer)
- field_monitor_text_log (text_long)
- field_monitor_town_city (text)
- field_monitorboiler_manufacturer (text)
- field_date_of_last_known_status (datetime)

### Views
The `monitor_submissions` view needs to be:
1. Recreated in the D11 UI
2. Or exported as configuration and placed in `config/install/`

### Field Groups
The field groups (Company Details, Job Details, System Details) need to be:
1. Recreated using Field Group module
2. Or exported as configuration

### Feeds Importer
The `last_known_statuses` feeds importer needs to be:
1. Recreated in the D11 Feeds UI
2. Or exported as configuration

## Installation

1. Enable the module: `drush en sentinel_monitor -y`
2. The ECK entity type and bundle will be registered
3. The `last_known_statuses` taxonomy vocabulary will be created only if it doesn't already exist (preserves existing terms from migration)
4. Create field definitions (see above)
5. Create views and field groups as needed

## Notes on Existing Taxonomy Vocabularies

Since vocabularies may already exist from migration with terms, the module:
- Uses `hook_install()` to conditionally create the `last_known_statuses` vocabulary only if it doesn't exist
- Does NOT overwrite existing vocabularies or terms
- Preserves all existing taxonomy data

## Dependencies

- datetime (core)
- eck
- feeds
- field_group
- taxonomy (core)
- text (core)
- views (core)
- sentinel_portal_module

## Notes

- The form alteration hook name may need adjustment based on ECK's form ID generation in D11
- Serial number validation matches the original D7 functionality
- Field access control is stated in the form alter based on user roles
