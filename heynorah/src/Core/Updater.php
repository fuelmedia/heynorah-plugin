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
    private const UPDATE_URI = 'https://github.com/fuelmedia/heynorah-plugin';

    private string $pluginBasename;
    private string $pluginSlug;

    public function __construct(string $pluginFile)
    {
        $this->pluginBasename = plugin_basename($pluginFile);
        $this->pluginSlug = dirname($this->pluginBasename);
    }

    public function register(): void
    {
        add_filter('update_plugins_github.com', [$this, 'filter_update_uri'], 10, 4);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
    }

    /**
     * WordPress 5.8+ calls this for plugins with an `Update URI` header.
     *
     * @param array<string,mixed>|false $update
     * @param array<string,mixed> $plugin_data
     * @param string $plugin_file
     * @param string[] $locales
     * @return array<string,mixed>|false
     */
    public function filter_update_uri($update, array $plugin_data, string $plugin_file, array $locales)
    {
        unset($locales);

        if ($plugin_file !== $this->pluginBasename) {
            return $update;
        }

        $manifest = $this->fetch_manifest(true);
        if (!$manifest) {
            return $update;
        }

        $payload = $this->build_update_payload($manifest, (string) ($plugin_data['Version'] ?? $this->current_version()));
        if (!$payload) {
            return $update;
        }

        return (array) $payload;
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

        $manifest = $this->fetch_manifest(true);
        if (!$manifest) {
            return $transient;
        }

        $payload = $this->build_update_payload($manifest, $this->current_version());
        if (!$payload) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        if (!isset($transient->no_update) || !is_array($transient->no_update)) {
            $transient->no_update = [];
        }
        if (!isset($transient->checked) || !is_array($transient->checked)) {
            $transient->checked = [];
        }

        $transient->checked[$this->pluginBasename] = $this->current_version();
        unset($transient->response[$this->pluginBasename], $transient->no_update[$this->pluginBasename]);

        if (version_compare($payload->new_version, $this->current_version(), '>')) {
            $transient->response[$this->pluginBasename] = $payload;
        } else {
            $transient->no_update[$this->pluginBasename] = $payload;
        }

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

        $manifest = $this->fetch_manifest(true);
        if (!$manifest) {
            return $result;
        }

        return (object) [
            'name' => (string) ($manifest['name'] ?? 'HeyNorah'),
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
    private function fetch_manifest(bool $force_refresh = false): ?array
    {
        $cache_key = 'heynorah_plugin_update_manifest';
        $cached = $force_refresh ? null : get_site_transient($cache_key);

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

        set_site_transient($cache_key, $decoded, HOUR_IN_SECONDS);

        return $decoded;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function build_update_payload(array $manifest, string $installed_version): ?object
    {
        $latest_version = (string) ($manifest['version'] ?? '');
        $package_url = (string) ($manifest['package_url'] ?? '');

        if ($latest_version === '' || $package_url === '') {
            return null;
        }

        return (object) [
            'id' => self::UPDATE_URI,
            'slug' => $this->pluginSlug,
            'plugin' => $this->pluginBasename,
            'version' => $latest_version,
            'new_version' => $latest_version,
            'url' => (string) ($manifest['homepage'] ?? self::UPDATE_URI),
            'package' => esc_url_raw($package_url),
            'tested' => (string) ($manifest['tested'] ?? ''),
            'requires_php' => (string) ($manifest['requires_php'] ?? '8.2'),
            'icons' => [],
            'banners' => [],
            'banners_rtl' => [],
            'compatibility' => new \stdClass(),
            'autoupdate' => version_compare($latest_version, $installed_version, '>'),
        ];
    }

    private function current_version(): string
    {
        return defined('HEYNORAH_PLUGIN_VERSION') ? (string) constant('HEYNORAH_PLUGIN_VERSION') : '0.0.0';
    }
}
