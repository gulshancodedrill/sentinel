# Sentinel Migrations Module (D11)

This module provides migration functionality for Sentinel sample data from legacy Drupal 7 databases.

## Overview

This module has been converted from Drupal 7 to Drupal 11. The conversion includes:

1. **Queue Processing**: Two queue workers that process system location extraction and address entity creation
2. **Queue Initiation Form**: Admin form to initiate the migration queue processing
3. **Database Tables**: Creates tables for addresses, company addresses, and unique address tracking
4. **Taxonomy Terms**: Creates address note taxonomy terms on install

## Drupal 7 to Drupal 11 Conversion Notes

### Migration Classes

The original D7 module used PHP classes extending the D7 Migrate API (`Migrate`, `MigrateSourceSQL`, `MigrateDestinationEntityAPI`). In Drupal 11:

- Migrations are defined in YAML files in the `migrations/` directory
- Source plugins, process plugins, and destination plugins replace the class-based approach
- The migration YAML configurations need to be created separately based on the original D7 migration classes

The original migration classes were:
- `SentinelSamplesMigration` - Main sample migration (currently commented out)
- `SentinelSamplesAddressMigration` - Address migration for sample addresses
- `SentinelSamplesCompanyAddressMigration` - Address migration for company addresses

### Queue System

Converted from D7's `hook_cron_queue_info()` to D11 QueueWorker plugins:
- `SystemLocationQueueWorker` - Processes system location extraction
- `SaveBlankAddressesQueueWorker` - Processes address entity creation

### Forms

Converted the D7 form callback to a D11 `ConfirmFormBase` class:
- `sentinel_migrations\Form\QueueInitiateForm`

### Database API

All database queries have been updated from D7's `db_*()` functions to D11's database service and query builders.

### Entity API

Entity loading and saving updated from D7's `entity_load()` to D11's entity type manager service.

## Installation

1. Enable the module: `drush en sentinel_migrations -y`
2. The module will automatically create necessary database tables and taxonomy terms on install

## Usage

1. Navigate to `/portal/admin/migrate` to initiate queue processing
2. Queue items will be processed during cron runs or manually via Drush queue-run commands

## Dependencies

- migrate (core)
- sentinel_portal_module
- sentinel_addresses

## Future Work

The YAML migration configurations need to be created based on the original D7 migration classes. These would be placed in the `migrations/` directory and reference:
- Custom source plugins for legacy database queries
- Process plugins for field mapping
- Destination plugins for Sentinel entities

See the original D7 migration classes in `testd7/sentinel_migrations/sentinel_samples_migration/` for reference.
