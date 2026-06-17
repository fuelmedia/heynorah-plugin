<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use HeyNorah\Services\StatsService;

class StatsController extends WP_REST_Controller
{
    public function register_routes(): void
    {
        register_rest_route('heynorah/v1', '/stats/dashboard', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_stats'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);
    }

    public function get_dashboard_stats(WP_REST_Request $request): WP_REST_Response
    {
        $service = new StatsService();
        $data = $service->get_dashboard_stats();

        return rest_ensure_response($data);
    }
}