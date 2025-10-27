<?php
/**
 * Standalone FSE Block Parser Function
 * Extract Gutenberg block data from FSE templates as JSON
 */

/**
 * Main function to get template blocks as JSON
 *
 * @param string $template_name Template name (e.g., 'front-page', 'index', 'single')
 * @param bool $include_template_parts Whether to resolve template parts (default: true)
 * @param bool $resolve_reusable Whether to resolve reusable blocks (default: true)
 * @return array|false Array of blocks or false on error
 */
function get_fse_template_blocks($template_name, $include_template_parts = true, $resolve_reusable = true) {
    try {
        // Get template content
        $template = get_fse_template($template_name);
        
        if (!$template) {
            return false;
        }
        
        // Parse blocks
        $blocks = parse_blocks($template->content);
        $processed_blocks = process_fse_blocks($blocks, $include_template_parts, $resolve_reusable);
        
        return [
            'template' => [
                'slug' => $template->slug,
                'title' => $template->title,
                'description' => $template->description ?? '',
            ],
            'blocks' => $processed_blocks,
            'meta' => [
                'total_blocks' => count_fse_blocks($processed_blocks),
                'template_parts_resolved' => $include_template_parts,
                'reusable_blocks_resolved' => $resolve_reusable,
            ],
        ];
        
    } catch (Exception $e) {
        error_log('FSE Block Parser Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get template by name
 *
 * @param string $template_name Template name
 * @return object|null Template object or null if not found
 */
function get_fse_template($template_name) {
    // Try to get from theme first
    $templates = wp_get_theme()->get_block_templates();
    foreach ($templates as $template) {
        if ($template->slug === $template_name) {
            return $template;
        }
    }
    
    // Try to get from database
    $template_post = get_posts([
        'post_type' => 'wp_template',
        'name' => $template_name,
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);
    
    if (!empty($template_post)) {
        $post = $template_post[0];
        return (object) [
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'description' => $post->post_excerpt,
            'content' => $post->post_content,
        ];
    }
    
    return null;
}

/**
 * Process blocks recursively
 *
 * @param array $blocks Raw blocks from parse_blocks()
 * @param bool $include_template_parts Whether to resolve template parts
 * @param bool $resolve_reusable Whether to resolve reusable blocks
 * @param array $processed_template_parts Tracking array for circular references
 * @param array $processed_reusable Tracking array for circular references
 * @return array Processed blocks
 */
function process_fse_blocks($blocks, $include_template_parts = true, $resolve_reusable = true, &$processed_template_parts = [], &$processed_reusable = []) {
    $processed_blocks = [];
    
    foreach ($blocks as $block) {
        if (empty($block['blockName'])) {
            continue; // Skip empty blocks
        }
        
        $processed_block = process_single_fse_block($block, $include_template_parts, $resolve_reusable, $processed_template_parts, $processed_reusable);
        if ($processed_block) {
            $processed_blocks[] = $processed_block;
        }
    }
    
    return $processed_blocks;
}

/**
 * Process a single block
 *
 * @param array $block Block data
 * @param bool $include_template_parts Whether to resolve template parts
 * @param bool $resolve_reusable Whether to resolve reusable blocks
 * @param array $processed_template_parts Tracking array for circular references
 * @param array $processed_reusable Tracking array for circular references
 * @return array|null Processed block or null
 */
function process_single_fse_block($block, $include_template_parts, $resolve_reusable, &$processed_template_parts, &$processed_reusable) {
    $block_data = [
        'blockName' => $block['blockName'],
        'clientId' => 'block_' . wp_generate_uuid4(),
        'attributes' => clean_fse_block_attributes($block['attrs'] ?? []),
    ];
    
    // Handle different block types
    switch ($block['blockName']) {
        case 'core/template-part':
            if ($include_template_parts) {
                return process_fse_template_part($block_data, $block['attrs'] ?? [], $processed_template_parts);
            }
            break;
            
        case 'core/block':
            if ($resolve_reusable) {
                return process_fse_reusable_block($block_data, $block['attrs'] ?? [], $processed_reusable);
            }
            break;
            
        default:
            // Handle regular blocks and dynamic blocks
            $block_data = process_fse_regular_block($block_data, $block);
            break;
    }
    
    // Process inner blocks
    if (!empty($block['innerBlocks'])) {
        $block_data['innerBlocks'] = process_fse_blocks($block['innerBlocks'], $include_template_parts, $resolve_reusable, $processed_template_parts, $processed_reusable);
    } else {
        $block_data['innerBlocks'] = [];
    }
    
    return $block_data;
}

/**
 * Process template part block
 *
 * @param array $block_data Block data
 * @param array $attrs Block attributes
 * @param array $processed_template_parts Tracking array for circular references
 * @return array Processed template part block
 */
function process_fse_template_part($block_data, $attrs, &$processed_template_parts) {
    $slug = $attrs['slug'] ?? '';
    $theme = $attrs['theme'] ?? get_stylesheet();
    
    // Prevent infinite loops
    $part_key = $theme . '/' . $slug;
    if (in_array($part_key, $processed_template_parts)) {
        $block_data['error'] = 'Circular reference detected';
        return $block_data;
    }
    
    $processed_template_parts[] = $part_key;
    
    // Get template part content
    $template_part = get_fse_template_part($slug, $theme);
    if ($template_part && !empty($template_part->content)) {
        $blocks = parse_blocks($template_part->content);
        $block_data['resolvedBlocks'] = process_fse_blocks($blocks, true, true, $processed_template_parts, []);
        $block_data['templatePart'] = [
            'slug' => $slug,
            'theme' => $theme,
            'title' => $template_part->title ?? '',
            'area' => $template_part->area ?? '',
        ];
    } else {
        $block_data['error'] = 'Template part not found: ' . $slug;
    }
    
    return $block_data;
}

/**
 * Process reusable block
 *
 * @param array $block_data Block data
 * @param array $attrs Block attributes
 * @param array $processed_reusable Tracking array for circular references
 * @return array Processed reusable block
 */
function process_fse_reusable_block($block_data, $attrs, &$processed_reusable) {
    $ref = $attrs['ref'] ?? 0;
    
    // Prevent infinite loops
    if (in_array($ref, $processed_reusable)) {
        $block_data['error'] = 'Circular reference detected';
        return $block_data;
    }
    
    $processed_reusable[] = $ref;
    
    // Get reusable block content
    $reusable_block = get_post($ref);
    if ($reusable_block && $reusable_block->post_type === 'wp_block') {
        $blocks = parse_blocks($reusable_block->post_content);
        $block_data['resolvedBlocks'] = process_fse_blocks($blocks, true, true, [], $processed_reusable);
        $block_data['reusableBlock'] = [
            'id' => $ref,
            'title' => $reusable_block->post_title,
            'slug' => $reusable_block->post_name,
        ];
    } else {
        $block_data['error'] = 'Reusable block not found: ' . $ref;
    }
    
    return $block_data;
}

/**
 * Process regular block (including dynamic blocks)
 *
 * @param array $block_data Block data
 * @param array $block Raw block data
 * @return array Processed block data
 */
function process_fse_regular_block($block_data, $block) {
    // Add innerHTML for text content if available
    if (!empty($block['innerHTML']) && trim($block['innerHTML']) !== '') {
        $block_data['innerHTML'] = trim($block['innerHTML']);
    }
    
    // Handle dynamic blocks
    if (is_fse_dynamic_block($block['blockName'])) {
        $block_data['isDynamic'] = true;
        $block_data['dynamicMetadata'] = get_fse_dynamic_block_metadata($block['blockName'], $block['attrs'] ?? []);
    }
    
    return $block_data;
}

/**
 * Check if a block is dynamic
 *
 * @param string $block_name Block name
 * @return bool True if dynamic block
 */
function is_fse_dynamic_block($block_name) {
    $registry = WP_Block_Type_Registry::get_instance();
    $block_type = $registry->get_registered($block_name);
    
    return $block_type && !empty($block_type->render_callback);
}

/**
 * Get metadata for dynamic blocks
 *
 * @param string $block_name Block name
 * @param array $attrs Block attributes
 * @return array Dynamic block metadata
 */
function get_fse_dynamic_block_metadata($block_name, $attrs) {
    $metadata = [
        'blockType' => $block_name,
        'renderCallback' => true,
    ];
    
    // Add specific metadata for common dynamic blocks
    switch ($block_name) {
        case 'core/query':
            $metadata['queryArgs'] = extract_fse_query_args($attrs);
            break;
            
        case 'core/post-content':
        case 'core/post-title':
        case 'core/post-excerpt':
        case 'core/post-date':
        case 'core/post-author':
            $metadata['contextRequired'] = 'post';
            break;
            
        case 'core/site-title':
        case 'core/site-tagline':
        case 'core/site-logo':
            $metadata['contextRequired'] = 'site';
            break;
            
        case 'core/navigation':
            $metadata['contextRequired'] = 'navigation';
            break;
    }
    
    return $metadata;
}

/**
 * Extract query arguments from query block
 *
 * @param array $attrs Block attributes
 * @return array Query arguments
 */
function extract_fse_query_args($attrs) {
    $query_args = [];
    
    if (isset($attrs['query'])) {
        $query = $attrs['query'];
        $query_args = [
            'postType' => $query['postType'] ?? 'post',
            'perPage' => $query['perPage'] ?? 10,
            'offset' => $query['offset'] ?? 0,
            'order' => $query['order'] ?? 'desc',
            'orderBy' => $query['orderBy'] ?? 'date',
            'categoryIds' => $query['categoryIds'] ?? [],
            'tagIds' => $query['tagIds'] ?? [],
            'sticky' => $query['sticky'] ?? '',
            'inherit' => $query['inherit'] ?? false,
        ];
    }
    
    return $query_args;
}

/**
 * Get template part by slug and theme
 *
 * @param string $slug Template part slug
 * @param string $theme Theme name
 * @return object|null Template part object or null
 */
function get_fse_template_part($slug, $theme) {
    // Try to get from theme files first
    $template_parts = wp_get_theme()->get_block_template_parts();
    foreach ($template_parts as $part) {
        if ($part->slug === $slug) {
            return $part;
        }
    }
    
    // Try to get from database
    $template_part = get_posts([
        'post_type' => 'wp_template_part',
        'name' => $slug,
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);
    
    if (!empty($template_part)) {
        $post = $template_part[0];
        return (object) [
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'area' => get_post_meta($post->ID, 'area', true),
        ];
    }
    
    return null;
}

/**
 * Clean and format block attributes
 *
 * @param array $attrs Block attributes
 * @return array Cleaned attributes
 */
function clean_fse_block_attributes($attrs) {
    if (empty($attrs)) {
        return [];
    }
    
    // Remove WordPress-specific attributes that aren't useful for frontend
    $cleaned = $attrs;
    unset($cleaned['lock']);
    unset($cleaned['metadata']);
    
    // Convert any object attributes to arrays for better JSON compatibility
    array_walk_recursive($cleaned, function(&$value) {
        if (is_object($value)) {
            $value = (array) $value;
        }
    });
    
    return $cleaned;
}

/**
 * Count total blocks recursively
 *
 * @param array $blocks Blocks array
 * @return int Total block count
 */
function count_fse_blocks($blocks) {
    $count = 0;
    foreach ($blocks as $block) {
        $count++;
        if (!empty($block['innerBlocks'])) {
            $count += count_fse_blocks($block['innerBlocks']);
        }
        if (!empty($block['resolvedBlocks'])) {
            $count += count_fse_blocks($block['resolvedBlocks']);
        }
    }
    return $count;
}

/**
 * Get all available FSE templates
 *
 * @return array Array of available templates
 */
function get_fse_available_templates() {
    $templates = [];
    
    // Get theme templates
    $theme_templates = wp_get_theme()->get_block_templates();
    foreach ($theme_templates as $template) {
        $templates[] = [
            'slug' => $template->slug,
            'title' => $template->title,
            'description' => $template->description,
            'area' => $template->area ?? 'wp_template',
        ];
    }
    
    // Get custom templates from database
    $custom_templates = get_posts([
        'post_type' => 'wp_template',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
    
    foreach ($custom_templates as $template) {
        $templates[] = [
            'slug' => $template->post_name,
            'title' => $template->post_title,
            'description' => $template->post_excerpt,
            'area' => 'wp_template',
            'custom' => true,
        ];
    }
    
    return $templates;
}

// Usage Examples:

/*
// Basic usage - get front-page template blocks
$blocks = get_fse_template_blocks('front-page');
if ($blocks) {
    echo json_encode($blocks, JSON_PRETTY_PRINT);
}

// Without template parts resolution (faster)
$blocks = get_fse_template_blocks('front-page', false, true);

// Without reusable block resolution
$blocks = get_fse_template_blocks('front-page', true, false);

// Get all available templates
$templates = get_fse_available_templates();
echo json_encode($templates, JSON_PRETTY_PRINT);

// Error handling example
$blocks = get_fse_template_blocks('non-existent-template');
if ($blocks === false) {
    echo json_encode(['error' => 'Template not found']);
} else {
    echo json_encode($blocks);
}
*/