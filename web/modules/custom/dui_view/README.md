# DUI View Module

This module generates a list of data about modules installed on the site. It's used by the `dui_controller` module to collect site information.

## Features

- **Module List**: Returns JSON list of all installed modules
- **Security**: HMAC key-based authentication
- **Encryption**: Encrypts JSON output before sending
- **Configuration**: Admin form to set security keys

## Key Functionality

### Module List Endpoint
- **Path**: `/dui-view/module-list/{key}`
- **Authentication**: HMAC-based key verification
- **Response**: Encrypted JSON containing:
  - Site name
  - Drupal core version
  - Module list (name, version, status)
  - System configuration data

### Security
- **HMAC Authentication**: Uses SHA-256 HMAC for key verification
- **Encryption**: Encrypts JSON data using OpenSSL
- **Key Management**: Two keys (public and private)
  - Public key: Used for HMAC authentication
  - Private key: Used for data encryption

### Configuration
- **Admin Form**: `/admin/config/system/dui-view`
- **Settings**: 
  - Public site key
  - Private site key
- **Permissions**: Requires `administer site configuration`

## Files Structure

```
dui_view/
├── dui_view.info.yml                     # Module definition
├── dui_view.routing.yml                   # Route definitions
├── dui_view.module                        # Module hooks
├── config/
│   └── install/
│       ├── dui_view.settings.yml          # Default configuration
│       └── system.menu.admin.yml          # Admin menu link
├── src/
│   ├── Controller/
│   │   └── DuiViewController.php          # Controller for endpoints
│   └── Form/
│       └── DuiViewConfigForm.php          # Configuration form
└── README.md                              # Documentation
```

## Dependencies

- **Core**: Drupal 11 core
- **No external dependencies**: Uses only core functionality

## Installation

1. Place the module in `/web/modules/custom/dui_view/`
2. Enable the module: `drush en dui_view`
3. Configure keys at `/admin/config/system/dui-view`

## Usage

### Configuration

1. Navigate to `/admin/config/system/dui-view`
2. Enter your public site key
3. Enter your private site key
4. Click "Save"

### API Access

Generate HMAC hash:
```php
$hmac = hash_hmac('sha256', $site_key, $site_key_private);
```

Access the endpoint:
```
GET /dui-view/module-list/{hmac}
```

### Response Format

The response is encrypted JSON containing:
```json
{
  "sitename": "Site Name",
  "core": "11.1.0",
  "modules": {
    "module_name": {
      "name": "Module Name",
      "version": "1.0.0",
      "status": "enabled"
    }
  },
  "data": {
    "preprocess_css": 1,
    "preprocess_js": 1,
    "page_compression": 1,
    "cache": 1,
    "block_cache": 1,
    "clean_url": 1,
    "cron_last": 1234567890,
    "error_level": 0
  }
}
```

## Technical Details

### Drupal 7 to 11 Conversion

#### Key Changes:
- **Routing**: `hook_menu()` → `.routing.yml`
- **Forms**: Form callback → `ConfigFormBase`
- **Access**: Access arguments → `AccessResult`
- **Config**: `variable_get/set()` → Configuration API
- **Encryption**: `mcrypt` (deprecated) → `openssl`
- **Module Data**: `system_rebuild_module_data()` → Extension discovery service
- **Response**: Custom delivery → `Response` object

#### Service Integration:
- **Extension Discovery**: Uses `extension.list.module` service
- **Module Handler**: Uses `module_handler` service
- **State API**: Uses state API for cron_last
- **Configuration API**: Uses config API for settings

### Security Features

#### HMAC Authentication
- Uses SHA-256 HMAC for key verification
- Key generated from site_key and site_key_private
- Prevents unauthorized access

#### Encryption
- Uses OpenSSL for encryption (AES-256-CBC)
- Base64 encoded and URL-safe
- Replaces special characters with safe alternatives

### Module Filtering

Filters out:
- Hidden modules
- Multi-project modules (only returns main project)
- Core modules
- Inactive modules

## Migration Notes

### From Drupal 7

#### Functionality Preserved:
- Same endpoint path structure
- Same HMAC authentication
- Same encryption (converted from mcrypt to OpenSSL)
- Same configuration form structure
- Same response format

#### Improvements:
- Better encryption algorithm (OpenSSL)
- Modern configuration API
- Proper dependency injection
- Service-based architecture
- Better security practices

## Security Considerations

### Authentication
- **HMAC Verification**: Strong key-based auth
- **Key Storage**: Stored in configuration system
- **Access Control**: Proper permission checks

### Data Protection
- **Encryption**: All data encrypted before transmission
- **No Plain Text**: JSON never sent in plain text
- **Key Separation**: Public and private keys separated

## Performance

### Caching
- **Module List**: Uses D11's extension discovery
- **Configuration**: Cached configuration API
- **Efficient**: Only processes active modules

### Optimization
- **Lazy Loading**: Only loads when accessed
- **Minimal Impact**: No database queries on list
- **Fast Response**: Quick JSON encoding

## Troubleshooting

### Common Issues

1. **Access Denied**: Check HMAC key calculation
2. **Invalid Key**: Verify keys in configuration
3. **Decryption Failed**: Check private key matches

### Debugging

- Check `dui_view` log entries for site data
- Verify HMAC calculation matches endpoint
- Test encryption/decryption separately
- Check OpenSSL extension is enabled

## Related Modules

- **dui_controller**: Consumes the JSON output from this module

## Future Enhancements

- **Additional Metrics**: More system statistics
- **User Data**: User count and statistics
- **Content Data**: Node count and statistics
- **Performance Metrics**: Additional performance data
- **Security Enhancements**: Additional security features


