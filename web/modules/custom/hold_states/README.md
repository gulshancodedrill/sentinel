# Hold States Module

This module manages hold states for sentinel samples, including email notifications when hold states are applied.

## Features

- **Hold State Management**: Taxonomy-based hold state values
- **Email Notifications**: Automatic email to clients when hold states change
- **Field Support**: Multiple email content fields for hold states
- **Token Replacement**: Dynamic content in email templates
- **Sample Integration**: Field on sentinel_sample entity

## Key Functionality

### Hold State Values
- **Taxonomy Vocabulary**: `hold_state_values`
- **Email Fields**: Subject, contents, specs, issue resolution, disposal warning
- **Email To**: Selectable email recipient (customer/none)
- **Issue With**: Issue type (sample/information_supplied)

### Email Notification
- **Automatic**: Sends email when hold state changes
- **Template Fields**: Multiple content sections for email
- **Token Support**: Dynamic content replacement
- **Fallback**: Sends admin notification if client email not found

### Sample Integration
- **Field**: `field_sample_hold_state` on sentinel_sample entity
- **Entity Hook**: Monitors entity updates
- **State Change**: Detects hold state changes

## Files Structure

```
hold_states/
├── hold_states.info.yml                                         # Module definition
├── hold_states.module                                           # Module hooks
├── config/
│   └── install/
│       └── taxonomy.vocabulary.hold_state_values.yml            # Taxonomy vocabulary
├── config/
│   └── install/
│       ├── field.storage.taxonomy_term.*.yml                     # Field storage configs
│       └── field.field.taxonomy_term.hold_state_values.*.yml    # Field instance configs
└── README.md                                                    # Documentation
```

## Dependencies

- **sentinel_portal_entities**: For sentinel_sample and sentinel_client entities
- **taxonomy**: Core taxonomy module
- **text**: Core text module
- **options**: Core options module

## Installation

1. Place the module in `/web/modules/custom/hold_states/`
2. Enable the module: `drush en hold_states`
3. The taxonomy vocabulary and fields will be automatically created

## Usage

### Setting Hold State

1. Navigate to a sentinel sample
2. Select a hold state from the dropdown
3. Save the sample
4. Email notification will be sent if configured

### Configuring Hold States

1. Go to Structure > Taxonomy > Hold state values
2. Add/edit taxonomy terms
3. Configure email fields:
   - Email subject
   - Email intro
   - Email issue specifics
   - Email issue Rectifying action
   - Email disposal warning
   - Email to (customer/none)
   - Issue with (sample/information_supplied)

### Field Definitions

The module creates the following fields on taxonomy terms:

- **field_email_subject**: Email subject (Text 255)
- **field_hold_state_email_contents**: Email intro (Text long)
- **field_hold_state_email_specs**: Email issue specifics (Text long)
- **field_hold_state_email_issue_rec**: Email issue Rectifying action (Text long)
- **field_hold_state_email_disposal**: Email disposal warning (Text long)
- **field_hold_state_email_to**: Email to (List: customer/none)
- **field_hold_state_issue_with**: Issue with (List: sample/information_supplied)

And on sentinel_sample entities:
- **field_sample_hold_state**: Sample hold state (Taxonomy reference)

## Technical Details

### Drupal 7 to 11 Conversion

#### Key Changes:
- **Hook Implementation**: Updated for D11 entity system
- **Field Access**: Uses new entity field API
- **Mail System**: Uses D11 mail plugin system
- **Token Replacement**: Custom implementation maintained
- **Entity Updates**: Proper original entity handling

#### Entity Hook:
- **hook_entity_update()**: Monitors sentinel_sample updates
- **Original Entity**: Preserved for comparison
- **Field Access**: Proper field access methods
- **Email Trigger**: Only when hold state changes

### Email System

#### Mail Plugin:
- **Key**: 'fault'
- **Subject**: From field_email_subject
- **Body**: Multiple content fields combined
- **Recipient**: Client email or admin fallback

#### Token Support:
- Basic token replacement for common fields
- Extensible for additional tokens
- Replaces entity field values

### Integration

#### With Sentinel Entities:
- **Sample Entity**: Monitors field_sample_hold_state
- **Client Entity**: Retrieves client for email
- **Field Access**: Proper field access methods

## Migration Notes

### From Drupal 7 Features

The D7 version used Features module to manage:
- Taxonomy vocabulary
- Field base and instance definitions
- Field groups
- Views configurations

D11 version uses:
- Configuration files for all definitions
- Standard D11 configuration system
- Views managed via UI or configuration export

### Compatibility

- **Entity Type**: Works with sentinel_sample entities
- **Taxonomy**: Same vocabulary machine name
- **Fields**: Same field names preserved
- **Email System**: Compatible mail structure

## Security Considerations

- **Email Validation**: Validates email addresses
- **Access Control**: Follows entity access control
- **Data Privacy**: Email content handled securely

## Performance

### Caching
- **Entity Cache**: Uses D11's entity cache
- **Field Definitions**: Cached field definitions
- **Efficient**: Only processes changed entities

### Optimization
- **Lazy Loading**: Only loads when needed
- **Conditional Processing**: Only when state changes
- **Minimal Overhead**: Efficient implementation

## Troubleshooting

### Common Issues

1. **Email Not Sending**: Check mail configuration
2. **Token Not Replacing**: Verify field names
3. **State Not Changing**: Check entity update hook

### Debugging

- Check hold_states log entries
- Verify field configuration
- Test token replacement
- Check mail system status

## Related Modules

- **sentinel_portal_entities**: Provides sample and client entities
- **sentinel_systemcheck_certificate**: May use hold states
- **sentinel_portal_module**: Portal integration


