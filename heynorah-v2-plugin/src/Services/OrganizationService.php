<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use HeyNorah\Core\Plugin;
use HeyNorah\Utils\Environment;
use HeyNorah\Utils\Logger;

class OrganizationService
{
    private SettingsService $settingsService;

    public function __construct(?SettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?? new SettingsService();
    }

    public function connect_api_key(string $api_key, string $webhook_url): array
    {
        $endpoint = trailingslashit($this->resolve_api_base_url()) . 'api/integrations/wordpress/connect';
        $preferred_scheme = Environment::is_development() ? 'http' : 'https';

        $plugin_version = defined('HEYNORAH_PLUGIN_VERSION')
            ? (string) HEYNORAH_PLUGIN_VERSION
            : '1.0.0';

        $site_url = $this->normalize_url_scheme((string) get_site_url(), $preferred_scheme);
        $normalized_webhook_url = $this->normalize_url_scheme($webhook_url, $preferred_scheme);

        return $this->post_json(
            $endpoint,
            [
                'siteUrl' => $site_url,
                'webhookUrl' => $normalized_webhook_url,
                'pluginVersion' => $plugin_version,
                'wpVersion' => get_bloginfo('version'),
            ],
            $api_key,
            '[HeyNorah Connect]'
        );
    }

    public function verify_domain(string $api_key, string $endpoint_key, string $challenge_id): array
    {
        $endpoint = trailingslashit($this->resolve_api_base_url()) . 'api/integrations/wordpress/verify-domain';

        return $this->post_json(
            $endpoint,
            [
                'endpointKey' => $endpoint_key,
                'challengeId' => $challenge_id,
            ],
            $api_key,
            '[HeyNorah Verify]'
        );
    }

    private function resolve_api_base_url(): string
    {
        $configured = trim($this->settingsService->get('api_base_url'));
        return $configured !== '' ? untrailingslashit($configured) : Plugin::get_api_base_url();
    }

    private function post_json(
        string $endpoint,
        array $payload,
        string $api_key,
        string $log_prefix
    ): array {
        $site_url = get_site_url();
        $is_development = Environment::is_development();
        $masked_key = strlen($api_key) > 8
            ? substr($api_key, 0, 4) . '***' . substr($api_key, -4)
            : '***';

        Logger::log('connect', 'outbound request', [
            'prefix' => $log_prefix,
            'endpoint' => $endpoint,
            'siteUrl' => $site_url,
            'apiKey' => $masked_key,
            'payload' => $payload,
        ]);

        if ($is_development) {
            error_log($log_prefix . ' Endpoint: ' . $endpoint);
            error_log($log_prefix . ' Site URL: ' . $site_url);
            error_log($log_prefix . ' API Key (masked): ' . $masked_key);
            error_log($log_prefix . ' Request Body: ' . wp_json_encode($payload));
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'x-api-key' => $api_key,
                'content-type' => 'application/json',
                'referer' => $site_url,
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            Logger::log('connect', 'wp_remote_post failed', [
                'prefix' => $log_prefix,
                'endpoint' => $endpoint,
                'error' => $error,
            ]);
            if ($is_development) {
                error_log($log_prefix . ' WP Error: ' . $error);
            }

            return [
                'success' => false,
                'status' => 0,
                'error' => $error,
                'data' => null,
                'raw' => null,
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        Logger::log('connect', 'response received', [
            'prefix' => $log_prefix,
            'endpoint' => $endpoint,
            'status' => $status_code,
            'body' => $body,
        ]);

        if ($is_development) {
            error_log($log_prefix . ' Response Status: ' . (string) $status_code);
            error_log($log_prefix . ' Response Body: ' . $body);
        }

        $decoded = json_decode($body, true);
        $json_ok = json_last_error() === JSON_ERROR_NONE && is_array($decoded);

        if (!$json_ok) {
            $error = 'Invalid JSON response: ' . json_last_error_msg();
            Logger::log('connect', 'response is not valid json', [
                'prefix' => $log_prefix,
                'endpoint' => $endpoint,
                'status' => $status_code,
                'jsonError' => json_last_error_msg(),
                'body' => $body,
            ]);
            if ($is_development) {
                error_log($log_prefix . ' JSON Decode Error: ' . json_last_error_msg());
            }

            return [
                'success' => false,
                'status' => $status_code,
                'error' => $error,
                'data' => null,
                'raw' => $body,
            ];
        }

        $api_data = $this->normalize_payload_data($decoded);
        $api_success = $this->resolve_success($decoded, $api_data, $status_code);
        $api_message = $this->extract_error_message($decoded);

        if ($is_development) {
            error_log($log_prefix . ' Parsed Response: ' . wp_json_encode($decoded) . ' ' . print_r($decoded, true));
        }

        if ($status_code < 200 || $status_code >= 300 || !$api_success) {
            $error = $api_message !== ''
                ? $api_message
                : (
                    ($status_code >= 200 && $status_code < 300)
                    ? 'Unexpected API response format.'
                    : 'HTTP ' . $status_code
                );
            Logger::log('connect', 'api returned non-success', [
                'prefix' => $log_prefix,
                'endpoint' => $endpoint,
                'status' => $status_code,
                'success' => $api_success,
                'error' => $error,
                'decoded' => $decoded,
            ]);

            return [
                'success' => false,
                'status' => $status_code,
                'error' => $error,
                'data' => $api_data,
                'raw' => $decoded,
            ];
        }

        Logger::log('connect', 'api returned success', [
            'prefix' => $log_prefix,
            'endpoint' => $endpoint,
            'status' => $status_code,
        ]);

        return [
            'success' => true,
            'status' => $status_code,
            'error' => '',
            'data' => $api_data,
            'raw' => $decoded,
        ];
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private function normalize_payload_data(array $decoded): array
    {
        if (is_array($decoded['data'] ?? null)) {
            return $decoded['data'];
        }

        // Some backends return payload without wrapping under "data".
        if ($this->looks_like_integration_payload($decoded)) {
            return $decoded;
        }

        return [];
    }

    private function normalize_url_scheme(string $url, string $scheme): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed) || empty($parsed['scheme'])) {
            return $url;
        }

        return (string) set_url_scheme($url, $scheme);
    }

