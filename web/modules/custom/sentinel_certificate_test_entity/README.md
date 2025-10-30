# Sentinel Certificate Test Entity Module

This module allows Sentinel to test the certificate generation report for test entities.

## Features

- **PDF Generation**: Provides route for generating PDF certificates for test entities
- **Field Mapping**: Maps field values to entity properties on save
- **Default Values**: Applies default property values to test entities
- **Custom Entity Class**: Extends ECK entity with helper methods
- **Sample Type Detection**: Determines sample type based on pack reference number
- **X100 Calculation**: Calculates X100 values based on molybdenum results
- **Country Detection**: Gets country code based on pack reference number

## Key Functionality

### PDF Generation
- **Route**: `/test_entity/condition_entity/{test_entity_id}/pdf`
- **Integration**: Uses `sentinel_systemcheck_certificate` for PDF generation
- **Permission**: Requires `sentinel portal` permission

### Entity Presave
- **Field Mapping**: Maps field values to entity properties
- **Default Values**: Applies default values for missing properties
- **Automatic**: Runs automatically when test_entity is saved

### TestSampleEntity Class
- **Extends**: ECK Entity base class
- **Methods**:
  - `getSampleType()` - Detects sample type (standard, vaillant, worcesterbosch_contract, worcesterbosch_service)
  - `calculateX100()` - Calculates X100 result from molybdenum
  - `getSampleCountry()` - Gets country code (ISO 3166-1 alpha-2)
  - `isLegacy()` - Always returns FALSE for D11

### Form Alterations
- **Feeds Import**: Adds instructions to feeds import form

## Files Structure

```
sentinel_certificate_test_entity/
├── sentinel_certificate_test_entity.info.yml       # Module definition
├── sentinel_certificate_test_entity.module         # Module hooks
├── sentinel_certificate_test_entity.routing.yml    # Route definitions
├── src/
│   ├── Controller/
│   │   └── TestEntityPdfController.php             # PDF controller
│   └── Entity/
│       └── TestSampleEntity.php                    # Custom entity class
└── README.md                                       # Documentation
```

## Dependencies

- **sentinel_portal_module**: Portal module
- **sentinel_portal_entities**: Portal entities
- **eck**: Entity Construction Kit
- **test_entity**: Test entity module (ECK entity)

## Installation

1. Place the module in `/web/modules/custom/sentinel_certificate_test_entity/`
2. Ensure `test_entity` ECK entity type exists
3. Enable the module: `drush en sentinel_certificate_test_entity`

## Usage

### PDF Generation

Access PDF for a test entity:
```
/test_entity/condition_entity/{test_entity_id}/pdf
```

### Entity Class Usage

```php
// Load a test entity.
$entity = \Drupal::entityTypeManager()
  ->getStorage('test_entity')
  ->load($id);

// Get sample type.
$type = $entity->getSampleType();

// Calculate X100.
$entity->calculateX100();

// Get country.
$country = $entity->getSampleCountry();
```

### Field to Property Mapping

The module automatically maps these fields to properties:
- `field_test_pack_reference_number` → `pack_reference_number`
- `field_test_appearance_result` → `appearance_result`
- `field_test_system_6_months` → `system_6_months`
- `field_test_ph_level` → `ph_result`
- And many more...

## Technical Details

### Drupal 7 to 11 Conversion

#### Key Changes:
- **Routing**: `hook_menu()` → `.routing.yml`
- **Entity Presave**: Updated for D11 entity API
- **Entity Class**: Extends `EckEntity` instead of `Entity`
- **Field Access**: Uses `hasField()` and `get()` methods
- **Property Setting**: Uses `set()` method when available

#### Entity Class:
- **Base Class**: Changed from `Entity` to `EckEntity`
- **Field Access**: Updated to use D11 field API
- **Property Access**: Handles both field-based and property-based access

### Sample Type Detection

Detects sample type based on pack reference number prefix:
- `102` - Standard Systemcheck Pack (or Vaillant if customer/project/boiler IDs present)
- `001` - Vaillant Systemcheck Pack
- `005` - Worcester Bosch Contract Form
- `006` - Worcester Bosch Service Form

### Country Code Detection

Returns ISO 3166-1 alpha-2 country codes:
- `120` → `it` (Italy)
- `210`, `110` → `de` (Germany)
- `130` → `fr` (France)
- Default → `gb` (United Kingdom)

### X100 Calculation

Formula: `(molybdenum_result / 75) * 100`
- Rounded to 2 decimal places
- Only calculated for non-legacy entities
- Requires molybdenum_result > 0

## Migration Notes

### From Drupal 7

#### Functionality Preserved:
- Field-to-property mapping logic
- Default value assignment
- Sample type detection
- X100 calculation
- Country code detection
- PDF generation route

#### Improvements:
- Uses D11 entity API
- Better field access methods
- Proper entity class structure
- Type-safe operations

## Related Modules

- **sentinel_systemcheck_certificate**: Handles actual PDF generation
- **test_entity**: ECK entity type for test entities
- **sentinel_portal_entities**: Portal entity definitions

## Troubleshooting

### Common Issues

1. **PDF Not Generating**: Check `sentinel_systemcheck_certificate` is enabled
2. **Properties Not Setting**: Verify entity type supports properties
3. **Field Mapping Failing**: Check field names match

### Debugging

- Check entity presave hooks are firing
- Verify field-to-property mapping
- Test PDF generation endpoint
- Review entity class methods


