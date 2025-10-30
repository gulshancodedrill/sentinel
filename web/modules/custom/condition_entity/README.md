# Condition Entity Module

This module provides a Condition Entity feature using ECK (Entity Construction Kit) for Drupal 11. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **ECK Entity Type**: Defines `condition_entity` entity type using ECK
- **ECK Bundle**: Defines `condition_entity` bundle
- **Fields**: Multiple fields for condition event data
- **Taxonomy**: Vocabulary for condition event results
- **Views Integration**: Supports Drupal Views

## Key Functionality

### Entity Type
- **Machine Name**: `condition_entity`
- **Label**: Condition Entity
- **Properties**: 
  - Title (managed)
  - UID (author)
  - Created
  - Changed
  - Language

### Bundle
- **Machine Name**: `condition_entity`
- **Label**: condition_entity
- **Description**: Condition Entity

### Fields

#### Field: Event element
- **Type**: Text (string, max 255)
- **Required**: Yes
- **Cardinality**: 1

#### Field: Event Number
- **Type**: Integer
- **Required**: Yes
- **Cardinality**: 1

#### Field: Condition Event Result
- **Type**: Entity Reference (Taxonomy Term)
- **Required**: No
- **Target**: `condition_event_results` vocabulary
- **Cardinality**: 1

#### Field: Event String
- **Type**: Text (long text)
- **Required**: No
- **Description**: Every element must be separated by a space. Do not change variable names. All variable names must have a $ in front of disc. Conditions are always separated by an "and"
- **Cardinality**: جهانی

#### Field: Event Individual Comment
- **Type**: Text (long text)
- **Required**: No
- **Cardinality**: 1

#### Field: Event Individual Recommendation
- **Type**: Text (long text)
- **Required**: No
- **Cardinality**: 1

#### Field: Number of white spaces
- **Type**: Text (string, max 255)
- **Required**: No
- **Cardinality**: 1

### Taxonomy Vocabulary
- **Machine Name**: `condition_event_results`
- **Label**: Condition event results
- **Description**: Sentinel Certificate pass/fail values
- **Hierarchy**: Flat (no hierarchy)

## Files Structure

```
condition_entity/
├── condition_entity.info.yml                                          # Module definition
├── condition_entity.module                                            # Module hooks
├── config/
│   └── install/
│       ├── eck.eck_entity_type.condition_entity.yml                   # ECK entity type
│       ├── eck.eck_type.condition_entity.condition_entity.yml         # ECK bundle
│       ├── taxonomy.vocabulary.condition_event_results.yml            # Taxonomy vocabulary
│       ├── field.storage.condition_entity.*.yml                       # Field storage configs
│       └── field.field.condition_entity.condition_entity.*.yml        # Field instance configs
└── README.md                                                          # Documentation
```

## Dependencies

- **ECK**: Entity Construction Kit module
- **Taxonomy**: Core taxonomy module
- **Text**: Core text module
- **Options**: Core options module
- **Field**: Core field module
- **sentinel_portal_entities**: Sentinel Portal Entities module

## Installation

1. Place the module in `/web/modules/custom/condition_entity/`
2. Enable the module: `drush en condition_entity`
3. The entity type, bundle, fields, and vocabulary will be automatically created

## Usage

### Creating Condition Entities

```php
// Load entity type manager
$storage = \Drupal::entityTypeManager()->getStorage('condition_entity');

// Create a new condition entity
$entity = $storage->create([
  'type' => 'condition_entity',
  'title' => 'My Condition',
  'field_condition_event_element' => 'Element 1',
  'field_condition_event_number' => 123,
  // ... other fields
]);

$entity->save();
```

### Loading Condition Entities

```php
// Load by ID
$entity = \Drupal::entityTypeManager()
  ->getStorage('condition_entity')
  ->load($id);

// Query entities
$query = \Drupal::entityQuery('condition_entity')
  ->condition('field_condition_event_number', 123)
  ->execute();
```

## Technical Details

### Drupal 7 to 11 Conversion

#### Key Changes:
- **Features Module**: Converted from Features to D11 configuration system
- **ECK Hooks**: `hook_eck_entity_type_info()` and `hook_eck_bundle_info()` → Config files
- **Field Definitions**: `hook_field_default_field_bases()` and `hook_field_default_field_instances()` → Config files
- **Taxonomy**: `hook_taxonomy_default_vocabularies()` → Config file
- **Views**: Views now managed via UI or exported config

#### Configuration System:
- **ECK Entity Type**: Defined in `eck.eck_entity_type.*.yml`
- **ECK Bundle**: Defined in `eck.eck_type.*.*.yml`
- **Field Storage**: Defined in `field.storage.*.*.yml`
- **Field Instances**: Defined in `field.field.*.*.*.yml`
- **Taxonomy**: Defined in `taxonomy.vocabulary.*.yml`

### Entity Structure

#### Entity Type Configuration
```yaml
id: condition_entity
label: Condition Entity
uid: true      # Author support
created: true  # Created timestamp
changed: true  # Changed timestamp
title: true    # Title field
language: true # Language support
```

#### Bundle Configuration
```yaml
id: condition_entity.condition_entity
type: condition_entity
name: condition_entity
description: Condition Entity
```

### Field Deficions

All fields follow D11 configuration structure:
- **Field Storage**: Defines field type and base settings
- **Field Instance**: Defines field on specific bundle with widget/display settings

## Migration Notes

### From Drupal 7 Features

The D7 version used Features module to manage:
- ECK entity type and bundle definitions
- Field base and instance definitions
- Taxonomy vocabulary
- Views configurations

D11 version uses:
- Configuration files for all entity/field definitions
- Standard D11 configuration system
- Views managed via UI or configuration export

### Compatibility

- **Entity Type**: Same machine name (`condition_entity`)
- **Bundle**: Same machine name (`condition_entity`)
- **Fields**: Same field names preserved
- **Taxonomy**: Same vocabulary machine name

## Security Considerations

- **Entity Access**: Uses ECK's access control system
- **Field Access**: Field-level access via field permissions
- **No Special Permissions**: Uses standard ECK permissions

## Performance

- **Caching**: Entity definitions cached
- **Indexes**: Fields can be indexed as needed
- **Storage**: Uses D11's field storage system

## Future Enhancements

- **Views**: Add default views configurations
- **Permissions**: Custom permission definitions if needed
- **Form Alterations**: Custom form handling if required
- **Validation**: Additional validation rules

## Related Modules

- **sentinel_systemcheck_certificate**: Uses condition entities for certificate generation
- **access_hierachy**: May query condition entities
- **sentinel_portal_entities**: Related entity types

## Troubleshooting

### Common Issues

1. **Fields Not Appearing**: Clear cache after installation
2. **Entity Type Not Found**: Ensure ECK module is enabled
3. **Field Storage Errors**: Check field dependencies

### Debugging

- Clear all caches: `drush cr`
- Verify config: Check config export
- Check dependencies: Ensure all required modules enabled


