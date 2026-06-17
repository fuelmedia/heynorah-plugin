<?php
declare(strict_types=1);

namespace HeyNorah\Core;

use HeyNorah\Services\SettingsService;

class PostTypeManager
{
    private SettingsService $settingsService;

    public function __construct()
    {
        $this->settingsService = new SettingsService();
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_inventory_cpt']);
        add_action('init', [$this, 'register_type_taxonomy']);
        add_action('init', [$this, 'add_search_rewrite_rules']);
        add_filter('post_type_link', [$this, 'filter_post_type_link'], 10, 2);
        add_filter('single_template', [$this, 'load_single_template']);
        add_filter('archive_template', [$this, 'load_archive_template']);
        add_filter('taxonomy_template', [$this, 'load_taxonomy_template']);
        add_filter('request', [$this, 'handle_filter_request'], 10, 1);
    }

    public function register_inventory_cpt(): void
    {
        $slug = $this->settingsService->get('cpt_slug') ?: 'inventory';
        $slug = sanitize_title($slug);

        $labels = [
            'name' => _x('Inventory', 'Post Type General Name', 'heynorah'),
            'singular_name' => _x('Inventory Item', 'Post Type Singular Name', 'heynorah'),
            'menu_name' => __('Inventory', 'heynorah'),
            'name_admin_bar' => __('Inventory', 'heynorah'),
            'archives' => __('Item Archives', 'heynorah'),
            'attributes' => __('Item Attributes', 'heynorah'),
            'parent_item_colon' => __('Parent Item:', 'heynorah'),
            'all_items' => __('All Items', 'heynorah'),
            'add_new_item' => __('Add New Item', 'heynorah'),
            'add_new' => __('Add New', 'heynorah'),
            'new_item' => __('New Item', 'heynorah'),
            'edit_item' => __('Edit Item', 'heynorah'),
            'update_item' => __('Update Item', 'heynorah'),
            'view_item' => __('View Item', 'heynorah'),
            'view_items' => __('View Items', 'heynorah'),
            'search_items' => __('Search Item', 'heynorah'),
        ];

        $args = [
            'label' => __('Inventory', 'heynorah'),
            'labels' => $labels,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'taxonomies' => [],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-products', // WP Dashicon
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'show_in_rest' => true,
            'rest_base' => 'inventory',

            "rewrite" => [
                "slug" => $slug . '/%' . Plugin::TAX_INDUSTRY . '%/detail',
                "with_front" => false,
            ],
        ];

        register_post_type(Plugin::CPT_INVENTORY, $args);
    }

    public function filter_post_type_link(string $post_link, $post): string
    {
        if ($post->post_type !== Plugin::CPT_INVENTORY) {
            return $post_link;
        }

        if (str_contains($post_link, '%' . Plugin::TAX_INDUSTRY . '%')) {
            $terms = get_the_terms($post->ID, Plugin::TAX_INDUSTRY);

            if (!empty($terms) && !is_wp_error($terms)) {
                $term = array_shift($terms);
                $post_link = str_replace('%' . Plugin::TAX_INDUSTRY . '%', $term->slug, $post_link);
            } else {
                // No term assigned, use 'uncategorized'
                $post_link = str_replace('%' . Plugin::TAX_INDUSTRY . '%', 'uncategorized', $post_link);
            }
        }

        return $post_link;
    }

    public function register_type_taxonomy(): void
    {
        $taxonomy_slug = $this->settingsService->get('taxonomy_slug') ?: 'type';
        $taxonomy_slug = sanitize_title($taxonomy_slug);

        $cpt_slug = $this->settingsService->get('cpt_slug') ?: 'inventory';
        $cpt_slug = sanitize_title($cpt_slug);

        $labels = [
            'name' => _x('Types', 'taxonomy general name', 'heynorah'),
            'singular_name' => _x('Type', 'taxonomy singular name', 'heynorah'),
            'search_items' => __('Search Types', 'heynorah'),
            'all_items' => __('All Types', 'heynorah'),
            'parent_item' => __('Parent Type', 'heynorah'),
            'parent_item_colon' => __('Parent Type:', 'heynorah'),
            'edit_item' => __('Edit Type', 'heynorah'),
            'update_item' => __('Update Type', 'heynorah'),
            'add_new_item' => __('Add New Type', 'heynorah'),
            'new_item_name' => __('New Type Name', 'heynorah'),
            'menu_name' => __('Types', 'heynorah'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => $cpt_slug,
                'with_front' => false,
                'hierarchical' => false,
            ],
            'show_in_rest' => true,
        ];

        register_taxonomy(Plugin::TAX_INDUSTRY, [Plugin::CPT_INVENTORY], $args);
    }

    public function load_single_template($template)
    {
        global $post;
        if ($post instanceof \WP_Post && $post->post_type === Plugin::CPT_INVENTORY) {
            $plugin_template = plugin_dir_path(dirname(__DIR__)) . 'src/Templates/single.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function load_archive_template($template)
    {
        global $post;
        if ($post instanceof \WP_Post && $post->post_type === Plugin::CPT_INVENTORY) {
            $plugin_template = plugin_dir_path(dirname(__DIR__)) . 'src/Templates/archive.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function load_taxonomy_template($template)
    {
        global $post;
        $is_inventory_post = $post instanceof \WP_Post && $post->post_type === Plugin::CPT_INVENTORY;
        if (($is_inventory_post || is_tax(Plugin::TAX_INDUSTRY)) && is_tax(Plugin::TAX_INDUSTRY)) {
            $plugin_template = plugin_dir_path(dirname(__DIR__)) . 'src/Templates/taxonomy-' . Plugin::TAX_INDUSTRY . '.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function add_search_rewrite_rules(): void
    {
        $slug = $this->settingsService->get('cpt_slug') ?: 'inventory';
        $slug = sanitize_title($slug);

        // Match filter paths but NOT detail pages
        // Negative lookahead: (?!detail/) - don't match if next segment is "detail/"
        // This allows WordPress's default single post rewrite to handle detail URLs
        add_rewrite_rule(
            '^' . $slug . '/([^/]+)/(?!detail/)(.+)/?$',
            'index.php?' . Plugin::TAX_INDUSTRY . '=$matches[1]&heynorah_filter_path=$matches[2]',
            'top'
        );

        // Base taxonomy archive
        add_rewrite_rule(
            '^' . $slug . '/([^/]+)/?$',
            'index.php?' . Plugin::TAX_INDUSTRY . '=$matches[1]',
            'top'
        );
    }

    public function handle_filter_request($query_vars)
    {
        // If we have a filter path but WordPress thinks it's a 404,
        // force it to recognize as a taxonomy archive
        if (isset($query_vars['heynorah_filter_path']) && isset($query_vars[Plugin::TAX_INDUSTRY])) {
            // Remove any error flags
            unset($query_vars['error']);
            unset($query_vars['pagename']);
            unset($query_vars['page']);

            // Ensure it's treated as a taxonomy query
            $query_vars['taxonomy'] = Plugin::TAX_INDUSTRY;
            $query_vars['term'] = $query_vars[Plugin::TAX_INDUSTRY];
        }

        return $query_vars;
    }
}
