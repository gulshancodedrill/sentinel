# Sentinel Addresses Module Conversion

This is a conversion guide for the `sentinel_addresses` module from Drupal 7 to Drupal 11.

## Module Overview

The `sentinel_addresses` module provides address management functionality using ECK (Entity Construction Kit) entities.

## Key Components

### ECK Entity Type
- **Entity Type**: `address`
- **Bundles**:
  - `address` - Sample addresses
  - `company_address` - Company addresses

### Fields Created
- `field_address` - Address field (addressfield)
- `field_address_note` - Field collection for address notes
- Field on sentinel_sample entities:
  - `field_company_address` - Reference to company address
  - `field_sentinel_sample_address` - Reference to sample address

### Features
- Address autocomplete for samples
- Address note management
- Cron processing for address formatting
- Views for displaying addresses and notes
- Field group configurations

## Conversion Complexity

This module is quite complex and involves:
- ECK entity type definitions
- Multiple field configurations
- Field collections
- Custom views
- Cron queue processing
- Complex database queries
- ECK entity integration

## Recommendation

Due to the complexity of this module and its heavy reliance on ECK with custom entity types and field collections, I recommend:

1. **Manual Configuration**: Set up the ECK entity types manually through the admin interface
2. **Field Export**: Export field configurations after manual setup
3. **Gradual Migration**: Migrate functionality piece by piece
4. **Alternative Approach**: Consider using Drupal's built-in address field instead of custom ECK entities

## Next Steps

To convert this module properly, you would need to:
1. Create ECK entity type configurations
2. Export all field configurations
3. Convert field collection to paragraphs or custom entities in D11
4. Update all queries to use D11's database API
5. Convert cron queue implementation
6. Update views configurations
7. Test all functionality

Would you like me to create a basic framework for this module, or would you prefer to handle the ECK configuration manually through the admin interface?


