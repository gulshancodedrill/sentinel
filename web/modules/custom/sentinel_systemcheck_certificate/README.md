# Sentinel SystemCheck Certificate Module

This module provides PDF certificate generation and validation logic for Sentinel SystemCheck results. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **PDF Certificate Generation**: Creates professional PDF certificates for SystemCheck results
- **Matrix Upload System**: Allows administrators to upload CSV files with validation rules
- **Dynamic Validation Logic**: Generates PHP validation scripts from uploaded matrix data
- **Result Processing**: Processes sample analysis results and applies validation rules
- **Multi-language Support**: Supports multiple languages for certificates and recommendations
- **File Management**: Handles PDF file storage and access control

## Key Functions

### Core Functions

- `sentinel_systemcheck_certificate_populate_results($result_object, $sentinel_sample, $formatted_results)` - Main function for populating sample results
- `sentinel_systemcheck_certificate_get_dompdf_object($data)` - Creates DomPDF object for PDF generation
- `sentinel_systemcheck_certificate_calculate_excess_cloride_result($mains_cl_result, $boron_result, $sys_cl_result)` - Calculates excess chloride
- `_calculate_sentinel_sample_result($result_object, $sentinel_sample)` - Calculates overall sample result
- `_apply_hierachy_conditions_for_overall_recommendations($recommendations, $lang)` - Applies recommendation hierarchy

### Helper Functions

- `_get_result_content($sentinel_sample_id, $sample_type)` - Gets certificate content data
- `sentinel_system_get_addresses_from_sample_entity($address_type, $sample_entity)` - Formats addresses
- `_re_generate_logic_from_condition_entities()` - Regenerates validation logic
- `_create_php_script($condition_entities, $buffer_filename, $real_filename)` - Creates validation PHP script
- `apply_strict_checking_to_element($var, &$element)` - Applies strict type checking

## Routes

- `/admin/matrix-upload` - Matrix upload form (admin only)
- `/view-my-results/{sample_id}` - View certificate results
- `/view-my-results-pdf/{sample_id}` - View PDF certificate
- `/portal/admin/samples/pdf/{sample_id}/refresh` - Regenerate PDF

## Dependencies

- `sentinel_portal_module` - Main portal module
- `sentinel_portal_entities` - Sample entity management
- `field` - Field operations
- `file` - File operations
- `user` - User management
- `system` - Core system functionality
- `taxonomy` - Taxonomy management
- `views` - Views integration

## Installation

1. Place the module in `/web/modules/custom/sentinel_systemcheck_certificate/`
2. Enable the module via Drush: `drush en sentinel_systemcheck_certificate`
3. The module will automatically create required taxonomy vocabularies and terms

## Usage

### For Administrators

1. **Upload Matrix**: Go to `/admin/matrix-upload` to upload CSV files with validation rules
2. **Regenerate PDFs**: Use the refresh link on sample pages to regenerate certificates
3. **Manage Rules**: The system automatically generates validation logic from uploaded matrices

### For Users

1. **View Results**: Access `/view-my-results/{sample_id}` to see certificate results
2. **Download PDF**: Use `/view-my-results-pdf/{sample_id}` to view/download PDF certificates

## Matrix Upload Format

The CSV file should contain the following columns:
- `event_number` - Sequential number for the rule
- `event_string` - Validation condition (e.g., "$ph > 7.5")
- `pass_fail` - Result taxonomy term (Pass/Fail/Warning)
- `individual_comment` - Comment for this specific result
- `individual_recommendation` - Overall recommendation
- `analysis` - Analysis element name

## Technical Details

### Validation Logic Generation

The module generates PHP validation scripts from uploaded matrix data. These scripts:
- Process sample analysis results
- Apply conditional logic based on uploaded rules
- Generate pass/fail results for each analysis element
- Create hierarchical recommendations

### PDF Generation

- Uses DomPDF library for PDF generation
- Supports custom CSS styling
- Generates professional certificates with company branding
- Handles multi-language content

### File Access Control

- Implements proper access control for PDF downloads
- Ensures users can only access certificates for their own samples
- Provides admin override capabilities

## Integration

This module integrates with:
- **Queue System**: Called by `sentinel_portal_queue` for result processing
- **Sample Entities**: Works with `sentinel_portal_entities` for sample management
- **Portal System**: Integrates with the main portal interface

## Future Enhancements

- Enhanced PDF templates with more customization options
- Advanced matrix validation with more complex conditions
- Email integration for automatic certificate delivery
- Statistics and reporting capabilities
- API endpoints for external system integration