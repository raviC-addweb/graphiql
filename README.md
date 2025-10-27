# FSE Block Parser - WordPress Plugin

A WordPress plugin that extracts Gutenberg block data from Full Site Editing (FSE) templates and returns it in pure JSON format. Perfect for headless WordPress setups with mobile frontends (React Native, Flutter, etc.).

## Features

- ✅ **Complete Block Parsing**: Extracts all Gutenberg blocks with attributes and inner blocks
- ✅ **Template Part Resolution**: Automatically resolves and includes header, footer, and other template parts
- ✅ **Reusable Block Support**: Resolves reusable blocks and includes their content
- ✅ **Dynamic Block Handling**: Provides metadata for dynamic blocks (Query, Post Title, etc.)
- ✅ **Custom Block Compatible**: Works with any custom blocks
- ✅ **Clean JSON Output**: Mobile-friendly JSON structure without HTML
- ✅ **Error Handling**: Comprehensive error handling and validation
- ✅ **Circular Reference Protection**: Prevents infinite loops in template parts and reusable blocks

## Installation

1. Download the plugin files
2. Upload to your WordPress `wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin

### Files to Upload

```
wp-content/plugins/fse-block-parser/
├── fse-block-parser.php        # Main plugin file
├── example-usage.php           # Usage examples
└── README.md                   # This file
```

## REST API Endpoints

### 1. Get Available Templates

```
GET /wp-json/fse/v1/templates
```

Returns a list of all available FSE templates.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "slug": "front-page",
      "title": "Front Page",
      "description": "Template for the front page",
      "area": "wp_template"
    },
    {
      "slug": "index",
      "title": "Index",
      "description": "Default template",
      "area": "wp_template"
    }
  ]
}
```

### 2. Get Template Block Data

```
GET /wp-json/fse/v1/template/{template-name}
```

**Parameters:**
- `template` (required): Template name (e.g., `front-page`, `index`, `single`)
- `include_template_parts` (optional, default: true): Whether to resolve template parts
- `resolve_reusable` (optional, default: true): Whether to resolve reusable blocks

**Examples:**
```bash
# Get front-page template with all features
GET /wp-json/fse/v1/template/front-page

# Get template without resolving template parts
GET /wp-json/fse/v1/template/front-page?include_template_parts=false

# Get template without resolving reusable blocks
GET /wp-json/fse/v1/template/front-page?resolve_reusable=false
```

## JSON Response Structure

Each block in the response has the following structure:

```json
{
  "blockName": "core/heading",
  "clientId": "block_12345678-1234-5678-9abc-123456789abc",
  "attributes": {
    "level": 1,
    "content": "Welcome to My Site"
  },
  "innerHTML": "<h1>Welcome to My Site</h1>",
  "innerBlocks": [],
  "isDynamic": false
}
```

### Block Properties

- `blockName`: The block type (e.g., `core/heading`, `core/paragraph`)
- `clientId`: Unique identifier for the block instance
- `attributes`: Block attributes (settings, content, etc.)
- `innerHTML`: HTML content (for static blocks)
- `innerBlocks`: Array of nested blocks
- `isDynamic`: Boolean indicating if the block is dynamic
- `dynamicMetadata`: Metadata for dynamic blocks (query args, context requirements)

### Template Parts

When template parts are resolved, they include:

```json
{
  "blockName": "core/template-part",
  "attributes": {
    "slug": "header",
    "theme": "my-theme"
  },
  "resolvedBlocks": [
    // Array of blocks from the template part
  ],
  "templatePart": {
    "slug": "header",
    "theme": "my-theme",
    "title": "Header",
    "area": "header"
  }
}
```

### Reusable Blocks

When reusable blocks are resolved:

```json
{
  "blockName": "core/block",
  "attributes": {
    "ref": 123
  },
  "resolvedBlocks": [
    // Array of blocks from the reusable block
  ],
  "reusableBlock": {
    "id": 123,
    "title": "Two Column Layout",
    "slug": "two-column-layout"
  }
}
```

## Usage Examples

### Basic PHP Usage

