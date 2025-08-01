<?php
/**
 * Example usage for FSE Block Parser
 * 
 * This file demonstrates how to use the plugin and shows example JSON output
 */

// Example 1: Get available templates
// GET /wp-json/fse/v1/templates

/*
Expected Response:
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
    },
    {
      "slug": "single",
      "title": "Single Post",
      "description": "Template for single posts",
      "area": "wp_template"
    }
  ]
}
*/

// Example 2: Get front-page template blocks with all features enabled
// GET /wp-json/fse/v1/template/front-page?include_template_parts=true&resolve_reusable=true

/*
Expected Response:
{
  "success": true,
  "data": {
    "template": {
      "slug": "front-page",
      "title": "Front Page",
      "description": "Template for the front page"
    },
    "blocks": [
      {
        "blockName": "core/template-part",
        "clientId": "block_12345678-1234-5678-9abc-123456789abc",
        "attributes": {
          "slug": "header",
          "theme": "my-theme"
        },
        "resolvedBlocks": [
          {
            "blockName": "core/site-title",
            "clientId": "block_23456789-2345-6789-abcd-23456789abcd",
            "attributes": {
              "level": 1,
              "isLink": true
            },
            "innerBlocks": [],
            "isDynamic": true,
            "dynamicMetadata": {
              "blockType": "core/site-title",
              "renderCallback": true,
              "contextRequired": "site"
            }
          },
          {
            "blockName": "core/navigation",
            "clientId": "block_34567890-3456-7890-bcde-34567890bcde",
            "attributes": {
              "orientation": "horizontal",
              "showSubmenuIcon": true
            },
            "innerBlocks": [
              {
                "blockName": "core/navigation-link",
                "clientId": "block_45678901-4567-8901-cdef-45678901cdef",
                "attributes": {
                  "label": "Home",
                  "url": "/",
                  "kind": "custom"
                },
                "innerBlocks": []
              }
            ]
          }
        ],
        "templatePart": {
          "slug": "header",
          "theme": "my-theme",
          "title": "Header",
          "area": "header"
        },
        "innerBlocks": []
      },
      {
        "blockName": "core/group",
        "clientId": "block_56789012-5678-9012-def0-56789012def0",
        "attributes": {
          "tagName": "main",
          "layout": {
            "type": "constrained"
          }
        },
        "innerBlocks": [
          {
            "blockName": "core/heading",
            "clientId": "block_67890123-6789-0123-ef01-67890123ef01",
            "attributes": {
              "level": 1,
              "content": "Welcome to My Site"
            },
            "innerHTML": "<h1>Welcome to My Site</h1>",
            "innerBlocks": []
          },
          {
            "blockName": "core/paragraph",
            "clientId": "block_78901234-7890-1234-f012-78901234f012",
            "attributes": {
              "content": "This is a paragraph block with some content."
            },
            "innerHTML": "<p>This is a paragraph block with some content.</p>",
            "innerBlocks": []
          },
          {
            "blockName": "core/query",
            "clientId": "block_89012345-8901-2345-0123-89012345f123",
            "attributes": {
              "query": {
                "postType": "post",
                "perPage": 5,
                "offset": 0,
                "order": "desc",
                "orderBy": "date"
              }
            },
            "innerBlocks": [
              {
                "blockName": "core/post-template",
                "clientId": "block_90123456-9012-3456-1234-90123456f234",
                "attributes": {},
                "innerBlocks": [
                  {
                    "blockName": "core/post-title",
                    "clientId": "block_01234567-0123-4567-2345-01234567f345",
                    "attributes": {
                      "level": 2,
                      "isLink": true
                    },
                    "innerBlocks": [],
                    "isDynamic": true,
                    "dynamicMetadata": {
                      "blockType": "core/post-title",
                      "renderCallback": true,
                      "contextRequired": "post"
                    }
                  },
                  {
                    "blockName": "core/post-excerpt",
                    "clientId": "block_12345678-1234-5678-3456-12345678f456",
                    "attributes": {
                      "moreText": "Read more"
                    },
                    "innerBlocks": [],
                    "isDynamic": true,
                    "dynamicMetadata": {
                      "blockType": "core/post-excerpt",
                      "renderCallback": true,
                      "contextRequired": "post"
                    }
                  }
                ]
              }
            ],
            "isDynamic": true,
            "dynamicMetadata": {
              "blockType": "core/query",
              "renderCallback": true,
              "queryArgs": {
                "postType": "post",
                "perPage": 5,
                "offset": 0,
                "order": "desc",
                "orderBy": "date",
                "categoryIds": [],
                "tagIds": [],
                "sticky": ""
              }
            }
          },
          {
            "blockName": "core/block",
            "clientId": "block_23456789-2345-6789-4567-23456789f567",
            "attributes": {
              "ref": 123
            },
            "resolvedBlocks": [
              {
                "blockName": "core/columns",
                "clientId": "block_34567890-3456-7890-5678-34567890f678",
                "attributes": {},
                "innerBlocks": [
                  {
                    "blockName": "core/column",
                    "clientId": "block_45678901-4567-8901-6789-45678901f789",
                    "attributes": {},
                    "innerBlocks": [
                      {
                        "blockName": "core/heading",
                        "clientId": "block_56789012-5678-9012-7890-56789012f890",
                        "attributes": {
                          "level": 3,
                          "content": "Column 1"
                        },
                        "innerHTML": "<h3>Column 1</h3>",
                        "innerBlocks": []
                      }
                    ]
                  },
                  {
                    "blockName": "core/column",
                    "clientId": "block_67890123-6789-0123-8901-67890123f901",
                    "attributes": {},
                    "innerBlocks": [
                      {
                        "blockName": "core/heading",
                        "clientId": "block_78901234-7890-1234-9012-78901234f012",
                        "attributes": {
                          "level": 3,
                          "content": "Column 2"
                        },
                        "innerHTML": "<h3>Column 2</h3>",
                        "innerBlocks": []
                      }
                    ]
                  }
                ]
              }
            ],
            "reusableBlock": {
              "id": 123,
              "title": "Two Column Layout",
              "slug": "two-column-layout"
            },
            "innerBlocks": []
          }
        ]
      },
      {
        "blockName": "core/template-part",
        "clientId": "block_89012345-8901-2345-0123-89012345f123",
        "attributes": {
          "slug": "footer",
          "theme": "my-theme"
        },
        "resolvedBlocks": [
          {
            "blockName": "core/paragraph",
            "clientId": "block_90123456-9012-3456-1234-90123456f234",
            "attributes": {
              "content": "© 2024 My Website. All rights reserved.",
              "align": "center"
            },
            "innerHTML": "<p class=\"has-text-align-center\">© 2024 My Website. All rights reserved.</p>",
            "innerBlocks": []
          }
        ],
        "templatePart": {
          "slug": "footer",
          "theme": "my-theme",
          "title": "Footer",
          "area": "footer"
        },
        "innerBlocks": []
      }
    ]
  },
  "meta": {
    "total_blocks": 15,
    "template_parts_resolved": true,
    "reusable_blocks_resolved": true
  }
}
*/

