<?php
/**
 * Plugin Name: HeyNorah
 * Description: HeyNorah V2 WordPress inventory and marine pack connector.
 * Version: 2.0.3
 * Requires at least: 6.3
 * Requires PHP: 8.2
 * Update URI: https://github.com/fuelmedia/heynorah-plugin
 * Author: HeyNorah AI
 */

if (!defined('ABSPATH'))
    exit;

if (!defined('HEYNORAH_PLUGIN_VERSION')) {
 define('HEYNORAH_PLUGIN_VERSION', '2.0.3');
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

use HeyNorah\Core\Plugin;
use HeyNorah\Core\Activator;
use HeyNorah\Core\Installer;
use HeyNorah\Core\Updater;

// SECURITY: Load config file early (before plugin initialization)
Installer::load_config();

(new Updater(__FILE__))->register();

// SECURITY: Register activation/deactivation/uninstall hooks
register_activation_hook(__FILE__, [Installer::class, 'activate']);
register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Installer::class, 'uninstall']);

add_action('plugins_loaded', function () {
    (new Plugin())->run();
});
