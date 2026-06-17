<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HeyNorah\Services\SettingsService;
use HeyNorah\Services\OrganizationService;
use HeyNorah\Services\AuditLogService;
use HeyNorah\Core\Plugin;

class ToolsController extends WP_REST_Controller
{
    private SettingsService $settingsService;
    private OrganizationService $organizationService;
    private AuditLogService $auditLog;

    public function __construct()
    {
        $this->settingsService = new SettingsService();
        $this->organizationService = new OrganizationService();
        $this->auditLog = new AuditLogService();
    }

    public function register_routes(): void
    {
        register_rest_route('heynorah/v1', '/tools/system-status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/tools/clear-logs', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/tools/reset-connection', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reset_connection'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/tools/test-connection', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'test_connection'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/tools/clear-all-settings', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'clear_all_settings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/tools/fix-permissions', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'fix_permissions'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function get_system_status(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        // PHP & WordPress Version
        $php_version = phpversion();
        $wp_version = get_bloginfo('version');
        $php_required = '8.0';
        $wp_required = '6.0';

        // Database Status
        $logs_table = $wpdb->prefix . Plugin::TABLE_LOGS;
        $settings_table = $wpdb->prefix . Plugin::TABLE_SETTINGS;

        // SECURITY FIX: Use wpdb->prepare() for all SQL queries
        $logs_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logs_table)) === $logs_table;
        $settings_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $settings_table)) === $settings_table;

        $logs_count = $logs_exists ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $logs_table)) : 0;
        $settings_count = $settings_exists ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $settings_table)) : 0;

        // API Connection Status
        $connection_status = $this->settingsService->get_connection_status();
        $api_connected = $connection_status['verified'] ?? false;
        $organization_name = $api_connected ? ($connection_status['organization']['name'] ?? 'Unknown') : null;

        // File Permissions
        $upload_dir = wp_upload_dir();
        $upload_writable = is_writable($upload_dir['basedir']);
        $plugin_dir_writable = is_writable(plugin_dir_path(dirname(__DIR__)));

        return rest_ensure_response([
            'php' => [
                'version' => $php_version,
                'required' => $php_required,
                'status' => version_compare($php_version, $php_required, '>=') ? 'ok' : 'warning',
            ],
            'wordpress' => [
                'version' => $wp_version,
                'required' => $wp_required,
                'status' => version_compare($wp_version, $wp_required, '>=') ? 'ok' : 'warning',
            ],
            'database' => [
                'logs_table' => [
                    'exists' => $logs_exists,
                    'count' => $logs_count,
                    'status' => $logs_exists ? 'ok' : 'error',
                ],
                'settings_table' => [
                    'exists' => $settings_exists,
                    'count' => $settings_count,
                    'status' => $settings_exists ? 'ok' : 'error',
                ],
            ],
            'api_connection' => [
                'connected' => $api_connected,
                'organization' => $organization_name,
                'verified_at' => $connection_status['verified_at'] ?? null,
                'status' => $api_connected ? 'ok' : 'warning',
            ],
            'permissions' => [
                'upload_dir' => [
                    'writable' => $upload_writable,
                    'path' => $upload_dir['basedir'],
                    'status' => $upload_writable ? 'ok' : 'error',
                ],
                'plugin_dir' => [
                    'writable' => $plugin_dir_writable,
                    'path' => plugin_dir_path(dirname(__DIR__)),
                    'status' => $plugin_dir_writable ? 'ok' : 'warning',
                ],
            ],
        ]);
    }

    public function clear_logs(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . Plugin::TABLE_LOGS;

        // SECURITY FIX: Use wpdb->prepare() with %i for table identifier
        $deleted = $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $table));

        // AUDIT LOG: Log data clear action
        $this->auditLog->log_data_clear('webhook_logs', get_current_user_id());

        return rest_ensure_response([
            'success' => true,
            'message' => 'All webhook logs cleared successfully',
            'deleted_rows' => $deleted !== false ? $deleted : 0,
        ]);
    }

    public function reset_connection(WP_REST_Request $request): WP_REST_Response
    {
        $this->settingsService->clear_connection_data();
        $this->settingsService->save('site_api_key', '');

        // AUDIT LOG: API disconnection
        $this->auditLog->log_api_connection('disconnect', get_current_user_id());

        return rest_ensure_response([
            'success' => true,
            'message' => 'Connection data has been reset. Please enter your API key again.',
        ]);
    }

    public function test_connection(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $api_key = $this->settingsService->get('site_api_key');

        if (empty($api_key)) {
            return new WP_Error(
                'rest_no_api_key',
                'No API key configured. Please add your API key first.',
                array('status' => 400)
            );
        }

        $webhook_url = rest_url('heynorah/v2/webhook');
        $response = $this->organizationService->connect_api_key($api_key, $webhook_url);

        if (!empty($response['success'])) {
            $response_data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $validated = !empty($response_data['validated']);
            return rest_ensure_response([
                'success' => true,
                'message' => $validated
                    ? 'Connection successful and domain validated.'
                    : 'Connection successful. Domain verification pending.',
                'data' => [
                    'organization' => $response_data['organization']['name'] ?? 'Unknown',
                    'user' => $response_data['user']['email'] ?? 'Unknown',
                    'validated' => $validated,
                ],
            ]);
        }

        return new WP_Error(
            'rest_connection_failed',
            'Connection failed. ' . (string) ($response['error'] ?? 'Please check your API key.'),
            array('status' => 400)
        );
    }

    public function clear_all_settings(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . Plugin::TABLE_SETTINGS;

        // SECURITY FIX: Use wpdb->prepare() with %i for table identifier
        $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $table));
        delete_option('heynorah_webhook_meta');
        $this->settingsService->clear_form_settings();

        // AUDIT LOG: All settings cleared
        $this->auditLog->log_data_clear('all_settings', get_current_user_id());

        return rest_ensure_response([
            'success' => true,
            'message' => 'All settings have been cleared. You will need to reconfigure the plugin.',
        ]);
    }

    public function fix_permissions(WP_REST_Request $request): WP_REST_Response
    {
        $results = [];
        $errors = [];

        // Get plugin directory
        $plugin_dir = plugin_dir_path(dirname(__DIR__));
        $upload_dir = wp_upload_dir();

        // Fix plugin directory permissions (755 for directories, 644 for files)
        try {
            $this->fix_directory_permissions($plugin_dir, $results, $errors);
        } catch (\Exception $e) {
            $errors[] = 'Plugin directory: ' . $e->getMessage();
        }

        // Fix upload directory permissions
        try {
            $this->fix_directory_permissions($upload_dir['basedir'], $results, $errors);
        } catch (\Exception $e) {
            $errors[] = 'Upload directory: ' . $e->getMessage();
        }

        // AUDIT LOG: Permission fix action
        $this->auditLog->log_permission_fix(
            get_current_user_id(),
            ['fixed_count' => count($results), 'errors' => count($errors)]
        );

        $success = empty($errors);

        return rest_ensure_response([
            'success' => $success,
            'message' => $success
                ? 'Permissions have been fixed successfully'
                : 'Permissions fix completed with some errors',
            'results' => [
                'fixed' => count($results),
                'errors' => $errors,
            ],
        ]);
    }

    private function fix_directory_permissions(string $path, array &$results, array &$errors): void
    {
        if (!file_exists($path)) {
            return;
        }

        // Do not mutate symlinks; they can point outside plugin boundaries.
        if (is_link($path)) {
            return;
        }

        if ($this->should_skip_permissions_path($path)) {
            return;
        }

        // Fix current directory/file
        if (is_dir($path)) {
            if (@chmod($path, 0755)) {
                $results[] = $path;
            } else {
                $errors[] = "Failed to fix: $path";
            }

            // Recursively fix subdirectories and files
            $items = @scandir($path);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $full_path = rtrim($path, '/') . '/' . $item;
                    $this->fix_directory_permissions($full_path, $results, $errors);
                }
            }
        } else {
            // It's a file
            $mode = $this->should_keep_executable($path) ? 0755 : 0644;
            if (@chmod($path, $mode)) {
                $results[] = $path;
            } else {
                $errors[] = "Failed to fix: $path";
            }
        }
    }

    private function should_skip_permissions_path(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        $skip_segments = [
            '/.git/',
            '/node_modules/',
        ];

        foreach ($skip_segments as $segment) {
            if (str_contains($normalized, $segment)) {
                return true;
            }
        }

        return false;
    }

    private function should_keep_executable(string $path): bool
    {
        $perms = @fileperms($path);
        if ($perms === false) {
            return false;
        }

        // Preserve executable files if they were already executable.
        return (bool) ($perms & 0x0040) || (bool) ($perms & 0x0008) || (bool) ($perms & 0x0001);
    }
}
