# Access Hierarchy Module

This module provides hierarchical access control for Sentinel Portal entities based on taxonomy hierarchies. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **Hierarchical Access Control**: Controls entity access based on taxonomy hierarchies
- **Client Cohort Management**: Manages client access through taxonomy-based cohorts
- **Recursive Taxonomy Processing**: Handles nested taxonomy structures
- **Query Alteration**: Automatically modifies entity queries based on user permissions

## Key Functionality

### Access Control
- **Query Alteration**: Implements `hook_query_TAG_alter()` to modify entity queries
- **Permission-Based**: Respects 'sentinel view own sentinel_sample' permission
- **Admin Override**: Admin users (UID 1) bypass all restrictions

### Hierarchy Management
- **Taxonomy Integration**: Uses 'sentinel_monitor_hierarchy' vocabulary
- **Recursive Processing**: Handles multi-level taxonomy hierarchies
- **Client Mapping**: Maps taxonomy terms to client IDs

### Core Functions

#### `access_hierachy_query_sample_entity_query_access_alter()`
- **Purpose**: Alters entity queries to enforce hierarchical access
- **Functionality**: Adds JOIN conditions and WHERE clauses based on user cohorts
- **Access Control**: Checks permissions and user roles

#### `get_more_clients_based_client_cohorts()`
- **Purpose**: Gets additional client IDs based on user's taxonomy cohorts
- **Input**: Sentinel client object
- **Output**: Array of client IDs the user can access
- **Process**: Recursively processes taxonomy hierarchies

#### `taxonomy_get_children_all()`
- **Purpose**: Recursively gets all child taxonomy terms
- **Input**: Taxonomy term ID, vocabulary ID
- **Output**: Array of all descendant term IDs
- **Recursion**: Handles unlimited depth hierarchies

#### `get_client_ids_based_on_user_taxonomies()`
- **Purpose**: Gets client IDs associated with specific taxonomy terms
- **Input**: Array of taxonomy term IDs
- **Output**: Array of client IDs
- **Database**: Queries sentinel_client and field_data tables

## Dependencies

- `sentinel_portal_entities` - Sentinel client and sample entities
- `taxonomy` - Taxonomy system for hierarchy management

## Installation

1. Place the module in `/web/modules/custom/access_hierachy/`
2. Enable the module via Drush: `drush en access_hierachy`
3. Ensure the 'sentinel_monitor_hierarchy' vocabulary exists
4. Configure client cohorts using taxonomy terms

## Usage

### For Administrators

1. **Create Hierarchy**: Set up taxonomy terms in 'sentinel_monitor_hierarchy'
2. **Assign Cohorts**: Assign taxonomy terms to client entities
3. **Configure Access**: The module automatically enforces access rules

### For Users

1. **Automatic Enforcement**: Access is automatically controlled based on cohorts
2. **Hierarchical Access**: Users can access entities within their hierarchy
3. **Permission Respect**: Module respects existing Drupal permissions

## Technical Details

### Query Alteration
The module uses Drupal's query alteration system to modify entity queries:
- **Hook**: `hook_query_TAG_alter()`
- **Tag**: `sample_entity_query_access`
- **Modification**: Adds JOIN and WHERE conditions

### Database Integration
- **Tables**: `sentinel_client`, `field_data_field_user_cohorts`
- **Joins**: LEFT JOIN between client and cohort data
- **Conditions**: IN clause for multiple client IDs

### Taxonomy Processing
- **Vocabulary**: 'sentinel_monitor_hierarchy'
- **Recursion**: Unlimited depth hierarchy support
- **Performance**: Efficient recursive processing

## Integration

This module integrates with:
- **Sentinel Portal**: Client and sample entity management
- **Taxonomy System**: Hierarchy management
- **Permission System**: Drupal's built-in access control
- **Query System**: Entity query modification

## Migration from Drupal 7

Key changes made during conversion:
- **Global Variables**: `$user` → `\Drupal::currentUser()`
- **Database API**: Direct queries → Database abstraction layer
- **Taxonomy API**: `taxonomy_get_children()` → `loadTree()`
- **Entity API**: Field access → Entity field API
- **Query Interface**: `QueryAlterableInterface` → `AlterableInterface`

## Security Features

- **Permission Checking**: Respects Drupal permissions
- **Admin Override**: Admin users bypass restrictions
- **SQL Injection Protection**: Uses parameterized queries
- **Access Control**: Hierarchical access enforcement

## Performance Considerations

- **Query Optimization**: Efficient JOIN operations
- **Caching**: Leverages Drupal's query caching
- **Recursion Limits**: Handles deep hierarchies efficiently
- **Database Indexing**: Relies on proper database indexes

## Troubleshooting

### Common Issues

1. **No Access**: Check taxonomy assignments and permissions
2. **Performance**: Ensure proper database indexes
3. **Hierarchy**: Verify taxonomy structure

### Debugging

- Enable query logging to see modified queries
- Check taxonomy term assignments
- Verify client cohort configurations

## Future Enhancements

- **Caching**: Add caching for hierarchy calculations
- **UI**: Administrative interface for hierarchy management
- **API**: REST API endpoints for hierarchy operations
- **Reporting**: Access audit and reporting features


