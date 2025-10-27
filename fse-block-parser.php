<?php
/**
 * Plugin Name: FSE Block Parser
 * Description: WordPress plugin to extract Gutenberg block data from FSE templates as JSON
 * Version: 1.0.0
 * Author: Developer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FSE_Block_Parser {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('fse/v1', '/template/(?P<template>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template_blocks'],
            'permission_callback' => '__return_true',
            'args' => [
                'template' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Template name (e.g., front-page, index, single, etc.)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'include_template_parts' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to resolve and include template parts',
                ],
                'resolve_reusable' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to resolve reusable blocks',
                ],
            ],
        ]);
        
        register_rest_route('fse/v1', '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_available_templates'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Get available FSE templates
     */
    public function get_available_templates() {
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
        
        return rest_ensure_response([
            'success' => true,
            'data' => $templates,
        ]);
    }
    
    /**
     * Get template blocks as JSON
     */
    public function get_template_blocks($request) {
        $template_name = $request->get_param('template');
        $include_template_parts = $request->get_param('include_template_parts');
        $resolve_reusable = $request->get_param('resolve_reusable');
        
        try {
            $template = $this->get_template($template_name);
            
            if (!$template) {
                return new WP_Error('template_not_found', 'Template not found', ['status' => 404]);
            }
            
            $block_parser = new FSE_Block_Data_Parser($include_template_parts, $resolve_reusable);
            $blocks = $block_parser->parse_blocks($template->content);
            
            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'template' => [
                        'slug' => $template->slug,
                        'title' => $template->title,
                        'description' => $template->description,
                    ],
                    'blocks' => $blocks,
                ],
                'meta' => [
                    'total_blocks' => $this->count_blocks($blocks),
                    'template_parts_resolved' => $include_template_parts,
                    'reusable_blocks_resolved' => $resolve_reusable,
                ],
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('parsing_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get template by name
     */
    private function get_template($template_name) {
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
     * Count total blocks recursively
     */
    private function count_blocks($blocks) {
        $count = 0;
        foreach ($blocks as $block) {
            $count++;
            if (!empty($block['innerBlocks'])) {
                $count += $this->count_blocks($block['innerBlocks']);
            }
        }
        return $count;
    }
}

/**
 * Block data parser class
 */
class FSE_Block_Data_Parser {
    
    private $include_template_parts;
    private $resolve_reusable;
    private $processed_template_parts = [];
    private $processed_reusable = [];
    
    public function __construct($include_template_parts = true, $resolve_reusable = true) {
        $this->include_template_parts = $include_template_parts;
        $this->resolve_reusable = $resolve_reusable;
    }
    
    /**
     * Parse blocks from content
     */
    public function parse_blocks($content) {
        $blocks = parse_blocks($content);
        return $this->process_blocks($blocks);
    }
    
    /**
     * Process blocks recursively
     */
    private function process_blocks($blocks) {
        $processed_blocks = [];
        
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue; // Skip empty blocks
            }
            
            $processed_block = $this->process_single_block($block);
            if ($processed_block) {
                $processed_blocks[] = $processed_block;
            }
        }
        
        return $processed_blocks;
    }
    
    /**
     * Process a single block
     */
    private function process_single_block($block) {
        $block_data = [
            'blockName' => $block['blockName'],
            'clientId' => $this->generate_client_id(),
            'attributes' => $this->clean_attributes($block['attrs'] ?? []),
        ];
        
        // Handle different block types
        switch ($block['blockName']) {
            case 'core/template-part':
                if ($this->include_template_parts) {
                    return $this->process_template_part($block_data, $block['attrs'] ?? []);
                }
                break;
                
            case 'core/block':
                if ($this->resolve_reusable) {
                    return $this->process_reusable_block($block_data, $block['attrs'] ?? []);
                }
                break;
                
            default:
                // Handle regular blocks and dynamic blocks
                $block_data = $this->process_regular_block($block_data, $block);
                break;
        }
        
        // Process inner blocks
        if (!empty($block['innerBlocks'])) {
            $block_data['innerBlocks'] = $this->process_blocks($block['innerBlocks']);
        } else {
            $block_data['innerBlocks'] = [];
        }
        
        return $block_data;
    }
    
    /**
     * Process template part block
     */
    private function process_template_part($block_data, $attrs) {
        $slug = $attrs['slug'] ?? '';
        $theme = $attrs['theme'] ?? get_stylesheet();
        
        // Prevent infinite loops
        $part_key = $theme . '/' . $slug;
        if (in_array($part_key, $this->processed_template_parts)) {
            $block_data['error'] = 'Circular reference detected';
            return $block_data;
        }
        
        $this->processed_template_parts[] = $part_key;
        
        // Get template part content
        $template_part = $this->get_template_part($slug, $theme);
        if ($template_part && !empty($template_part->content)) {
            $blocks = parse_blocks($template_part->content);
            $block_data['resolvedBlocks'] = $this->process_blocks($blocks);
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
     */
    private function process_reusable_block($block_data, $attrs) {
        $ref = $attrs['ref'] ?? 0;
        
        // Prevent infinite loops
        if (in_array($ref, $this->processed_reusable)) {
            $block_data['error'] = 'Circular reference detected';
            return $block_data;
        }
        
        $this->processed_reusable[] = $ref;
        
        // Get reusable block content
        $reusable_block = get_post($ref);
        if ($reusable_block && $reusable_block->post_type === 'wp_block') {
            $blocks = parse_blocks($reusable_block->post_content);
            $block_data['resolvedBlocks'] = $this->process_blocks($blocks);
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
     */
    private function process_regular_block($block_data, $block) {
        // Add innerHTML for text content if available
        if (!empty($block['innerHTML']) && trim($block['innerHTML']) !== '') {
            $block_data['innerHTML'] = trim($block['innerHTML']);
        }
        
        // Handle dynamic blocks by checking if they have a render callback
        if ($this->is_dynamic_block($block['blockName'])) {
            $block_data['isDynamic'] = true;
            
            // For dynamic blocks, we might want to include some rendered content
            // but since we want JSON only, we'll include metadata instead
            $block_data['dynamicMetadata'] = $this->get_dynamic_block_metadata($block['blockName'], $block['attrs'] ?? []);
        }
        
        return $block_data;
    }
    
    /**
     * Check if a block is dynamic
     */
    private function is_dynamic_block($block_name) {
        $registry = WP_Block_Type_Registry::get_instance();
        $block_type = $registry->get_registered($block_name);
        
        return $block_type && !empty($block_type->render_callback);
    }
    
    /**
     * Get metadata for dynamic blocks
     */
    private function get_dynamic_block_metadata($block_name, $attrs) {
        $metadata = [
            'blockType' => $block_name,
            'renderCallback' => true,
        ];
        
        // Add specific metadata for common dynamic blocks
        switch ($block_name) {
            case 'core/query':
                $metadata['queryArgs'] = $this->extract_query_args($attrs);
                break;
                
            case 'core/post-content':
            case 'core/post-title':
            case 'core/post-excerpt':
                $metadata['contextRequired'] = 'post';
                break;
                
            case 'core/site-title':
            case 'core/site-tagline':
                $metadata['contextRequired'] = 'site';
                break;
        }
        
        return $metadata;
    }
    
    /**
     * Extract query arguments from query block
     */
    private function extract_query_args($attrs) {
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
            ];
        }
        
        return $query_args;
    }
    
    /**
     * Get template part by slug and theme
     */
    private function get_template_part($slug, $theme) {
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
     */
    private function clean_attributes($attrs) {
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
     * Generate a unique client ID for each block
     */
    private function generate_client_id() {
        return 'block_' . wp_generate_uuid4();
    }
}

// Initialize the plugin
new FSE_Block_Parser();