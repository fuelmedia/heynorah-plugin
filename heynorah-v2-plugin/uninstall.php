<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * @link       https://heynorah.ai
 * @since      1.0.0
 *
 * @package    HeyNorah
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * HeyNorah Plugin Uninstaller
 *
 * This file runs when the plugin is uninstalled (not just deactivated).
 * It removes all plugin data from the database to ensure clean removal.
 */

global $wpdb;

// Define table names
$logs_table = $wpdb->prefix . 'heynorah_webhook_logs';
$settings_table = $wpdb->prefix . 'heynorah_settings';
$events_table = $wpdb->prefix . 'heynorah_webhook_events';

/**
 * 1. Drop Custom Tables
 */
$wpdb->query("DROP TABLE IF EXISTS $logs_table");
$wpdb->query("DROP TABLE IF EXISTS $settings_table");
$wpdb->query("DROP TABLE IF EXISTS $events_table");

/**
 * 2. Delete WordPress Options
 */
delete_option('heynorah_db_version');
delete_option('heynorah_webhook_meta');

/**
 * 3. Delete Custom Post Type Posts
 */
$custom_post_type = 'heynorah_inventory';

// Get all posts of this type
$posts = get_posts([
    'post_type' => $custom_post_type,
    'posts_per_page' => -1,
    'post_status' => 'any',
]);

// Delete each post permanently
foreach ($posts as $post) {
    // Delete post meta
    delete_post_meta($post->ID, '_thumbnail_id');

    // Force delete (bypass trash)
    wp_delete_post($post->ID, true);
}

/**
 * 4. Clear Scheduled Cron Jobs
 */
$timestamp = wp_next_scheduled('heynorah_daily_cleanup');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'heynorah_daily_cleanup');
}

// Clear all instances of the scheduled event
wp_clear_scheduled_hook('heynorah_daily_cleanup');

/**
 * 5. Delete Transients
 */
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_heynorah_%'");
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_heynorah_%'");

/**
 * 6. Flush Rewrite Rules
 * (WordPress will automatically flush rewrite rules after uninstall)
 */
flush_rewrite_rules();

/**
 * 7. Clean up multisite installations (if applicable)
 */
if (is_multisite()) {
    // Get all sites in the network
    $sites = get_sites([
        'number' => 0, // Get all sites
    ]);

    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);

        // Repeat cleanup for each site
        $site_logs_table = $wpdb->prefix . 'heynorah_webhook_logs';
        $site_settings_table = $wpdb->prefix . 'heynorah_settings';
        $site_events_table = $wpdb->prefix . 'heynorah_webhook_events';

        $wpdb->query("DROP TABLE IF EXISTS $site_logs_table");
        $wpdb->query("DROP TABLE IF EXISTS $site_settings_table");
        $wpdb->query("DROP TABLE IF EXISTS $site_events_table");

        delete_option('heynorah_db_version');
        delete_option('heynorah_webhook_meta');

        // Delete posts for this site
        $site_posts = get_posts([
            'post_type' => $custom_post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        foreach ($site_posts as $post) {
            delete_post_meta($post->ID, '_thumbnail_id');
            wp_delete_post($post->ID, true);
        }

        // Clear scheduled events for this site
        $site_timestamp = wp_next_scheduled('heynorah_daily_cleanup');
        if ($site_timestamp) {
            wp_unschedule_event($site_timestamp, 'heynorah_daily_cleanup');
        }
        wp_clear_scheduled_hook('heynorah_daily_cleanup');

        // Delete transients for this site
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_heynorah_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_heynorah_%'");

        restore_current_blog();
    }
}

/**
 * 8. Clear any cached data
 */
wp_cache_flush();
