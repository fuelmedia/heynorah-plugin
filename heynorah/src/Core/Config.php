<?php
declare(strict_types=1);

namespace HeyNorah\Core;
use RuntimeException;

/**
 * Security Configuration
 *
 * SECURITY: Values are loaded from wp-content/heynorah-config.php
 * This file no longer contains hardcoded secrets.
 */
class Config
{
    /**
     * Get internal encryption key from config file
     * @throws RuntimeException if constant not defined
     */
    public static function get_internal_key(): string
    {
        if (!defined('HEYNORAH_INTERNAL_KEY')) {
            throw new RuntimeException(
                'HEYNORAH_INTERNAL_KEY not defined. Please deactivate and reactivate the plugin.'
            );
        }
        return HEYNORAH_INTERNAL_KEY;
    }

    // Development Meilisearch configuration
    public const DEV_MS_URL = 'http://localhost:7700';
    public const DEV_MS_KEY = 'MOkGsfWBIm54yHsAp8x55KqNVs4WuX9fg8xQlLriNOM=';
    public const DEV_MS_SEARCH_KEY = 'MOkGsfWBIm54yHsAp8x55KqNVs4WuX9fg8xQlLriNOM=';
    public const PROD_MS_URL = 'https://latest.search.x.heynorah.ai';
}