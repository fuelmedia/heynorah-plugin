<?php
declare(strict_types=1);

namespace HeyNorah\Core;

use HeyNorah\Services\SettingsService;
use HeyNorah\Utils\Obfuscator;
use HeyNorah\Utils\Environment;
use HeyNorah\Core\Config;
use HeyNorah\Api\SettingsController;
use HeyNorah\Api\WebhookController;
use HeyNorah\Api\LogsController;
use HeyNorah\Api\StatsController;
use HeyNorah\Api\ToolsController;
use HeyNorah\Api\SearchController;
use HeyNorah\Services\DomainChallengeService;
use HeyNorah\Database\Schema;

class Plugin
{
    public const CDN_BASE_URL = 'https://cdn.ims.heynorah.ai';
    public const PROD_API_BASE_URL = 'https://cx.api.x.heynorah.ai';
    public const DEV_API_BASE_URL = 'http://127.0.0.1:3215';
    public const CPT_INVENTORY = 'heynorah_inventory';
    public const TAX_INDUSTRY = 'heynorah_type';
    public const TABLE_LOGS = 'heynorah_webhook_logs';
    public const TABLE_SETTINGS = 'heynorah_settings';
    public const TABLE_AUDIT_LOG = 'heynorah_audit_log';
    public const TABLE_WEBHOOK_EVENTS = 'heynorah_webhook_events';
    public const MIN_WORDPRESS_VERSION = '6.3';
    public const TESTED_WORDPRESS_VERSION = '7.0';
    public const MIN_PHP_VERSION = '8.2';
    private const VITE_SERVER = 'http://localhost:5175';
    private SettingsService $settingsService;
    private DomainChallengeService $domainChallengeService;

    /**
     * Get API base URL based on environment
     */
    public static function get_api_base_url(): string
    {
        if (defined('HEYNORAH_API_BASE_URL') && is_string(HEYNORAH_API_BASE_URL) && trim(HEYNORAH_API_BASE_URL) !== '') {
            return untrailingslashit(trim(HEYNORAH_API_BASE_URL));
        }

        $env_url = getenv('HEYNORAH_API_BASE_URL');
        if (is_string($env_url) && trim($env_url) !== '') {
            return untrailingslashit(trim($env_url));
        }

        return Environment::is_development()
            ? self::DEV_API_BASE_URL
            : self::PROD_API_BASE_URL;
    }

    public function __construct()
    {
        $this->settingsService = new SettingsService();
        $this->domainChallengeService = new DomainChallengeService($this->settingsService);
    }

