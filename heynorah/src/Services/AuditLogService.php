<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use HeyNorah\Core\Plugin;

/**
 * Audit Logging Service
 *
 * Logs critical security-related actions for compliance and forensics
 */
class AuditLogService
{
    private function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'heynorah_audit_log';
    }

    /**
     * Log settings change
     *
     * @param string $key Settings key that changed
     * @param string $old_value Previous value (partial)
     * @param string $new_value New value (partial)
     * @param int $user_id User who made the change
     */
    public function log_settings_change(string $key, string $old_value, string $new_value, int $user_id): void
    {
        $this->log_action('settings_update', [
            'key' => $key,
            'old_value' => $this->mask_sensitive_value($old_value),
            'new_value' => $this->mask_sensitive_value($new_value),
        ], $user_id);
    }

    /**
     * Log API connection change
     *
     * @param string $action 'connect' or 'disconnect'
     * @param int $user_id User who made the change
     */
    public function log_api_connection(string $action, int $user_id): void
    {
        $this->log_action('api_connection', [
            'action' => $action,
        ], $user_id);
    }

    /**
     * Log data clear/reset action
     *
     * @param string $type Type of data cleared (logs, settings, etc.)
     * @param int $user_id User who made the change
     */
    public function log_data_clear(string $type, int $user_id): void
    {
        $this->log_action('data_clear', [
            'type' => $type,
        ], $user_id);
    }

    /**
     * Log permission fix action
     *
     * @param int $user_id User who made the change
     * @param array $details Details about the fix (fixed count, errors, etc.)
     */
    public function log_permission_fix(int $user_id, array $details): void
    {
        $this->log_action('permission_fix', $details, $user_id);
    }

    /**
     * Generic audit log entry
     *
     * @param string $action Action type
     * @param array $details Action details
     * @param int $user_id User ID
     */
    private function log_action(string $action, array $details, int $user_id): void
    {
        global $wpdb;
        $table = $this->get_table_name();

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        if (!$table_exists) {
            // Table doesn't exist yet, skip logging
            return;
        }

        $wpdb->insert($table, [
            'action' => $action,
            'user_id' => $user_id,
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
            'created_at' => current_time('mysql'),
        ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    }

    /**
     * Mask sensitive values for logging
     *
     * @param string $value Value to mask
     * @return string Masked value
     */
    private function mask_sensitive_value(string $value): string
    {
        if (empty($value) || strlen($value) <= 6) {
            return '***';
        }

        // Show first 3 and last 3 characters
        return substr($value, 0, 3) . '***' . substr($value, -3);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check for proxy headers (if behind load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }

        return $ip;
    }

    /**
     * Get user agent
     *
     * @return string User agent string
     */
    private function get_user_agent(): string
    {
        return sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }

    /**
     * Get recent audit logs
     *
     * @param int $limit Number of logs to retrieve
     * @return array Audit logs
     */
    public function get_recent_logs(int $limit = 50): array
    {
        global $wpdb;
        $table = $this->get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i ORDER BY created_at DESC LIMIT %d",
                $table,
                $limit
            ),
            ARRAY_A
        );

        // Decode JSON details
        foreach ($results as &$row) {
            if (!empty($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
        }

        return $results ?: [];
    }
}
