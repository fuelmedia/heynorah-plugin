<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use HeyNorah\Core\Plugin;
use InvalidArgumentException;

class StatsService
{
    public function get_dashboard_stats(): array
    {
        global $wpdb;
        $cpt = Plugin::CPT_INVENTORY;
        $table_logs = $wpdb->prefix . Plugin::TABLE_LOGS;

        $total_items = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft')",
            $cpt
        ));
        $active_items = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $cpt
        ));
        $draft_items = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'draft'",
            $cpt
        ));

        // SECURITY FIX: Whitelist-based status type instead of raw SQL
        $total_trend = $this->calculate_post_growth('all');
        $active_trend = $this->calculate_post_growth('publish');

        // SECURITY FIX: Use wpdb->prepare() with %i for table identifier
        $last_log = $wpdb->get_row($wpdb->prepare("SELECT created_at, status, event FROM %i ORDER BY id DESC LIMIT 1", $table_logs));

        $errors_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM %i WHERE status = 'error' AND created_at >= NOW() - INTERVAL 24 HOUR", $table_logs));
        $errors_yesterday = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM %i WHERE status = 'error' AND created_at < NOW() - INTERVAL 24 HOUR AND created_at >= NOW() - INTERVAL 48 HOUR", $table_logs));

        $error_trend = 0;
        if ($errors_yesterday > 0) {
            $error_trend = (($errors_today - $errors_yesterday) / $errors_yesterday) * 100;
        } elseif ($errors_today > 0) {
            $error_trend = 100;
        }

        return [
            'inventory' => [
                'total' => $total_items,
                'total_trend' => round($total_trend, 1),
                'active' => $active_items,
                'active_trend' => round($active_trend, 1),
                'draft' => $draft_items,
            ],
            'sync' => [
                'last_sync_time' => $last_log ? $last_log->created_at : null,
                'last_sync_status' => $last_log ? $last_log->status : 'pending',
                'last_event' => $last_log ? $last_log->event : 'None',
                'recent_errors' => $errors_today,
                'error_trend' => round($error_trend, 1)
            ]
        ];
    }

    /**
     * Calculate post growth trend
     *
     * SECURITY: Uses whitelist approach to prevent SQL injection
     * @param string $status_type Allowed: 'all', 'publish', 'draft'
     * @return float Growth percentage
     * @throws InvalidArgumentException if status_type is invalid
     */
    private function calculate_post_growth(string $status_type): float
    {
        global $wpdb;
        $cpt = Plugin::CPT_INVENTORY;

        // SECURITY: Whitelist-based SQL condition
        $allowed_conditions = [
            'all' => "post_status IN ('publish', 'draft')",
            'publish' => "post_status = 'publish'",
            'draft' => "post_status = 'draft'"
        ];

        if (!isset($allowed_conditions[$status_type])) {
            throw new InvalidArgumentException('Invalid status type: ' . esc_html($status_type));
        }

        $status_condition = $allowed_conditions[$status_type];

        $this_period = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
            WHERE post_type = %s
            AND $status_condition
            AND post_date >= NOW() - INTERVAL 30 DAY",
            $cpt
        ));

        $last_period = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
            WHERE post_type = %s
            AND $status_condition
            AND post_date < NOW() - INTERVAL 30 DAY
            AND post_date >= NOW() - INTERVAL 60 DAY",
            $cpt
        ));

        if ($last_period === 0) {
            return $this_period > 0 ? 100.0 : 0.0;
        }

        return (($this_period - $last_period) / $last_period) * 100;
    }
}