    /**
     * Recursively sanitize data for JavaScript output
     *
     * SECURITY: Prevents XSS via wp_localize_script
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_for_js($data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize_for_js'], $data);
        }

        if (is_string($data)) {
            return esc_js($data);
        }

        if (is_bool($data) || is_int($data) || is_float($data)) {
            return $data;
        }

        // null or other types
        return $data;
    }

    private function resolve_ms_search_key(?array $organization, bool $is_development): string
    {
        $org_key = is_array($organization)
            ? (string) ($organization['meilisearchPublicKey'] ?? '')
            : '';

        if ($org_key !== '') {
            return $org_key;
        }

        return $is_development ? Config::DEV_MS_SEARCH_KEY : '';
    }

    private function resolve_ms_inventory_index(?array $organization): string
    {
        $indexes = (is_array($organization) && is_array($organization['meilisearchIndexes'] ?? null))
            ? $organization['meilisearchIndexes']
            : [];

        $inventory_index = (string) ($indexes['inventory'] ?? '');
        if ($inventory_index === '') {
            $inventory_index = (string) ($indexes['records'] ?? '');
        }

        return $inventory_index !== '' ? $inventory_index : 'inventory';
    }

    public function run()
    {
        $this->ensure_database_schema();

        // SECURITY: Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);

        add_action('init', [$this->domainChallengeService, 'register_rewrite_rule']);
        add_filter('query_vars', [$this->domainChallengeService, 'register_query_vars']);
        add_action('template_redirect', [$this->domainChallengeService, 'handle_request']);

        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets'], PHP_INT_MAX);

        add_filter('script_loader_tag', [$this, 'add_type_module_attribute'], 10, 3);

        $cpt_manager = new PostTypeManager();
        $cpt_manager->register();

        add_action('rest_api_init', function (): void {
            (new SettingsController())->register_routes();
            (new WebhookController())->register_routes();
            (new LogsController())->register_routes();
            (new StatsController())->register_routes();
            (new ToolsController())->register_routes();
            (new SearchController())->register_routes();
        });

        add_action('heynorah_daily_cleanup', [$this, 'cleanup_logs']);

        if (!wp_next_scheduled('heynorah_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'heynorah_daily_cleanup');
        }
    }

    public function add_admin_page()
    {
        add_menu_page(
            'HeyNorah AI',
            'HeyNorah AI',
            'manage_options',
            'heynorah',
            [$this, 'render_admin_page'],
            'dashicons-superhero',
            2
        );
    }

    public function render_admin_page()
    {
        echo '<div id="heynorah-root"></div>';
    }

    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_heynorah' !== $hook)
            return;

        $connection_status = $this->settingsService->get_connection_status();
        $organization = $connection_status['organization'] ?? null;

        // Get Meilisearch credentials
        $is_development = Environment::is_development();

        $configured_ms_url = $this->settingsService->get('meilisearch_url');
        $ms_url = $configured_ms_url !== '' ? $configured_ms_url : ($is_development ? Config::DEV_MS_URL : Config::PROD_MS_URL);
        $ms_search_key = $this->resolve_ms_search_key($organization, $is_development);
        $webhook_url = rest_url('heynorah/v2/webhook');
        $webhook_url = set_url_scheme($webhook_url, $is_development ? 'http' : 'https');

        // SECURITY FIX: Sanitize organization and user data before passing to frontend
        $safe_organization = $this->sanitize_for_js($organization);
        $safe_user = $this->sanitize_for_js($connection_status['user'] ?? null);
        $safe_webhook = $this->sanitize_for_js($connection_status['webhook'] ?? null);

        // SECURITY: Pass search-only API key (not master key) for instant search
        $data = [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin_url' => admin_url(),
            'webhook_url' => esc_url_raw($webhook_url),
            'plugin_url' => trailingslashit(plugin_dir_url(dirname(__DIR__))),
            'cdn_base_url' => trailingslashit(self::CDN_BASE_URL),
            'api_base_url' => esc_url($this->settingsService->get('api_base_url') ?: self::get_api_base_url()),
            'ms_url' => esc_url($ms_url),
            'ms_search_key' => esc_js($ms_search_key), // Search-only key for instant search
            'site_api_key' => esc_js($this->settingsService->get('site_api_key')),
            'organization' => $safe_organization,
            'user' => $safe_user,
            'webhook' => $safe_webhook,
            'verified' => (bool) ($connection_status['verified'] ?? false),
            'verified_at' => esc_js($connection_status['verified_at'] ?? ''),
        ];

        $this->load_react_app('heynorah-admin-app', 'src/main.tsx', 'heynorahSettings', $data);
    }

    public function enqueue_frontend_assets()
    {
        // Enqueue Tailwind CSS for single inventory posts
        if (is_singular(self::CPT_INVENTORY)) {
            $this->enqueue_single_styles();

            // Enqueue Tabler Icons
            wp_enqueue_style(
                'tabler-icons',
                'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css',
                [],
                '3.26.0'
            );
        }

        // Enqueue search app for archive pages
        if (!is_tax(self::TAX_INDUSTRY))
            return;

        $connection_status = $this->settingsService->get_connection_status();
        $organization = $connection_status['organization'] ?? null;

        // Get Meilisearch credentials
        $is_development = Environment::is_development();

        $configured_ms_url = $this->settingsService->get('meilisearch_url');
        $ms_url = $configured_ms_url !== '' ? $configured_ms_url : ($is_development ? Config::DEV_MS_URL : Config::PROD_MS_URL);
        $ms_search_key = $this->resolve_ms_search_key($organization, $is_development);

        $cpt_slug = $this->settingsService->get('cpt_slug') ?: 'inventory';
        $taxonomy_slug = $this->settingsService->get('taxonomy_slug') ?: 'type';
        $meilisearch_index_inventory = $this->resolve_ms_inventory_index($organization);

        // Get CSS URLs for shadow DOM from manifest
        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/.vite/manifest.json';

        // Default CSS URLs (fallback) with cache-busting
        $fallback_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/main.css';
        $fallback_version = file_exists($fallback_path) ? filemtime($fallback_path) : time();
        $css_url = add_query_arg('ver', (string) $fallback_version, $plugin_url . 'assets/dist/main.css');
        $instantsearch_css_url = $css_url;

        // Read from manifest if available
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);

            // Get main CSS from src/main.tsx entry
            if (isset($manifest['src/main.tsx']['css']) && !empty($manifest['src/main.tsx']['css'])) {
                $css_file = $manifest['src/main.tsx']['css'][0];
                $css_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/' . $css_file;
                $css_version = file_exists($css_path) ? filemtime($css_path) : time();
                $css_url = add_query_arg('ver', (string) $css_version, $plugin_url . 'assets/dist/' . $css_file);
                $instantsearch_css_url = $css_url;
            }
        }

        // SECURITY: Pass search-only API key for instant search (not master key!)
        $data = [
            'ms_url' => esc_url($ms_url),
            'ms_search_key' => esc_js($ms_search_key), // Search-only key for instant search
            'api_root' => esc_url_raw(rest_url()),
            'cpt_slug' => esc_js($cpt_slug),
            'taxonomy_slug' => esc_js($taxonomy_slug),
            'cdn_base_url' => trailingslashit(self::CDN_BASE_URL),
            'plugin_url' => trailingslashit($plugin_url),
            'meilisearch_index_inventory' => esc_js($meilisearch_index_inventory),
            'css_url' => esc_url($css_url),
            'instantsearch_css_url' => esc_url($instantsearch_css_url),
        ];

        $this->load_react_app('heynorah-search-app', 'src/search-app.tsx', 'heynorahConfig', $data);
    }

    private function enqueue_single_styles(): void
    {
        $is_development = Environment::is_development();

        if ($is_development && $this->is_vite_server_available()) {
            // In development, load CSS via Vite dev server with HMR
            if (!wp_script_is('vite-client', 'enqueued')) {
                wp_enqueue_script('vite-client', self::VITE_SERVER . '/@vite/client', [], null, ['in_footer' => true]);
            }

            wp_enqueue_script(
                'heynorah-single-hmr',
                self::VITE_SERVER . '/src/single-hmr.ts',
                ['vite-client'],
                null,
                ['in_footer' => true]
            );
            return;
        }

        // Fallback (or production): enqueue the built CSS file
        $manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry_path = 'src/single.css';

        if (!isset($manifest[$entry_path])) {
            return;
        }

        $entry = $manifest[$entry_path];

        // Enqueue the CSS file(s) from manifest
        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                $file_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/' . $css_file;
                $version = file_exists($file_path) ? filemtime($file_path) : '1.0.0';

                wp_enqueue_style(
                    'heynorah-single-styles',
                    plugin_dir_url(dirname(__DIR__)) . 'assets/dist/' . $css_file,
                    [],
                    $version
                );
                break; // Only enqueue the first CSS file for now
            }
        } elseif (isset($entry['file']) && substr($entry['file'], -4) === '.css') {
            $file_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/' . $entry['file'];
            $version = file_exists($file_path) ? filemtime($file_path) : '1.0.0';

            wp_enqueue_style(
                'heynorah-single-styles',
                plugin_dir_url(dirname(__DIR__)) . 'assets/dist/' . $entry['file'],
                [],
                $version
            );
        }
    }

    private function is_vite_server_available(): bool
    {
        static $is_available = null;

        if ($is_available !== null) {
            return $is_available;
        }

        $response = wp_remote_get(self::VITE_SERVER . '/@vite/client', [
            'timeout' => 0.5,
            'sslverify' => false,
            'redirection' => 0,
        ]);

        if (is_wp_error($response)) {
            $is_available = false;
            return $is_available;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $is_available = $status_code >= 200 && $status_code < 400;

        return $is_available;
    }

    private function load_react_app(string $handle, string $entry_path, string $localize_obj_name, array $data): void
    {
        $is_development = Environment::is_development();
        $vite_available = $is_development ? $this->is_vite_server_available() : false;

        if ($is_development) {
            // Tailwind v4 utilities are currently unreliable when injected via the
            // Vite dev server in wp-admin. Use the built admin bundle in development
            // so responsive/layout utilities resolve correctly.
            if ($handle === 'heynorah-admin-app' || !$vite_available) {
                $this->enqueue_production_assets($handle, $entry_path);
                wp_localize_script($handle, $localize_obj_name, $data);
                return;
            }

            if (!wp_script_is('vite-client', 'enqueued')) {
                wp_enqueue_script('vite-client', self::VITE_SERVER . '/@vite/client', [], null, ['in_footer' => true]);
            }

            if (!has_action('wp_print_footer_scripts', [$this, 'print_react_preamble']) && !has_action('admin_print_footer_scripts', [$this, 'print_react_preamble'])) {
                $action_hook = is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts';
                add_action($action_hook, [$this, 'print_react_preamble'], 0);
            }

            wp_enqueue_script($handle, self::VITE_SERVER . '/' . $entry_path, ['vite-client'], null, ['in_footer' => true]);
        } else {
            $this->enqueue_production_assets($handle, $entry_path);
        }

        wp_localize_script($handle, $localize_obj_name, $data);
    }

    private function enqueue_entry_css(string $handle, string $entry_path): void
    {
        $manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);

        if (!isset($manifest[$entry_path])) {
            return;
        }

        $entry = $manifest[$entry_path];
        $css_files = $entry['css'] ?? [];

        if (!empty($entry['imports'])) {
            foreach ($entry['imports'] as $import_key) {
                if (isset($manifest[$import_key]['css'])) {
                    $css_files = array_merge($css_files, $manifest[$import_key]['css']);
                }
            }
        }

        foreach (array_unique($css_files) as $index => $css) {
            $css_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/' . $css;
            $css_version = file_exists($css_path) ? filemtime($css_path) : '1.0.0';

            wp_enqueue_style(
                $handle . '-style-' . $index,
                plugin_dir_url(dirname(__DIR__)) . 'assets/dist/' . $css,
                [],
                $css_version,
                'all'
            );
        }
    }

    public function print_react_preamble(): void
    {
        echo '<script type="module">
            import RefreshRuntime from "' . self::VITE_SERVER . '/@react-refresh"
            RefreshRuntime.injectIntoGlobalHook(window)
            window.$RefreshReg$ = () => {}
            window.$RefreshSig$ = () => (type) => type
            window.__vite_plugin_react_preamble_installed__ = true
        </script>';
    }
    private function enqueue_production_assets(string $handle, string $entry_path): void
    {
        $manifest_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/.vite/manifest.json';

        if (!file_exists($manifest_path))
            return;

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (!isset($manifest[$entry_path]))
            return;
        $entry = $manifest[$entry_path];

        // IMPORTANT: Skip CSS enqueue for search app (uses Shadow DOM with inline CSS)
        $skip_css_enqueue = $handle === 'heynorah-search-app';

        if (!$skip_css_enqueue) {
            $this->enqueue_entry_css($handle, $entry_path);
        }

        $js_path = plugin_dir_path(dirname(__DIR__)) . 'assets/dist/' . $entry['file'];
        $js_version = file_exists($js_path) ? filemtime($js_path) : '1.0.0';

        wp_enqueue_script(
            $handle,
            plugin_dir_url(dirname(__DIR__)) . 'assets/dist/' . $entry['file'],
            [],
            $js_version,
            ['in_footer' => true]
        );
    }

    public function add_type_module_attribute(string $tag, string $handle, string $src): string
    {
        $modules = ['vite-client', 'heynorah-admin-app', 'heynorah-search-app', 'heynorah-single-hmr'];

        if (in_array($handle, $modules, true)) {
            return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
        }
        return $tag;
    }

    public function cleanup_logs(): void
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . self::TABLE_LOGS;
        $events_table = $wpdb->prefix . self::TABLE_WEBHOOK_EVENTS;

        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE created_at < NOW() - INTERVAL 30 DAY", $logs_table));
        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE processed_at < NOW() - INTERVAL 30 DAY", $events_table));
    }

    private function ensure_database_schema(): void
    {
        $db_version = (string) get_option('heynorah_db_version', '0.0.0');
        if (version_compare($db_version, '1.3.0', '<')) {
            Schema::create_tables();
        }
    }

    /**
     * Add security headers
     *
     * SECURITY: Adds HTTP security headers for admin panel
     */
    public function add_security_headers(): void
    {
        // Only for admin panel
        if (!is_admin()) {
            return;
        }

        // Content Security Policy - Allow inline scripts for WordPress compatibility
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';");

        // Prevent clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy - Restrict dangerous features
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
