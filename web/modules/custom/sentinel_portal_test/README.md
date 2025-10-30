# Sentinel Portal Test Module

This module provides test utilities and test data for the Sentinel Portal system. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **Test Content Generation**: Generate test content for development and testing
- **Test Data**: Pre-populated test samples with various scenarios
- **Development Tools**: Utilities for portal development and debugging

## Key Functionality

### Test Content Generation

- **Route**: `/portal/test-generate-content`
- **Access**: Requires 'sentinel portal administration' permission
- **Functionality**: Creates test notice entities for development purposes

### Test Data Installation

The module automatically creates three test samples during installation:

1. **Sample 100:10000** - New sample without test results
   - Basic sample data with no analysis results
   - Represents a newly submitted pack

2. **Sample 100:10001** - Processed sample with passing results
   - Complete analysis results
   - All tests passing
   - Represents a successful analysis

3. **Sample 100:10002** - Failed sample with mixed results
   - Partial analysis results
   - Some tests failing
   - Represents a sample requiring attention

## Routes

- `/portal/test-generate-content` - Generate test content (requires admin permission)

## Dependencies

- `sentinel_portal_entities` - Entity management
- `sentinel_portal_module` - Main portal module
- `simpletest` - Testing framework (optional)

## Installation

1. Place the module in `/web/modules/custom/sentinel_portal_test/`
2. Enable the module via Drush: `drush en sentinel_portal_test`
3. The module will automatically create test sample data

## Usage

### For Developers

1. **Generate Test Content**: Access `/portal/test-generate-content` to create test notices
2. **Use Test Data**: The three pre-populated samples can be used for testing various scenarios
3. **Development Testing**: Use test data to verify portal functionality

### Test Scenarios

The module provides three distinct test scenarios:

1. **New Sample** - For testing submission workflows
2. **Passed Sample** - For testing successful analysis displays
3. **Failed Sample** - For testing failure handling and recommendations

## Technical Details

### Test Sample Data

- **Pack Reference Numbers**: 100:10000, 100:10001, 100:10002
- **Realistic Data**: Uses realistic installer, company, and location information
- **Complete Coverage**: Includes all sample entity fields with appropriate test values
- **Various States**: Samples in different processing states (new, processed, failed)

### Notice Generation

- **Dynamic Content**: Creates notices with timestamped titles
- **Educational Content**: Includes interesting background information
- **Read Status**: Tracks whether notices have been read
- **User Association**: Links notices to users

## Integration

This module integrates with:
- **Sample Entities**: Uses `sentinel_portal_entities` for sample management
- **Notice System**: Generates test notices for notification testing
- **Client System**: Uses client entities for user management

## Development

### Adding More Test Data

To add more test samples, modify the `hook_install()` function in `sentinel_portal_test.install`:

```php
$sample = $sample_storage->create([
  'pack_reference_number' => '100:10003',
  // ... other fields ...
]);
$sample->save();
```

### Test Content Generator

The test content generator creates realistic test data for development. It can be extended to create:
- Multiple notice types
- Various sample states
- Different client scenarios
- Complex test cases

## Future Enhancements

- **More Test Scenarios**: Additional predefined test cases
- **Bulk Test Data**: Generate multiple test samples at once
- **Test Fixtures**: Reusable test data fixtures
- **Automated Testing**: Integration with test frameworks
- **Performance Testing**: Test data for performance scenarios


