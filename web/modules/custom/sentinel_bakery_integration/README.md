# Sentinel Bakery Integration Module

This module provides integration with Bakery SSO (Single Sign-On) for role synchronization.

## Features

- **Role Synchronization**: Automatically syncs user roles from Bakery SSO
- **Portal User Assignment**: Ensures all users get the "portal_user" role
- **Administrator Support**: Preserves administrator role for admin users
- **Role Mapping**: Maps roles from Bakery payload to local Drupal roles

## Key Functionality

### Bakery Hook
- **hook_bakery_receive()**: Called when receiving user data from Bakery SSO
- **Role Assignment**: Automatically assigns appropriate roles based on SSO data
- **User Update**: Updates user account with synced roles

### Role Handling
- **Authenticated Role**: All users get "authenticated" role
- **Portal User Role**: All users get "portal_user" role
- **Administrator Role**: Preserved if user is an administrator
- **Additional Roles**: Maps other roles from Bakery payload

## Files Structure

```
sentinel_bakery_integration/
├── sentinel_bakery_integration.info.yml      # Module definition
└── sentinel_bakery_integration.module        # Module hooks
```

## Dependencies

- **user**: Core user module
- **bakery**: Bakery SSO module (if available for D11)

## Installation

1. Place the module in `/web/modules/custom/sentinel_bakery_integration/`
2. Ensure Bakery module is installed (if using SSO)
3. Enable the module: `drush en sentinel_bakery_integration`

## Usage

The module automatically works when:
- Users authenticate via Bakery SSO
- User data is received from Bakery
- Roles need to be synchronized across systems

### Configuration

No configuration is required. The module automatically:
- Assigns "authenticated" role to all users
- Assigns "portal_user" role to all users  
- Preserves "administrator" role for admin users
- Maps additional roles from Bakery payload

## Technical Details

### Drupal 7 to 11 Conversion

#### Key Changes:
- **Role Loading**: `user_role_load_by_name()` → `Role::load()` / entity storage
- **User Saving**: `user_save()` → `$account->save()`
- **Role IDs**: Role IDs changed from numeric to machine names
- **Entity API**: Uses D11 entity API for user and role entities

#### Role Handling:
- **Machine Names**: Uses role machine names instead of numeric IDs
- **Entity Loader**: Uses entity type manager and Role entity class
- **Role Checking**: Uses `$account->hasRole()` method
- **Role Assignment**: Uses `$account->set('roles', ...)` 

### Compatibility

- **Bakery Module**: Works with Bakery if available for D11
- **Alternative SSO**: Can be adapted for other SSO systems
- **Role Names**: Assumes standard role machine names (authenticated, portal_user, administrator)

## Migration Notes

### From Drupal 7

#### Functionality Preserved:
- Role synchronization logic
- Portal user assignment
- Administrator role handling
- Additional role mapping

#### Improvements:
- Uses D11 entity API
- Better role management
- More flexible role loading
- Logging for debugging

### Role Name Changes

If your roles have different machine names, update the code:
- `authenticated user` → `authenticated`
- `portal user` → `portal_user`  
- `administrator` → `administrator`

## Security Considerations

- **Role Assignment**: Only assigns roles that exist in the system
- **Permission Checks**: Respects Drupal's permission system
- **Logging**: Logs role updates for audit purposes

## Troubleshooting

### Common Issues

1. **Roles Not Assigning**: Check role machine names match
2. **Bakery Puck Not Firing**: Verify Bakery is installed and configured
3. **Permission Errors**: Check user permissions for role assignment

### Debugging

- Check `sentinel_bakery_integration` log entries
- Verify Bakery configuration
- Confirm role machine names are correct
- Test SSO authentication flow

## Related Modules

- **bakery**: SSO module for Drupal
- **sentinel_portal_module**: Portal functionality
- **sentinel_portal_user_map**: User mapping functionality

## Future Enhancements

- **Configurable Roles**: Admin UI to configure which roles to assign
- **Role Mapping**: Configurable role name mapping
- **Additional Fields**: Sync additional user fields from SSO
- **SSO Alternatives**: Support for other SSO systems


