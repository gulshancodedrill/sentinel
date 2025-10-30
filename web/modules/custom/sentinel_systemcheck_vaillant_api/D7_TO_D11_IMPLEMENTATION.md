# Vaillant API Configuration - D7 to D11 Implementation Comparison

## Drupal 7 Implementation

**File**: `/var/www/html/drupal7-project/docroot/sites/default/settings.php`  
**Lines**: 555-564

```php
if ($server['AH_SITE_ENVIRONMENT'] = 'prod') {
  // Update when we get confirmation of Key & Endpoint
  // Get the Vaillant API key that is stored in Acquia variables
  $conf['sentinel_vaillant_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
  $conf['sentinel_vaillant_endpoint']= 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
} else {
  // Get the Vaillant API key that is stored in Acquia variables
  $conf['sentinel_vaillant_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
  $conf['sentinel_vaillant_endpoint']= 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
}
```

### D7 Characteristics:
- Uses `$conf` array (D7's configuration override system)
- Checks `$server['AH_SITE_ENVIRONMENT']` for Acquia Cloud environments
- API key comes from environment variable: `VAILLANT_API`
- Endpoint is **hardcoded** (not from environment variable)
- Both prod and non-prod branches are identical
- **No admin interface** - configuration only in settings.php

## Drupal 11 Implementation

**File**: `/var/www/html/sentinel11/sentinel/web/sites/default/settings.php`  
**Lines**: 879-915

```php
// Check if we're in Acquia environment (same check as D7, which uses $server['AH_SITE_ENVIRONMENT'])
// In D7, $server is typically set by Acquia Cloud's include files
$is_prod = FALSE;
if (isset($_ENV['AH_SITE_ENVIRONMENT']) && $_ENV['AH_SITE_ENVIRONMENT'] === 'prod') {
  $is_prod = TRUE;
}
// Also check $server array if it exists (for Acquia Cloud compatibility, matches D7 behavior)
if (isset($server) && is_array($server) && isset($server['AH_SITE_ENVIRONMENT']) && $server['AH_SITE_ENVIRONMENT'] === 'prod') {
  $is_prod = TRUE;
}

// Set Vaillant API configuration (same logic for both prod and non-prod in D7)
// Get the Vaillant API key that is stored in Acquia variables (or environment)
if ($is_prod) {
  // Production environment - Update when we get confirmation of Key & Endpoint
  // Get the Vaillant API key that is stored in Acquia variables
  $config['sentinel_systemcheck_vaillant_api.settings']['api_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
  $config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
}
else {
  // Non-production environment
  // Get the Vaillant API key that is stored in Acquia variables
  $config['sentinel_systemcheck_vaillant_api.settings']['api_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
  $config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
}
```

### D11 Characteristics:
- Uses `$config` array (D11's configuration override system)
- Checks both `$_ENV['AH_SITE_ENVIRONMENT']` and `$server['AH_SITE_ENVIRONMENT']` for compatibility
- API key comes from environment variable: `VAILLANT_API` (same as D7)
- Endpoint is **hardcoded** (same as D7 - not from environment variable)
- Both prod and non-prod branches are identical (same as D7)
- **Admin interface available** at `/admin/config/system/sentinel-vaillant-api` (NEW in D11)

## Key Differences

| Aspect | Drupal 7 | Drupal 11 |
|--------|----------|-----------|
| Configuration Array | `$conf` | `$config['sentinel_systemcheck_vaillant_api.settings']` |
| Variable Names | `sentinel_vaillant_key`, `sentinel_vaillant_endpoint` | `api_key`, `endpoint` (within config object) |
| Environment Detection | `$server['AH_SITE_ENVIRONMENT']` | `$_ENV['AH_SITE_ENVIRONMENT']` + `$server['AH_SITE_ENVIRONMENT']` |
| Endpoint Source | Hardcoded | Hardcoded (same as D7) |
| API Key Source | Environment variable `VAILLANT_API` | Environment variable `VAILLANT_API` (same) |
| Admin Interface | ❌ None | ✅ Available at `/admin/config/system/sentinel-vaillant-api` |

## Module Code Access

### D7 Module Code:
```php
$key = variable_get('sentinel_vaillant_key');
$endpoint = variable_get('sentinel_vaillant_endpoint');
```

### D11 Module Code:
```php
// Function automatically checks environment variables first, then config
$key = sentinel_systemcheck_vaillant_api_get_key();
// Endpoint is read with same priority
```

The D11 module functions check in this order:
1. Environment variables (`VAILLANT_API`, optionally `VAILLANT_API_ENDPOINT`)
2. Configuration from `settings.php` or admin form
3. Default values

This maintains compatibility with D7 while providing additional flexibility.
