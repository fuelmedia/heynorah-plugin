<?php
declare(strict_types=1);

namespace HeyNorah\Database;

use HeyNorah\Core\Plugin;

class Schema
{
    public static function create_tables(): void
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $schemas = [
            self::get_logs_table_sql(),
            self::get_settings_table_sql(),
            self::get_audit_log_table_sql(),
            self::get_webhook_events_table_sql(),
        ];

        foreach ($schemas as $sql) {
            dbDelta($sql);
        }

        update_option('heynorah_db_version', '1.3.0');
    }

    private static function get_logs_table_sql(): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Plugin::TABLE_LOGS;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event varchar(100) NOT NULL,
            heynorah_id varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            response_code int(3) NOT NULL,
            payload longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY heynorah_id (heynorah_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
    }

    private static function get_settings_table_sql(): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Plugin::TABLE_SETTINGS;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_api_key varchar(255) DEFAULT NULL,
            api_base_url varchar(255) DEFAULT NULL,
            meilisearch_url varchar(255) DEFAULT NULL,
            cpt_slug varchar(100) DEFAULT NULL,
            taxonomy_slug varchar(100) DEFAULT NULL,
            webhook_secret longtext DEFAULT NULL,
            organization_data longtext DEFAULT NULL,
            user_data longtext DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            validated tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY validated (validated),
            KEY verified_at (verified_at)
        ) $charset_collate;";
    }

    private static function get_audit_log_table_sql(): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Plugin::TABLE_AUDIT_LOG;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
    }

    private static function get_webhook_events_table_sql(): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Plugin::TABLE_WEBHOOK_EVENTS;
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(191) NOT NULL,
            delivery_id varchar(191) DEFAULT '' NOT NULL,
            event_type varchar(100) DEFAULT '' NOT NULL,
            item_id varchar(100) DEFAULT '' NOT NULL,
            status varchar(30) DEFAULT '' NOT NULL,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_error text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY delivery_id (delivery_id),
            KEY event_type (event_type),
            KEY processed_at (processed_at)
        ) $charset_collate;";
    }
}
