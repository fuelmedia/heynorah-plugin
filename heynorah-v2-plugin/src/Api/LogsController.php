<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use HeyNorah\Core\Plugin;

class LogsController extends WP_REST_Controller
{
    public function register_routes(): void
    {
        register_rest_route('heynorah/v1', '/logs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_logs'],
                'permission_callback' => fn() => current_user_can('manage_options'),
                'args' => [
                    'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                    'per_page' => ['default' => 10, 'sanitize_callback' => 'absint'],
                ]
            ],
        ]);
    }

    public function get_logs(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . Plugin::TABLE_LOGS;

        $page = $request->get_param('page') ? (int) $request->get_param('page') : 1;
        $per_page = $request->get_param('per_page') ? (int) $request->get_param('per_page') : 10;
        $offset = ($page - 1) * $per_page;

        // SECURITY FIX: Use wpdb->prepare() with %i for table identifier
        $total_items = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM %i", $table));
        $total_pages = ceil($total_items / $per_page);

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d", $table, $per_page, $offset)
        );

        foreach ($results as $row) {
            $row->payload = json_decode($row->payload);
        }

        return rest_ensure_response([
            'data' => $results,
            'meta' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $total_items,
                'total_pages' => $total_pages
            ]
        ]);
    }
}