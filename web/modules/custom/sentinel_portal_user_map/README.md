# Sentinel Portal User Map Module

This module provides functionality to map Sentinel Portal users and their related cohorts/locations. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **User Management**: Add new users to the portal system
- **Location Mapping**: Assign users to specific locations/cohorts
- **User Search**: Autocomplete search for existing users
- **Access Control**: Proper permission-based access control
- **Location Hierarchy**: Support for hierarchical location structures

## Key Functionality

### User Management
- **Add New Users**: Create new portal users with email, name, organization, and location assignment
- **Update Existing Users**: Add additional location access to existing users
- **User Search**: Autocomplete functionality to find existing users

### Location Management
- **Hierarchical Locations**: Support for nested location structures using taxonomy
- **Cohort Assignment**: Users can be assigned to multiple location cohorts
- **Location Display**: Visual representation of location hierarchy with indentation

### Access Control
- **Permission-Based**: Uses 'sentinel portal' permission for access control
- **Cohort Validation**: Users must have cohorts to access the functionality
- **Admin Override**: Admin users (UID 1) have full access

## Routes

- `/portal/user-list` - Main user list page showing locations and associated users
- `/portal/user-map-add-user` - Form to add new users
- `/portal/user-map-update-user` - Form to update existing users' locations
- `/portal/user-list/autocomplete` - AJAX autocomplete endpoint for user search

## Forms

### AddUserForm
- **Email**: Sub contractor email address (required, validated)
- **Name**: Sub contractor name (required)
- **Organization**: Sub contractor organization (required)
- **Location**: Location selection from available cohorts (required)

### UpdateUserForm
- **User**: Autocomplete field to search and select existing users
- **Location**: Location selection to add to user's existing access

## Key Functions

### Core Functions
- `sentinel_portal_user_map_client_has_cohorts($account)` - Check if user has cohorts
- `sentinel_portal_user_map_values()` - Get available locations for current user
- `sentinel_portal_user_map_selectfield()` - Build location options for forms
- `sentinel_portal_user_map_cleanup_username($name, $uid)` - Clean up usernames
- `sentinel_portal_user_map_unique_username($name, $uid)` - Generate unique usernames

### User Creation Process
1. **Validation**: Email validation and duplicate checking
2. **Username Generation**: Clean and unique username creation
3. **User Creation**: Create Drupal user account with portal role
4. **Client Entity**: Create/update sentinel_client entity
5. **Location Assignment**: Assign user to selected location cohorts
6. **Notification**: Display success/error messages

### Location System
- Uses taxonomy terms for location hierarchy
- Supports nested locations with visual indentation
- Integrates with sentinel_client entity field_user_cohorts
- Provides autocomplete search within user's accessible locations

## Dependencies

- `sentinel_portal_module` - Main portal module
- `sentinel_portal_entities` - Client entity management
- `user` - User management
- `system` - Core system functionality
- `field` - Field operations
- `taxonomy` - Location taxonomy management

## Installation

1. Place the module in `/web/modules/custom/sentinel_portal_user_map/`
2. Enable the module via Drush: `drush en sentinel_portal_user_map`
3. Ensure the 'portal user' role exists
4. Configure location taxonomy terms

## Usage

### For Administrators

1. **View User Lists**: Access `/portal/user-list` to see all locations and their associated users
2. **Add New Users**: Use "Add new user" link to create new portal users
3. **Update User Access**: Use "Add existing user" links to grant additional location access

### For Portal Users

1. **Manage Subcontractors**: Add and manage subcontractor users
2. **Location Assignment**: Assign users to appropriate locations/cohorts
3. **User Search**: Use autocomplete to quickly find existing users

## Technical Details

### Database Integration
- **Users Table**: Standard Drupal users table
- **Sentinel Client**: Custom client entity for portal-specific data
- **Field Data**: Uses field_data_field_user_cohorts for location assignments
- **Taxonomy**: Location hierarchy stored in taxonomy terms

### Security Features
- **Access Control**: Permission-based access with cohort validation
- **Input Validation**: Email validation and duplicate checking
- **SQL Injection Protection**: Uses Drupal's database abstraction layer
- **XSS Protection**: Proper output escaping and sanitization

### User Experience
- **Autocomplete**: Real-time user search with AJAX
- **Form Validation**: Client-side and server-side validation
- **User Feedback**: Clear success/error messages
- **Navigation**: Breadcrumb navigation and proper redirects

## Integration

This module integrates with:
- **Portal System**: Main portal navigation and access control
- **Client Entities**: Sentinel client management system
- **User System**: Drupal's core user management
- **Taxonomy System**: Location hierarchy management
- **Field System**: Custom field storage for user cohorts

## Future Enhancements

- **Bulk User Operations**: Import/export user lists
- **Advanced Search**: More sophisticated user search options
- **Email Notifications**: Automatic email notifications for user creation
- **Audit Trail**: Track user access changes
- **API Integration**: REST API endpoints for external system integration