```php
// Fetch template blocks
$response = wp_remote_get(home_url('/wp-json/fse/v1/template/front-page'));
$data = json_decode(wp_remote_retrieve_body($response), true);

if ($data['success']) {
    $blocks = $data['data']['blocks'];
    // Process blocks for your application
}
```

### React Native Example

```javascript
const fetchTemplateBlocks = async (templateName) => {
  try {
    const response = await fetch(
      `https://yoursite.com/wp-json/fse/v1/template/${templateName}`
    );
    const data = await response.json();
    
    if (data.success) {
      return data.data.blocks;
    }
  } catch (error) {
    console.error('Error fetching blocks:', error);
  }
  return [];
};

// Usage
const blocks = await fetchTemplateBlocks('front-page');
```

### Flutter Example

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

Future<List<dynamic>> fetchTemplateBlocks(String templateName) async {
  try {
    final response = await http.get(
      Uri.parse('https://yoursite.com/wp-json/fse/v1/template/$templateName'),
      headers: {'Accept': 'application/json'},
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return data['data']['blocks'];
      }
    }
  } catch (e) {
    print('Error fetching blocks: $e');
  }
  return [];
}
```

## Block Type Handling

### Static Blocks
- `core/heading`: Includes level and content
- `core/paragraph`: Includes text content
- `core/image`: Includes URL, alt text, and dimensions
- `core/button`: Includes text, URL, and styling

### Dynamic Blocks
- `core/query`: Includes query parameters and post type
- `core/post-title`: Marked as requiring post context
- `core/post-content`: Marked as requiring post context
- `core/site-title`: Marked as requiring site context

### Layout Blocks
- `core/group`: Container with layout settings
- `core/columns`: Column layout with inner column blocks
- `core/cover`: Cover block with background and content

## Error Handling

The API returns structured error responses:

```json
{
  "success": false,
  "code": "template_not_found",
  "message": "Template not found",
  "data": {
    "status": 404
  }
}
```

Common error codes:
- `template_not_found`: Template doesn't exist
- `parsing_error`: Error parsing block content
- `rest_forbidden`: Permission denied

## Performance Considerations

1. **Caching**: Consider caching API responses since template content doesn't change frequently
2. **Template Parts**: Disable template part resolution if not needed to improve performance
3. **Reusable Blocks**: Disable reusable block resolution if not needed
4. **Pagination**: For large templates, consider implementing pagination

## Security

- The plugin uses `__return_true` for permission callbacks, making endpoints publicly accessible
- This is suitable for headless setups where template data is not sensitive
- For private sites, modify the permission callbacks to check user capabilities

## Customization

### Adding Custom Block Support

The plugin automatically handles custom blocks. For special processing, extend the `process_regular_block` method:

```php
private function process_regular_block($block_data, $block) {
    // Your custom block handling
    if ($block['blockName'] === 'my-plugin/custom-block') {
        $block_data['customData'] = $this->process_custom_block($block['attrs']);
    }
    
    return parent::process_regular_block($block_data, $block);
}
```

### Filtering Output

Add WordPress filters to modify the output:

```php
add_filter('fse_block_parser_block_data', function($block_data, $block) {
    // Modify block data before returning
    return $block_data;
}, 10, 2);
```

## Troubleshooting

### Template Not Found
- Verify the template exists in your active theme
- Check if it's a custom template stored in the database
- Ensure the template slug matches exactly

### Empty Blocks Array
- Check if the template has any blocks
- Verify the template content is not corrupted
- Enable WordPress debug logging to see detailed errors

### Performance Issues
- Disable template part resolution for simple use cases
- Implement caching in your application
- Consider limiting the number of resolved reusable blocks

## Contributing

This plugin is designed to be extended and customized for specific use cases. Common improvements might include:

- Additional dynamic block metadata extraction
- Custom block type processors
- Performance optimizations
- Extended error handling
- Webhook notifications for template changes

## License

This plugin is provided as-is for educational and development purposes. Feel free to modify and use in your projects.

## Changelog

### Version 1.0.0
- Initial release
- Basic template parsing
- Template part resolution
- Reusable block support
- Dynamic block metadata
- REST API endpoints
- Error handling and validation