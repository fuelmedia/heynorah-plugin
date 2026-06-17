<?php
declare(strict_types=1);

namespace HeyNorah\Utils;

/**
 * Environment Detection Helper
 *
 * Provides reliable environment detection based on domain/host
 */
class Environment
{
    /**
     * Check if current environment is development
     *
     * Development environments include:
     * - localhost
     * - *.local domains
     * - 127.0.0.1
     * - 192.168.* (local network)
     *
     * @return bool True if development environment
     */
    public static function is_development(): bool
    {
        $host = self::get_host();

        return (
            str_contains($host, 'localhost') ||
            str_ends_with($host, '.local') ||
            str_starts_with($host, '127.0.0.1') ||
            str_starts_with($host, '192.168.')
        );
    }

    /**
     * Check if current environment is production
     *
     * @return bool True if production environment
     */
    public static function is_production(): bool
    {
        return !self::is_development();
    }

    /**
     * Get current host
     *
     * @return string Current host/domain
     */
    public static function get_host(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }
}
