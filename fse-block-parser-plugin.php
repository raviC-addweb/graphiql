<?php
/**
 * Plugin Name: FSE Block Parser
 * Plugin URI: https://github.com/your-username/fse-block-parser
 * Description: Extract Gutenberg block data from Full Site Editing (FSE) templates in pure JSON format. Perfect for headless WordPress with mobile frontends.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fse-block-parser
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FSE_BLOCK_PARSER_VERSION', '1.0.0');
define('FSE_BLOCK_PARSER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FSE_BLOCK_PARSER_PLUGIN_PATH', plugin_dir_path(__FILE__));

class FSE_Block_Parser_Plugin {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('fse-block-parser', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if we're in a block theme
        if (!wp_is_block_theme()) {
            add_action('admin_notices', [$this, 'block_theme_notice']);
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Get all available templates
        register_rest_route('fse-parser/v1', '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_available_templates'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Get specific template blocks
        register_rest_route('fse-parser/v1', '/template/(?P<template>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template_blocks'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'template' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Template name (e.g., front-page, index, single)',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_template_name'],
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
                'format' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'standard',
                    'enum' => ['standard', 'mobile', 'minimal'],
                    'description' => 'Output format for different use cases',
                ],
            ],
        ]);
        
        // Get template hierarchy
        register_rest_route('fse-parser/v1', '/template-hierarchy', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template_hierarchy'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }
    
    /**
     * Check permissions for API access
     */
    public function check_permissions() {
        // Allow public access by default for headless setups
        // Override this in a child plugin or filter for custom permissions
        return apply_filters('fse_block_parser_permissions', true);
    }
    
    /**
     * Validate template name
     */
    public function validate_template_name($param, $request, $key) {
        return preg_match('/^[a-zA-Z0-9-_]+$/', $param);
    }
    
    /**
     * Get all available templates
     */
    public function get_available_templates($request) {
        try {
            $templates = $this->get_fse_available_templates();
            
            return rest_ensure_response([
                'success' => true,
                'data' => $templates,
                'meta' => [
                    'total' => count($templates),
                    'block_theme' => wp_is_block_theme(),
                ],
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('template_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get template blocks
     */
    public function get_template_blocks($request) {
        $template_name = $request->get_param('template');
        $include_template_parts = $request->get_param('include_template_parts');
        $resolve_reusable = $request->get_param('resolve_reusable');
        $format = $request->get_param('format');
        
        try {
            $result = $this->get_fse_template_blocks($template_name, $include_template_parts, $resolve_reusable);
            
            if ($result === false) {
                return new WP_Error('template_not_found', 'Template not found: ' . $template_name, ['status' => 404]);
            }
            
            // Format output based on request
            if ($format === 'mobile') {
                $result['blocks'] = $this->format_for_mobile($result['blocks']);
            } elseif ($format === 'minimal') {
                $result['blocks'] = $this->format_minimal($result['blocks']);
            }
            
            return rest_ensure_response([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'format' => $format,
                    'generated_at' => current_time('mysql'),
                    'cache_key' => md5($template_name . $include_template_parts . $resolve_reusable),
                ],
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('parsing_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get template hierarchy information
     */
    public function get_template_hierarchy($request) {
        $hierarchy = [
            'theme_templates' => [],
            'custom_templates' => [],
            'template_parts' => [],
        ];
        
        // Get theme templates
        if (function_exists('wp_get_theme')) {
            $theme_templates = wp_get_theme()->get_block_templates();
            foreach ($theme_templates as $template) {
                $hierarchy['theme_templates'][] = [
                    'slug' => $template->slug,
                    'title' => $template->title,
                    'description' => $template->description,
                    'area' => $template->area ?? 'wp_template',
                    'source' => 'theme',
                ];
            }
        }
        
        // Get custom templates
        $custom_templates = get_posts([
            'post_type' => 'wp_template',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);
        
        foreach ($custom_templates as $template) {
            $hierarchy['custom_templates'][] = [
                'slug' => $template->post_name,
                'title' => $template->post_title,
                'description' => $template->post_excerpt,
                'area' => 'wp_template',
                'source' => 'custom',
                'id' => $template->ID,
            ];
        }
        
        // Get template parts
        if (function_exists('wp_get_theme')) {
            $template_parts = wp_get_theme()->get_block_template_parts();
            foreach ($template_parts as $part) {
                $hierarchy['template_parts'][] = [
                    'slug' => $part->slug,
                    'title' => $part->title,
                    'area' => $part->area ?? '',
                    'source' => 'theme',
                ];
            }
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $hierarchy,
        ]);
    }
    
    /**
     * Format blocks for mobile consumption
     */
    private function format_for_mobile($blocks) {
        $mobile_blocks = [];
        
        foreach ($blocks as $block) {
            $mobile_block = [
                'type' => $block['blockName'],
                'id' => $block['clientId'],
                'props' => $block['attributes'],
            ];
            
            // Extract content based on block type
            switch ($block['blockName']) {
                case 'core/heading':
                    $mobile_block['content'] = strip_tags($block['innerHTML'] ?? '');
                    $mobile_block['level'] = $block['attributes']['level'] ?? 1;
                    break;
                    
                case 'core/paragraph':
                    $mobile_block['content'] = strip_tags($block['innerHTML'] ?? '');
                    break;
                    
                case 'core/image':
                    $mobile_block['src'] = $block['attributes']['url'] ?? '';
                    $mobile_block['alt'] = $block['attributes']['alt'] ?? '';
                    $mobile_block['caption'] = $block['attributes']['caption'] ?? '';
                    break;
                    
                case 'core/button':
                    $mobile_block['text'] = $block['attributes']['text'] ?? '';
                    $mobile_block['url'] = $block['attributes']['url'] ?? '';
                    break;
                    
                case 'core/query':
                    if (isset($block['dynamicMetadata']['queryArgs'])) {
                        $mobile_block['query'] = $block['dynamicMetadata']['queryArgs'];
                    }
                    break;
            }
            
            // Process children
            if (!empty($block['innerBlocks'])) {
                $mobile_block['children'] = $this->format_for_mobile($block['innerBlocks']);
            }
            
            if (!empty($block['resolvedBlocks'])) {
                $mobile_block['resolvedChildren'] = $this->format_for_mobile($block['resolvedBlocks']);
            }
            
            $mobile_blocks[] = $mobile_block;
        }
        
        return $mobile_blocks;
    }
    
    /**
     * Format blocks in minimal format
     */
    private function format_minimal($blocks) {
        $minimal_blocks = [];
        
        foreach ($blocks as $block) {
            $minimal_block = [
                'type' => $block['blockName'],
                'attrs' => $block['attributes'],
            ];
            
            if (!empty($block['innerHTML'])) {
                $minimal_block['content'] = strip_tags($block['innerHTML']);
            }
            
            if (!empty($block['innerBlocks'])) {
                $minimal_block['inner'] = $this->format_minimal($block['innerBlocks']);
            }
            
            if (!empty($block['resolvedBlocks'])) {
                $minimal_block['resolved'] = $this->format_minimal($block['resolvedBlocks']);
            }
            
            $minimal_blocks[] = $minimal_block;
        }
        
        return $minimal_blocks;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.9', '<')) {
            wp_die('This plugin requires WordPress 5.9 or higher.');
        }
        
        // Check if theme supports blocks
        if (!wp_is_block_theme()) {
            // Don't prevent activation, just show notice later
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Show notice if not using a block theme
     */
    public function block_theme_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('FSE Block Parser:', 'fse-block-parser'); ?></strong>
                <?php _e('This plugin works best with block themes. Your current theme may have limited FSE template support.', 'fse-block-parser'); ?>
            </p>
        </div>
        <?php
    }
    
    // Include all the parsing functions from the standalone version
    
    /**
     * Main function to get template blocks as JSON
     */
    public function get_fse_template_blocks($template_name, $include_template_parts = true, $resolve_reusable = true) {
        try {
            $template = $this->get_fse_template($template_name);
            
            if (!$template) {
                return false;
            }
            
            $blocks = parse_blocks($template->content);
            $processed_blocks = $this->process_fse_blocks($blocks, $include_template_parts, $resolve_reusable);
            
            return [
                'template' => [
                    'slug' => $template->slug,
                    'title' => $template->title,
                    'description' => $template->description ?? '',
                ],
                'blocks' => $processed_blocks,
                'meta' => [
                    'total_blocks' => $this->count_fse_blocks($processed_blocks),
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
     */
    private function get_fse_template($template_name) {
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
     */
    private function process_fse_blocks($blocks, $include_template_parts = true, $resolve_reusable = true, &$processed_template_parts = [], &$processed_reusable = []) {
        $processed_blocks = [];
        
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }
            
            $processed_block = $this->process_single_fse_block($block, $include_template_parts, $resolve_reusable, $processed_template_parts, $processed_reusable);
            if ($processed_block) {
                $processed_blocks[] = $processed_block;
            }
        }
        
        return $processed_blocks;
    }
    
    /**
     * Process a single block
     */
    private function process_single_fse_block($block, $include_template_parts, $resolve_reusable, &$processed_template_parts, &$processed_reusable) {
        $block_data = [
            'blockName' => $block['blockName'],
            'clientId' => 'block_' . wp_generate_uuid4(),
            'attributes' => $this->clean_fse_block_attributes($block['attrs'] ?? []),
        ];
        
        // Handle different block types
        switch ($block['blockName']) {
            case 'core/template-part':
                if ($include_template_parts) {
                    return $this->process_fse_template_part($block_data, $block['attrs'] ?? [], $processed_template_parts);
                }
                break;
                
            case 'core/block':
                if ($resolve_reusable) {
                    return $this->process_fse_reusable_block($block_data, $block['attrs'] ?? [], $processed_reusable);
                }
                break;
                
            default:
                $block_data = $this->process_fse_regular_block($block_data, $block);
                break;
        }
        
        // Process inner blocks
        if (!empty($block['innerBlocks'])) {
            $block_data['innerBlocks'] = $this->process_fse_blocks($block['innerBlocks'], $include_template_parts, $resolve_reusable, $processed_template_parts, $processed_reusable);
        } else {
            $block_data['innerBlocks'] = [];
        }
        
        return $block_data;
    }
    
    /**
     * Process template part block
     */
    private function process_fse_template_part($block_data, $attrs, &$processed_template_parts) {
        $slug = $attrs['slug'] ?? '';
        $theme = $attrs['theme'] ?? get_stylesheet();
        
        $part_key = $theme . '/' . $slug;
        if (in_array($part_key, $processed_template_parts)) {
            $block_data['error'] = 'Circular reference detected';
            return $block_data;
        }
        
        $processed_template_parts[] = $part_key;
        
        $template_part = $this->get_fse_template_part($slug, $theme);
        if ($template_part && !empty($template_part->content)) {
            $blocks = parse_blocks($template_part->content);
            $block_data['resolvedBlocks'] = $this->process_fse_blocks($blocks, true, true, $processed_template_parts, []);
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
    private function process_fse_reusable_block($block_data, $attrs, &$processed_reusable) {
        $ref = $attrs['ref'] ?? 0;
        
        if (in_array($ref, $processed_reusable)) {
            $block_data['error'] = 'Circular reference detected';
            return $block_data;
        }
        
        $processed_reusable[] = $ref;
        
        $reusable_block = get_post($ref);
        if ($reusable_block && $reusable_block->post_type === 'wp_block') {
            $blocks = parse_blocks($reusable_block->post_content);
            $block_data['resolvedBlocks'] = $this->process_fse_blocks($blocks, true, true, [], $processed_reusable);
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
     * Process regular block
     */
    private function process_fse_regular_block($block_data, $block) {
        if (!empty($block['innerHTML']) && trim($block['innerHTML']) !== '') {
            $block_data['innerHTML'] = trim($block['innerHTML']);
        }
        
        if ($this->is_fse_dynamic_block($block['blockName'])) {
            $block_data['isDynamic'] = true;
            $block_data['dynamicMetadata'] = $this->get_fse_dynamic_block_metadata($block['blockName'], $block['attrs'] ?? []);
        }
        
        return $block_data;
    }
    
    /**
     * Check if block is dynamic
     */
    private function is_fse_dynamic_block($block_name) {
        $registry = WP_Block_Type_Registry::get_instance();
        $block_type = $registry->get_registered($block_name);
        
        return $block_type && !empty($block_type->render_callback);
    }
    
    /**
     * Get dynamic block metadata
     */
    private function get_fse_dynamic_block_metadata($block_name, $attrs) {
        $metadata = [
            'blockType' => $block_name,
            'renderCallback' => true,
        ];
        
        switch ($block_name) {
            case 'core/query':
                $metadata['queryArgs'] = $this->extract_fse_query_args($attrs);
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
        }
        
        return $metadata;
    }
    
    /**
     * Extract query args
     */
    private function extract_fse_query_args($attrs) {
        if (!isset($attrs['query'])) {
            return [];
        }
        
        $query = $attrs['query'];
        return [
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
    
    /**
     * Get template part
     */
    private function get_fse_template_part($slug, $theme) {
        $template_parts = wp_get_theme()->get_block_template_parts();
        foreach ($template_parts as $part) {
            if ($part->slug === $slug) {
                return $part;
            }
        }
        
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
     * Clean block attributes
     */
    private function clean_fse_block_attributes($attrs) {
        if (empty($attrs)) {
            return [];
        }
        
        $cleaned = $attrs;
        unset($cleaned['lock'], $cleaned['metadata']);
        
        array_walk_recursive($cleaned, function(&$value) {
            if (is_object($value)) {
                $value = (array) $value;
            }
        });
        
        return $cleaned;
    }
    
    /**
     * Count blocks recursively
     */
    private function count_fse_blocks($blocks) {
        $count = 0;
        foreach ($blocks as $block) {
            $count++;
            if (!empty($block['innerBlocks'])) {
                $count += $this->count_fse_blocks($block['innerBlocks']);
            }
            if (!empty($block['resolvedBlocks'])) {
                $count += $this->count_fse_blocks($block['resolvedBlocks']);
            }
        }
        return $count;
    }
    
    /**
     * Get available templates
     */
    private function get_fse_available_templates() {
        $templates = [];
        
        // Theme templates
        $theme_templates = wp_get_theme()->get_block_templates();
        foreach ($theme_templates as $template) {
            $templates[] = [
                'slug' => $template->slug,
                'title' => $template->title,
                'description' => $template->description,
                'area' => $template->area ?? 'wp_template',
                'source' => 'theme',
            ];
        }
        
        // Custom templates
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
                'source' => 'custom',
            ];
        }
        
        return $templates;
    }
}

// Initialize the plugin
new FSE_Block_Parser_Plugin();