// Example 3: Get template without resolving template parts and reusable blocks
// GET /wp-json/fse/v1/template/front-page?include_template_parts=false&resolve_reusable=false

/*
Expected Response (simplified):
{
  "success": true,
  "data": {
    "template": {
      "slug": "front-page",
      "title": "Front Page",
      "description": "Template for the front page"
    },
    "blocks": [
      {
        "blockName": "core/template-part",
        "clientId": "block_12345678-1234-5678-9abc-123456789abc",
        "attributes": {
          "slug": "header",
          "theme": "my-theme"
        },
        "innerBlocks": []
      },
      {
        "blockName": "core/group",
        "clientId": "block_56789012-5678-9012-def0-56789012def0",
        "attributes": {
          "tagName": "main"
        },
        "innerBlocks": [
          // ... other blocks without resolution
        ]
      },
      {
        "blockName": "core/block",
        "clientId": "block_23456789-2345-6789-4567-23456789f567",
        "attributes": {
          "ref": 123
        },
        "innerBlocks": []
      }
    ]
  },
  "meta": {
    "total_blocks": 5,
    "template_parts_resolved": false,
    "reusable_blocks_resolved": false
  }
}
*/

// Example function to fetch and use the data in a WordPress theme or plugin
function fetch_template_blocks_example() {
    // Using WordPress HTTP API
    $response = wp_remote_get(home_url('/wp-json/fse/v1/template/front-page'));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data['success']) {
        $blocks = $data['data']['blocks'];
        
        // Process blocks for your mobile app
        return process_blocks_for_mobile($blocks);
    }
    
    return false;
}

function process_blocks_for_mobile($blocks) {
    $mobile_blocks = [];
    
    foreach ($blocks as $block) {
        $mobile_block = [
            'type' => $block['blockName'],
            'id' => $block['clientId'],
            'props' => $block['attributes'],
        ];
        
        // Handle different block types for mobile rendering
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
                break;
                
            case 'core/query':
                $mobile_block['queryArgs'] = $block['dynamicMetadata']['queryArgs'] ?? [];
                break;
        }
        
        // Process inner blocks recursively
        if (!empty($block['innerBlocks'])) {
            $mobile_block['children'] = process_blocks_for_mobile($block['innerBlocks']);
        }
        
        // Handle resolved blocks (template parts, reusable blocks)
        if (!empty($block['resolvedBlocks'])) {
            $mobile_block['resolvedChildren'] = process_blocks_for_mobile($block['resolvedBlocks']);
        }
        
        $mobile_blocks[] = $mobile_block;
    }
    
    return $mobile_blocks;
}

// Example error handling
function handle_api_errors($response) {
    if (is_wp_error($response)) {
        error_log('FSE Block Parser API Error: ' . $response->get_error_message());
        return [
            'success' => false,
            'error' => 'API request failed',
            'message' => $response->get_error_message()
        ];
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        
        error_log('FSE Block Parser API Error: HTTP ' . $status_code);
        return [
            'success' => false,
            'error' => 'HTTP error',
            'status_code' => $status_code,
            'message' => $error_data['message'] ?? 'Unknown error'
        ];
    }
    
    return null; // No error
}

// Example usage with error handling
function safe_fetch_template_blocks($template_name) {
    $url = home_url("/wp-json/fse/v1/template/{$template_name}");
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
        ]
    ]);
    
    $error = handle_api_errors($response);
    if ($error) {
        return $error;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'JSON decode error',
            'message' => json_last_error_msg()
        ];
    }
    
    return $data;
}