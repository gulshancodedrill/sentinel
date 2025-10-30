# Sentinel Samples Migration (D11)

This directory contains the conversion of the Drupal 7 `sentinel_samples_migration` migration classes to Drupal 11.

## Overview

The original D7 migration used PHP classes extending the Migrate API. In D11, migrations are defined using YAML configuration files with source, process, and destination plugins.

## Structure

### Traits
- `src/Traits/AddressMappingTrait.php` - Address field mapping utilities (converted from D7)

### Source Plugins
- `src/Plugin/migrate/source/SentinelSamplesSource.php` - Source plugin for querying legacy database (partially implemented)

### Destination Plugins
- `src/Plugin/migrate/destination/SentinelSampleDestination.php` - Destination plugin for Sentinel Sample entities (converted from `MigrateDestinationSentinelSample`)

### Migration YAML Files
- `migrations/` - Directory for YAML migration configurations (needs to be created)

## Original D7 Classes

The following D7 classes were converted:

1. **SentinelSamplesMigration** - Main sample migration
   - Queries `vaillant_samples` and `standard_samples` tables
   - Unites data from multiple legacy tables
   - Processes lab test results
   - Maps to `sentinel_sample` entities

2. **SentinelSamplesBaseAddressMigration** - Base class for address migrations
   - Abstract class for handling address entity creation
   - Links addresses to sample entities

3. **SentinelSamplesAddressMigration** - Address migration
   - Migrates sample addresses from `addresses` table
   - Creates `address` entity bundle entities
   - Maps to `field_sentinel_sample_address` field

4. **SentinelSamplesCompanyAddressMigration** - Company address migration
   - Migrates company addresses from `company_addresses` table
   - Creates `company_address` entity bundle entities
   - Maps to `field_company_address` field

5. **MigrateDestinationSentinelSample** - Custom destination class
   - Handles importing Sentinel Sample entities
   - Processes test result data
   - Handles entity creation and updates

## Conversion Status

### Completed ✅
- Address mapping trait converted
- Destination plugin structure created
- Source plugin structure started
- Test data processing logic migrated

### In Progress / TODO ⚠️

1. **Source Plugin (`SentinelSamplesSource`)**
   - Complete the UNION query implementation (D11 query builder doesn't support UNION directly)
   - Handle the `standard_samples` query separately or use raw SQL
   - Configure database connection for legacy `sentinel_legacy` database
   - Complete field mappings

2. **Address Source Plugins**
   - Create `SentinelAddressesSource` plugin for address migrations
   - Create `SentinelCompanyAddressesSource` plugin for company address migrations
   - Handle unique address table joins and GROUP_CONCAT operations

3. **Migration YAML Files**
   - Create `sentinelsamplesmigration.yml`
   - Create `sentineladdressesmigration.yml`
   - Create `sentinelcompanyaddressesmigration.yml`
   - Define process pipelines for field mappings

4. **Process Plugins** (if needed)
   - Custom process plugins for test data transformation
   - Address field mapping process plugins

5. **Post-Import Processing**
   - Convert `postImport()` method to migration post-processing
   - Queue cleanup after migration completion

## Database Configuration

The migrations query a legacy database called `sentinel_legacy`. In D11, you need to:

1. Add database connection to `settings.php`:
```php
$databases['sentinel_legacy']['default'] = [
  'database' => 'legacy_database_name',
  'username' => 'username',
  'password' => 'password',
  'prefix' => '',
  'host' => 'localhost',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];
```

2. Update source plugins to use the correct database connection.

## Migration Groups

The original D7 migrations were organized into groups:
- `samplesAddresses` - Sentinel Addresses
- `samplesCompanyAddresses` - Sentinel Company Addresses
- `samples` - Sentinel Samples (commented out in original)

## Testing

After completing the migration YAML files:
1. Review migration configurations: `drush migrate:status`
2. Import migrations: `drush migrate:import sentinelsamplesmigration`
3. Verify data migration
4. Run address migrations after sample migration completes

## Notes

- The original D7 migrations use complex UNION queries that need special handling in D11
- Test result processing is handled in the destination plugin's `import()` method
- Address entity linking happens in the `complete()` method which needs to be converted to a process plugin or post-processing hook
- The `postImport()` cleanup logic should be moved to a migration post-processing hook


