<?php

/**
 * @file
 * README for Vaillant API Configuration
 */

# Vaillant API Configuration for Drupal 11

This module reads API configuration from environment variables (same as Drupal 7) with fallback to configuration.

## Configuration Methods

### Method 1: Environment Variables (Recommended - same as D7)

In your `settings.php` file or environment configuration, set:

```php
// These will be automatically read by the module (no code needed in settings.php)
// Just ensure these environment variables are set:
// - VAILLANT_API (the API key)
// - VAILLANT_API_ENDPOINT (optional, defaults to the production endpoint)
```

Or in `settings.php`, you can set them directly from environment:

```php
// Vaillant API Configuration (matches D7 approach)
// Read from environment variables like D7 settings.php
if (getenv('VAILLANT_API') || isset($_ENV['VAILLANT_API'])) {
  $config['sentinel_systemcheck_vaillant_api.settings']['api_key'] = getenv('VAILLANT_API') ?: $_ENV['VAILLANT_API'];
}
if (getenv('VAILLANT_API_ENDPOINT') || isset($_ENV['VAILLANT_API_ENDPOINT'])) {
  $config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = getenv('VAILLANT_API_ENDPOINT') ?: $_ENV['VAILLANT_API_ENDPOINT'];
} else {
  // Default endpoint (same as D7)
  $config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
}
```

### Method 2: Direct Configuration Override in settings.php

```php
// Vaillant API Configuration
$config['sentinel_systemcheck_vaillant_api.settings']['api_key'] = 'your-api-key-here';
$config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
```

### Method 3: Admin Configuration UI

After installing the module, you can configure it via:
- `/admin/config/system/sentinel-vaillant-api` (if admin form is created)

## How It Works

The module functions check in this order:
1. Environment variables (`VAILLANT_API`, `VAILLANT_API_ENDPOINT`)
2. Configuration system (`$config['sentinel_systemcheck_vaillant_api.settings']`)
3. Default values (endpoint only)

This matches the D7 behavior where `variable_get()` was used with values from `settings.php` `$conf` array.

## Drupal 7 to Drupal 11 Comparison

**D7 (settings.php):**
```php
$conf['sentinel_vaillant_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
$conf['sentinel_vaillant_endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
```

**D7 (module code):**
```php
$key = variable_get('sentinel_vaillant_key');
$endpoint = variable_get('sentinel_vaillant_endpoint');
```

**D11 (module code - automatic):**
```php
// Reads from environment first, then config
$key = sentinel_systemcheck_vaillant_api_get_key(); // Checks env vars first
$endpoint = // Checks env vars first, then config
```

## Production Setup

For production environments (like Acquia Cloud), set the environment variables:
- `VAILLANT_API` - Your API key
- `VAILLANT_API_ENDPOINT` - (Optional) API endpoint URL

The module will automatically use these values, just like D7 did.


