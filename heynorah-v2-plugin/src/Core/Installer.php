<?php
declare(strict_types=1);

namespace HeyNorah\Core;

/**
 * Plugin Installation & Configuration Management
 *
 * WordFence-style approach: Create separate config file in wp-content
 * instead of modifying wp-config.php
 */
class Installer
{
    private const CONFIG_FILE = 'heynorah-config.php';

    /**
     * Get config file path
     */
    private static function get_config_file_path(): string
    {
        return WP_CONTENT_DIR . '/' . self::CONFIG_FILE;
    }

    /**
     * Plugin activation hook
     */
    public static function activate(): void
    {
        // Create config file if it doesn't exist
        if (!self::config_file_exists()) {
            self::create_config_file();
        }

        // Set proper permissions
        self::set_secure_permissions();

        // Register CPT and flush permalinks
        $postTypeManager = new PostTypeManager();
        $postTypeManager->register_inventory_cpt();
        flush_rewrite_rules(false);
    }

    /**
     * Check if config file exists
     */
    public static function config_file_exists(): bool
    {
        return file_exists(self::get_config_file_path());
    }

    /**
     * Create config file with secure random keys
     */
    private static function create_config_file(): bool
    {
        $config_path = self::get_config_file_path();

        // Generate secure random key for internal encryption
        $internal_key = base64_encode(random_bytes(32));

        $config_content = <<<PHP
<?php
/**
 * HeyNorah WordPress Plugin Configuration
 *
 * Generated: %s
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HEYNORAH_INTERNAL_KEY', '{$internal_key}');

PHP;

        $config_content = sprintf($config_content, current_time('mysql'));

        // Write config file
        $result = file_put_contents($config_path, $config_content);

        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Set secure file permissions (0600 - owner read/write only)
     */
    private static function set_secure_permissions(): void
    {
        $config_path = self::get_config_file_path();

        if (file_exists($config_path)) {
            // 0600 = Owner can read and write, nobody else can do anything
            @chmod($config_path, 0600);
        }
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate(): void
    {
        // Don't delete config file on deactivation
        // User might reactivate and want to keep settings
    }

    /**
     * Plugin uninstall hook
     */
    public static function uninstall(): void
    {
        // Delete config file on uninstall
        $config_path = self::get_config_file_path();

        if (file_exists($config_path)) {
            @unlink($config_path);
        }
    }

    /**
     * Load config file
     */
    public static function load_config(): void
    {
        $config_path = self::get_config_file_path();

        if (file_exists($config_path)) {
            require_once $config_path;
        }
    }

    /**
     * Check if constants are defined
     */
    public static function constants_defined(): bool
    {
        return defined('HEYNORAH_INTERNAL_KEY');
    }

    /**
     * Get admin notice for missing config
     */
    public static function get_config_missing_notice(): string
    {
        $config_path = self::get_config_file_path();

        return sprintf(
            '<div class="notice notice-error"><p><strong>HeyNorah Security Warning:</strong> Config file not found at <code>%s</code>. Please deactivate and reactivate the plugin to generate it.</p></div>',
            esc_html($config_path)
        );
    }
}
