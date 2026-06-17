<?php
declare(strict_types=1);

namespace HeyNorah\Utils;

final class Logger
{
    private const LOG_DIR_NAME = 'heynorah';
    private const LOG_FILE_NAME = 'heynorah-plugin.log';

    /**
     * @param array<string,mixed> $context
     */
    public static function log(string $channel, string $message, array $context = []): void
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $line = '[' . $timestamp . '] [' . trim($channel) . '] ' . trim($message);

        if (!empty($context)) {
            $encoded = wp_json_encode(
                self::sanitize_context($context),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if (is_string($encoded) && $encoded !== '') {
                $line .= ' ' . $encoded;
            }
        }

        $line .= PHP_EOL;
        $path = self::get_log_file_path();

        if ($path === '') {
            error_log('[HeyNorah Logger] ' . $line);
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        $written = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('[HeyNorah Logger Fallback] ' . $line);
        }
    }

    public static function get_log_file_path(): string
    {
        $base_dir = '';
        $upload_dir = wp_upload_dir(null, false);

        if (is_array($upload_dir) && empty($upload_dir['error']) && !empty($upload_dir['basedir'])) {
            $base_dir = (string) $upload_dir['basedir'];
        }

        if ($base_dir === '' && defined('WP_CONTENT_DIR')) {
            $base_dir = WP_CONTENT_DIR . '/uploads';
        }

        if ($base_dir === '') {
            return '';
        }

        return rtrim($base_dir, '/\\') . '/' . self::LOG_DIR_NAME . '/' . self::LOG_FILE_NAME;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function sanitize_context(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalized_key = strtolower((string) $key);
            $is_sensitive = str_contains($normalized_key, 'secret')
                || str_contains($normalized_key, 'token')
                || str_contains($normalized_key, 'api_key')
                || str_contains($normalized_key, 'authorization')
                || str_contains($normalized_key, 'signature');

            if ($is_sensitive) {
                $sanitized[(string) $key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string,mixed> $value */
                $sanitized[(string) $key] = self::sanitize_context($value);
                continue;
            }

            if (is_object($value)) {
                $sanitized[(string) $key] = '[object]';
                continue;
            }

            if (is_string($value) && strlen($value) > 1500) {
                $sanitized[(string) $key] = substr($value, 0, 1500) . '...[truncated]';
                continue;
            }

            $sanitized[(string) $key] = $value;
        }

        return $sanitized;
    }
}