    /**
     * @param array<string,mixed> $decoded
     * @param array<string,mixed> $api_data
     */
    private function resolve_success(array $decoded, array $api_data, int $status_code): bool
    {
        if (array_key_exists('success', $decoded)) {
            if (!empty($decoded['success'])) {
                return true;
            }

            // Some integrations may send success=false while still returning
            // valid connect payload for pending verification flows.
            return $status_code >= 200
                && $status_code < 300
                && $this->looks_like_integration_payload($api_data);
        }

        if (array_key_exists('ok', $decoded)) {
            return !empty($decoded['ok']);
        }

        if (array_key_exists('status', $decoded)) {
            $status = $decoded['status'];
            if (is_string($status)) {
                $normalized = strtolower(trim($status));
                if (in_array($normalized, ['ok', 'success', 'succeeded', 'acknowledged', 'verified'], true)) {
                    return true;
                }
            }
        }

        // Fallback for schema variations: treat 2xx + expected payload as success.
        return $status_code >= 200
            && $status_code < 300
            && $this->looks_like_integration_payload($api_data);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function looks_like_integration_payload(array $payload): bool
    {
        if (array_key_exists('validated', $payload)) {
            return true;
        }

        $webhook = is_array($payload['webhook'] ?? null) ? $payload['webhook'] : [];
        if (empty($webhook)) {
            return false;
        }

        $secret = trim((string) ($webhook['secret'] ?? ''));
        $endpoint_key = trim((string) ($webhook['endpointKey'] ?? ''));
        $challenge_id = trim((string) ($webhook['challengeId'] ?? ''));
        $challenge = is_array($webhook['challenge'] ?? null) ? $webhook['challenge'] : [];
        $nested_challenge_id = trim((string) ($challenge['id'] ?? $challenge['challengeId'] ?? ''));

        return $secret !== ''
            || $endpoint_key !== ''
            || $challenge_id !== ''
            || $nested_challenge_id !== '';
    }

    /**
     * @param array<string,mixed> $decoded
     */
    private function extract_error_message(array $decoded): string
    {
        $direct_message = trim((string) ($decoded['message'] ?? ''));
        if ($direct_message !== '') {
            return $direct_message;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $data = $decoded['data'];
            $data_message = trim((string) ($data['message'] ?? ''));
            if ($data_message !== '') {
                return $data_message;
            }

            $data_error = trim((string) ($data['error'] ?? ''));
            if ($data_error !== '') {
                return $data_error;
            }

            $data_reason = trim((string) ($data['reason'] ?? ''));
            if ($data_reason !== '') {
                return $data_reason;
            }

            if (isset($data['errors']) && is_array($data['errors'])) {
                $data_errors = $this->collect_error_messages($data['errors']);
                if (!empty($data_errors)) {
                    return implode(' | ', $data_errors);
                }
            }
        }

        if (isset($decoded['error'])) {
            if (is_string($decoded['error'])) {
                $error = trim($decoded['error']);
                if ($error !== '') {
                    return $error;
                }
            }

            if (is_array($decoded['error'])) {
                $nested = trim((string) ($decoded['error']['message'] ?? ''));
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            $messages = $this->collect_error_messages($decoded['errors']);
            if (!empty($messages)) {
                return implode(' | ', $messages);
            }
        }

        if (isset($decoded['details']) && is_array($decoded['details'])) {
            $detail_messages = $this->collect_error_messages($decoded['details']);
            if (!empty($detail_messages)) {
                return implode(' | ', $detail_messages);
            }
        }

        return '';
    }

    /**
     * @param array<int|string,mixed> $items
     * @return array<int,string>
     */
    private function collect_error_messages(array $items): array
    {
        $messages = [];

        foreach ($items as $entry) {
            if (is_string($entry)) {
                $entry = trim($entry);
                if ($entry !== '') {
                    $messages[] = $entry;
                }
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $message = trim((string) ($entry['message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }

            $reason = trim((string) ($entry['reason'] ?? ''));
            if ($reason !== '') {
                $messages[] = $reason;
            }

            if (isset($entry['path']) && is_array($entry['path'])) {
                $path_parts = array_filter(array_map(
                    static fn($part): string => trim((string) $part),
                    $entry['path']
                ));
                $path = implode('.', $path_parts);
                if ($path !== '' && $message !== '') {
                    $messages[] = $path . ': ' . $message;
                }
            }
        }

        return array_values(array_unique($messages));
    }
}
