/**
 * Custom CKEditor configuration
 * This file contains custom CKEditor settings
 */

CKEDITOR.editorConfig = function(config) {
  // Custom configuration for CKEditor
  // Add any custom CKEditor settings here
  
  // Example configurations:
  // config.toolbar = 'Basic';
  // config.height = 300;
  // config.resize_enabled = false;
  
  // Custom toolbar configuration
  config.toolbar = [
    { name: 'document', items: [ 'Source', '-', 'NewPage', 'Preview', 'Print', '-', 'Templates' ] },
    { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },
    { name: 'editing', items: [ 'Find', 'Replace', '-', 'SelectAll', '-', 'Scayt' ] },
    { name: 'forms', items: [ 'Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField' ] },
    { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
    { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
    { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
    { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe' ] },
    { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
    { name: 'colors', items: [ 'TextColor', 'BGColor' ] },
    { name: 'tools', items: [ 'Maximize', 'ShowBlocks' ] },
    { name: 'about', items: [ 'About' ] }
  ];
  
  // Disable some features
  config.removePlugins = 'elementspath';
  config.resize_enabled = false;
  
  // Custom styles
  config.stylesSet = [
    { name: 'Heading 1', element: 'h1' },
    { name: 'Heading 2', element: 'h2' },
    { name: 'Heading 3', element: 'h3' },
    { name: 'Heading 4', element: 'h4' },
    { name: 'Heading 5', element: 'h5' },
    { name: 'Heading 6', element: 'h6' },
    { name: 'Paragraph', element: 'p' },
    { name: 'Preformatted', element: 'pre' }
  ];
};


