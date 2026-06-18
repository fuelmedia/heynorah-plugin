<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HeyNorah\Core\Config;
use HeyNorah\Utils\Environment;
use HeyNorah\Services\SettingsService;

/**
 * Search Proxy Controller
 *
 * SECURITY: Proxies Meilisearch requests to prevent master key exposure
 * NOTE: Public endpoint - does NOT extend BaseController (no CSRF check needed)
 */
class SearchController extends WP_REST_Controller
{
    public function register_routes(): void
    {
        register_rest_route('heynorah/v1', '/search', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'proxy_search'],
                'permission_callback' => '__return_true', // Public endpoint
            ],
        ]);
    }

    /**
     * Proxy search requests to Meilisearch
     *
     * SECURITY: Master key never exposed to frontend
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function proxy_search(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        // Validate required params
        if (!isset($params['query']) || !isset($params['index'])) {
            return new WP_Error(
                'rest_missing_params',
                'Missing required parameters: query, index',
                ['status' => 400]
            );
        }

        $query = sanitize_text_field($params['query']);
        $index_name = sanitize_text_field($params['index']);
        $limit = isset($params['limit']) ? (int) $params['limit'] : 20;
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $filters = $params['filters'] ?? null;

        // Get Meilisearch credentials
        $is_development = Environment::is_development();
        $settingsService = new SettingsService();
        $connection_status = $settingsService->get_connection_status();
        $organization = $connection_status['organization'] ?? null;
        $organization_search_key = is_array($organization)
            ? (string) ($organization['meilisearchPublicKey'] ?? '')
            : '';

        if ($is_development) {
            $ms_url = Config::DEV_MS_URL;
            $ms_key = $organization_search_key !== ''
                ? $organization_search_key
                : Config::DEV_MS_SEARCH_KEY;
        } else {
            // Production: use the connected organization's configured search host.
            $configured_ms_url = $settingsService->get('meilisearch_url');
            $ms_url = $configured_ms_url !== '' ? $configured_ms_url : Config::PROD_MS_URL;
            $ms_key = $organization_search_key;

            // If no public key available, return error
            if (empty($ms_key)) {
                return new WP_Error(
                    'meilisearch_config_error',
                    'Meilisearch public key not available',
                    ['status' => 500]
                );
            }
        }

        // Build search request
        $search_url = rtrim($ms_url, '/') . '/indexes/' . $index_name . '/search';

        $body = [
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($filters) {
            $body['filter'] = $filters;
        }

        // Make request to Meilisearch
        $response = wp_remote_post($search_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $ms_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'rest_search_failed',
                'Search request failed: ' . $response->get_error_message(),
                ['status' => 500]
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'rest_search_error',
                'Meilisearch returned error',
                ['status' => $status_code]
            );
        }

        $results = json_decode($response_body, true);

        return rest_ensure_response($results);
    }
}
