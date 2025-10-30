# Sentinel Portal Services Module

## Overview
This module provides REST API endpoints for Sentinel SystemCheck services, replacing the deprecated Drupal 7 Services module functionality.

## Features
- **Heartbeat Service**: Simple health check endpoint
- **Customer Service**: Customer lookup and creation
- **Sample Service**: Sample retrieval and creation

## API Endpoints

### 1. Heartbeat Service
**Endpoint:** `GET /sentinel/heartbeat`

Returns the current server timestamp.

**Response:**
```json
{
  "status": "200",
  "message": "2025-01-15 10:30:45"
}
```

### 2. Customer Service
**Endpoint:** `GET /sentinel/customerservice`

Returns or creates a customer ID for given customer details.

**Parameters:**
- `key` (required): API key
- `email` (required): Customer email
- `name` (required): Customer name
- `company` (optional): Company name

**Response:**
```json
{
  "status": "200",
  "message": {
    "name": "John Doe",
    "email": "john@example.com",
    "customer_id": "12345",
    "company": "Example Corp"
  }
}
```

### 3. Sample Service

#### Get Sample
**Endpoint:** `GET /sentinel/sampleservice`

Retrieves a sample by pack reference number.

**Parameters:**
- `key` (required): API key
- `pack_reference_number` (required): Pack reference number
- `ucr` (optional): Unique customer reference

**Response:**
```json
{
  "status": "200",
  "message": {
    "pack_reference_number": "123:456A",
    "company_email": "test@example.com",
    ...
  }
}
```

#### Create/Update Sample
**Endpoint:** `POST /sentinel/sampleservice`

Creates or updates a sample.

**Parameters:**
- `key` (required): API key (in query string)
- `data` (required): JSON payload with sample data

**Response:**
```json
{
  "status": "200",
  "message": "Sample created."
}
```

Or with errors:
```json
{
  "status": "200",
  "message": "Sample created (with errors).",
  "error": [
    {
      "error_column": "field_name",
      "error_description": "error description"
    }
  ]
}
```

## Authentication
All endpoints require an API key. The key is validated against the `sentinel_client` entity with the `api_key` field.

## Caching
All Sentinel services have caching disabled to ensure fresh data on every request.

## Drupal 7 to Drupal 11 Conversion Notes

### Key Changes:
1. **Services Module → REST Module**: Replaced D7 Services module with D11 REST API
2. **hook_services_resources() → REST Resource Plugins**: Converted service definitions to REST resource plugins
3. **EntityFieldQuery → Entity Query**: Updated database queries to use Entity Query API
4. **drupal_static() → Static arrays**: Simplified static caching
5. **services_error() → HTTP Exceptions**: Use Symfony HTTP exceptions for errors
6. **Hook-based architecture → Plugin-based**: REST resources are now plugins
7. **File structure**: Service callbacks moved to Plugin classes
8. **Configuration**: Service endpoints defined in YAML config files
9. **Response format**: Using ResourceResponse instead of returning arrays
10. **Caching**: Using Drupal 11 cache metadata system

### Files Converted:
- Service definitions → REST Resource Plugin classes:
  - `sentinel_portal_services.heartbeat.service.inc` → `HeartbeatResource.php`
  - `sentinel_portal_services.customer.service.inc` → `CustomerServiceResource.php`
  - `sentinel_portal_services.sample.service.inc` → `SampleServiceResource.php`
- `sentinel_portal_services.info` → `sentinel_portal_services.info.yml`
- `sentinel_portal_services.module` → Helper functions only
- Added REST configuration YAML files

### Removed Files:
- `sentinel_portal_services.services.inc` - No longer needed (Services module specific)
- `sentinel_portal_services.features.inc` - Features API not used in D11
- `sentinel_portal_services.install` - No database schema needed

## Dependencies
- rest
- serialization
- sentinel_portal_module
- sentinel_portal_entities

## Installation
Enable the module using Drush:
```bash
drush pm:enable sentinel_portal_services -y
```

## Testing
Test the endpoints using curl:

```bash
# Heartbeat
curl -X GET "https://example.com/sentinel/heartbeat" -H "Accept: application/json"

# Customer Service
curl -X GET "https://example.com/sentinel/customerservice?key=YOUR_API_KEY&email=test@example.com&name=John%20Doe" -H "Accept: application/json"

# Sample Service - Get
curl -X GET "https://example.com/sentinel/sampleservice?key=YOUR_API_KEY&pack_reference_number=123:456A" -H "Accept: application/json"

# Sample Service - Create
curl -X POST "https://example.com/sentinel/sampleservice?key=YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"pack_reference_number":"123:456A","ucr":"12345",...}'
```

## Security
- All endpoints validate the API key
- Access control is handled at the resource level
- No caching to prevent data leakage

## Author
Converted from Drupal 7 to Drupal 11



