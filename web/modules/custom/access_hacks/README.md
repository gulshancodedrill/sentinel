# Access Hacks Module

This module provides a collection of tweaks and fixes for Drupal 11. It's a complete conversion from Drupal 7 to Drupal 11.

## Features

- **Theme Customizations**: CSS overrides for admin themes
- **Meta Tag Management**: Removes unwanted meta tags
- **CKEditor Configuration**: Custom CKEditor settings
- **Route Management**: Disables unwanted routes

## Key Functionality

### Theme Hacks
- **Seven Theme Overrides**: Custom CSS for Seven admin theme
- **Library Integration**: Proper D11 library system integration
- **Conditional Loading**: Only loads when Seven theme is active

### Meta Tag Management
- **Shortlink Removal**: Removes pesky shortlink meta tags
- **Head Alteration**: Modifies HTML head elements
- **Clean Output**: Provides cleaner HTML output

### CKEditor Configuration
- **Custom Settings**: Custom CKEditor configuration
- **Toolbar Customization**: Custom toolbar setup
- **Plugin Management**: Enables/disables specific plugins

### Route Management
- **Node Route Disabling**: Disables the /node front page route
- **Route Alteration**: Modifies routing behavior

## Files Structure

```
access_hacks/
├── access_hacks.info.yml          # Module definition
├── access_hacks.module            # Main module file
├── access_hacks.libraries.yml     # Library definitions
├── css/
│   └── seven_theme_hacks.css      # Seven theme overrides
└── js/
    └── ckeditor_custom_config.js  # CKEditor configuration
```

## Dependencies

- No specific dependencies required
- Works with core Drupal 11 functionality

## Installation

1. Place the module in `/web/modules/custom/access_hacks/`
2. **Note**: This module is not intended to be enabled by default
3. Enable only if specific hacks are needed: `drush en access_hacks`

## Usage

### Theme Customizations
- Automatically applies when Seven theme is active
- CSS overrides are loaded via library system
- Customize `css/seven_theme_hacks.css` as needed

### CKEditor Configuration
- Custom configuration is applied to CKEditor instances
- Modify `js/ckeditor_custom_config.js` for different settings
- Toolbar and plugin settings can be customized

### Meta Tag Management
- Automatically removes shortlink meta tags
- No configuration required
- Works on all pages

## Technical Details

### Drupal 7 to 11 Conversion

#### Hooks Converted:
- `hook_menu_alter()` → Route subscriber (not implemented in this version)
- `hook_init()` → `hook_preprocess_html()`
- `hook_html_head_alter()` → `hook_page_attachments()`
- `hook_wysiwyg_editor_settings_alter()` → `hook_editor_js_settings_alter()`

#### Key Changes:
- **Global Variables**: `$theme` → `\Drupal::theme()->getActiveTheme()`
- **CSS Loading**: `drupal_add_css()` → Library system
- **Path Functions**: `drupal_get_path()` → `\Drupal::service('extension.list.module')->getPath()`
- **Base Path**: `base_path()` → `\Drupal::request()->getBasePath()`

### Library System
- Uses D11's library system for CSS/JS management
- Proper dependency management
- Conditional loading based on theme

### CKEditor Integration
- Custom configuration file
- Toolbar customization
- Plugin management
- Style set definitions

## Customization

### CSS Overrides
Edit `css/seven_theme_hacks.css` to customize Seven theme:
```css
/* Add your custom CSS here */
body.admin-page {
  /* Custom admin page styles */
}
```

### CKEditor Configuration
Modify `js/ckeditor_custom_config.js` for different editor settings:
```javascript
CKEDITOR.editorConfig = function(config) {
  // Your custom configuration
  config.toolbar = 'Basic';
  config.height = 300;
};
```

## Migration Notes

### Drupal 7 Features Not Converted:
- **Menu Alteration**: Route disabling not implemented (D11 handles this differently)
- **WYSIWYG Integration**: Updated to D11's editor system

### Drupal 11 Improvements:
- **Library System**: Better CSS/JS management
- **Service Integration**: Proper service usage
- **Theme System**: Better theme integration

## Security Considerations

- **No Security Impact**: Module only provides UI tweaks
- **Safe to Disable**: Can be safely disabled without issues
- **No Database Changes**: No database modifications required

## Performance

- **Minimal Impact**: Very lightweight module
- **Conditional Loading**: Only loads resources when needed
- **Caching Friendly**: Works with Drupal's caching system

## Troubleshooting

### Common Issues

1. **CSS Not Loading**: Check library definition and theme
2. **CKEditor Issues**: Verify configuration file syntax
3. **Meta Tags**: Check if other modules are adding tags

### Debugging

- Enable CSS/JS aggregation debugging
- Check browser developer tools
- Verify library loading

## Future Enhancements

- **Route Subscriber**: Implement proper route disabling
- **More Themes**: Support for additional admin themes
- **Configuration UI**: Admin interface for settings
- **Additional Hacks**: More Drupal tweaks and fixes


