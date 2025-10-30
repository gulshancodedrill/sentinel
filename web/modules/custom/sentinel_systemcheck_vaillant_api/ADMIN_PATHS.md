# Vaillant API Configuration - Admin Paths Documentation

## Summary

### Drupal 7
**No Admin Interface** - Configuration was done **only in `settings.php`**

- **File**: `/var/www/html/drupal7-project/docroot/sites/default/settings.php`
- **Lines**: 555-564
- **Method**: Directly in `settings.php`:
  ```php
  $conf['sentinel_vaillant_key'] = isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : getenv('VAILLANT_API');
  $conf['sentinel_vaillant_endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
  ```
- **Admin Path**: **NONE** (no admin interface)

### Drupal 11
**Admin Interface Available** (NEW feature - not in D7)

- **Admin Path**: `/admin/config/system/sentinel-vaillant-api`
- **Configuration File**: `/var/www/html/sentinel11/sentinel/web/sites/default/settings.php` (lines 879-897)
- **Method**: Multiple options with priority:
  1. Environment variables (same as D7)
  2. Admin configuration form (NEW in D11)
  3. Settings.php override
  4. Default values

## Where to Configure

### Drupal 7 Location:
```
File: /var/www/html/drupal7-project/docroot/sites/default/settings.php
Lines: 555-564

// Set in $conf array:
$conf['sentinel_vaillant_key'] = getenv('VAILLANT_API');
$conf['sentinel_vaillant_endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
```

### Drupal 11 Locations:

#### Option 1: Admin Interface (NEW - Not in D7)
```
URL: /admin/config/system/sentinel-vaillant-api
Menu: Configuration > System > Vaillant API Configuration
Permission: administer site configuration
```

#### Option 2: Settings.php (Same as D7)
```
File: /var/www/html/sentinel11/sentinel/web/sites/default/settings.php
Lines: 879-897

// Set in $config array:
$config['sentinel_systemcheck_vaillant_api.settings']['api_key'] = getenv('VAILLANT_API');
$config['sentinel_systemcheck_vaillant_api.settings']['endpoint'] = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
```

#### Option 3: Environment Variables (Same as D7)
```
Set these environment variables:
- VAILLANT_API (API key)
- VAILLANT_API_ENDPOINT (optional, defaults to production endpoint)
```

## Priority Order (D11 only - D7 only used settings.php)

In Drupal 11, the module checks in this order:
1. **Environment variables** → `VAILLANT_API`, `VAILLANT_API_ENDPOINT`
2. **Configuration system** → Values from admin form or settings.php
3. **Default values** → Endpoint defaults to production URL

This means environment variables always win, just like D7 behavior.