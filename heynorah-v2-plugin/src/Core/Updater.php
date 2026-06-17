<?php
declare(strict_types=1);

namespace HeyNorah\Core;

/**
 * Public distribution updater.
 *
 * The development repository stays private. WordPress sites check a small
 * public manifest that points to a packaged ZIP containing only runtime files.
 */
class Updater
{
    private const MANIFEST_URL = 'https://raw.githubusercontent.com/fuelmedia/heynorah-plugin/main/update.json';

    private string $pluginBasename;
    private string $pluginSlug;

    public function __construct(string $pluginFile)
    {
        $this->pluginBasename = plugin_basename($pluginFile);
        $this->pluginSlug = dirname($this->pluginBasename);
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
    }

    /**
     * @param object|mixed $transient
     * @return object|mixed
     */
    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }
        /** @var \stdClass $transient */

        $manifest = $this->fetch_manifest();
        if (!$manifest) {
            return $transient;
        }

        $latest_version = (string) ($manifest['version'] ?? '');
        $package_url = (string) ($manifest['package_url'] ?? '');

        if ($latest_version === '' || $package_url === '') {
            return $transient;
        }

        if (!version_compare($latest_version, $this->current_version(), '>')) {
            return $transient;
        }

        $update = (object) [
            'id' => $this->pluginBasename,
            'slug' => $this->pluginSlug,
            'plugin' => $this->pluginBasename,
            'new_version' => $latest_version,
            'url' => (string) ($manifest['homepage'] ?? 'https://github.com/fuelmedia/heynorah-plugin'),
            'package' => esc_url_raw($package_url),
            'tested' => (string) ($manifest['tested'] ?? ''),
            'requires_php' => (string) ($manifest['requires_php'] ?? '8.2'),
        ];

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        $transient->response[$this->pluginBasename] = $update;

        return $transient;
    }

    /**
     * @param false|object|array<mixed> $result
     * @param string $action
     * @param object $args
     * @return false|object|array<mixed>
     */
    public function plugin_information($result, string $action, object $args)
    {
        if ($action !== 'plugin_information' || (($args->slug ?? '') !== $this->pluginSlug)) {
            return $result;
        }

        $manifest = $this->fetch_manifest();
        if (!$manifest) {
            return $result;
        }

        return (object) [
            'name' => (string) ($manifest['name'] ?? 'HeyNorah V2 Plugin'),
            'slug' => $this->pluginSlug,
            'version' => (string) ($manifest['version'] ?? $this->current_version()),
            'author' => (string) ($manifest['author'] ?? 'HeyNorah AI'),
            'homepage' => (string) ($manifest['homepage'] ?? 'https://github.com/fuelmedia/heynorah-plugin'),
            'download_link' => (string) ($manifest['package_url'] ?? ''),
            'requires' => (string) ($manifest['requires'] ?? ''),
            'requires_php' => (string) ($manifest['requires_php'] ?? '8.2'),
            'tested' => (string) ($manifest['tested'] ?? ''),
            'sections' => [
                'description' => (string) ($manifest['description'] ?? 'HeyNorah WordPress inventory connector.'),
                'changelog' => (string) ($manifest['changelog'] ?? ''),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch_manifest(): ?array
    {
        $cache_key = 'heynorah_plugin_update_manifest';
        $cached = get_site_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::MANIFEST_URL, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => 8,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        set_site_transient($cache_key, $decoded, 6 * HOUR_IN_SECONDS);

        return $decoded;
    }

    private function current_version(): string
    {
        return defined('HEYNORAH_PLUGIN_VERSION') ? (string) constant('HEYNORAH_PLUGIN_VERSION') : '0.0.0';
    }
}
