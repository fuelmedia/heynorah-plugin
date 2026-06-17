<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

/**
 * Base REST API Controller with Security Features
 *
 * SECURITY: Provides explicit nonce validation for cookie-based auth
 */
abstract class BaseController extends WP_REST_Controller
{
    /**
     * Verify WordPress REST API nonce
     *
     * SECURITY: Explicit CSRF protection for cookie-based authentication
     * @param WP_REST_Request $request The request object
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    protected function verify_nonce(WP_REST_Request $request)
    {
        // Cookie-based auth için nonce kontrolü
        $nonce = $request->get_header('X-WP-Nonce');

        if (empty($nonce)) {
            return new WP_Error(
                'rest_missing_nonce',
                'Missing security nonce',
                ['status' => 403]
            );
        }

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_invalid_nonce',
                'Invalid security nonce',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check if request is using cookie-based authentication
     *
     * @param WP_REST_Request $request The request object
     * @return bool True if using cookies
     */
    protected function is_cookie_auth(WP_REST_Request $request): bool
    {
        // Cookie auth kullanılıyorsa nonce gerekli
        // Authorization header yoksa cookie auth varsayılır
        $auth_header = $request->get_header('Authorization');
        return empty($auth_header);
    }
}
