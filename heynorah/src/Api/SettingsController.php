<?php
declare(strict_types=1);

namespace HeyNorah\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use HeyNorah\Core\PostTypeManager;
use HeyNorah\Services\SettingsService;
use HeyNorah\Services\OrganizationService;
use HeyNorah\Services\AuditLogService;
use HeyNorah\Services\DomainChallengeService;
use HeyNorah\Utils\Logger;

class SettingsController extends BaseController
{

    private SettingsService $settingsService;
    private PostTypeManager $postTypeManager;
    private OrganizationService $organizationService;
    private AuditLogService $auditLog;
    private DomainChallengeService $domainChallengeService;

    public function __construct()
    {
        $this->settingsService = new SettingsService();
        $this->postTypeManager = new PostTypeManager();
        $this->organizationService = new OrganizationService();
        $this->auditLog = new AuditLogService();
        $this->domainChallengeService = new DomainChallengeService($this->settingsService);
    }

    public function register_routes(): void
    {
        register_rest_route('heynorah/v1', '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);

        register_rest_route('heynorah/v1', '/settings/gravity-forms', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_gravity_forms'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('heynorah/v1', '/settings/retry-verify', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'retry_verify'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function permissions_check(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        $basic_settings = $this->settingsService->get_all_for_api();
        $connection_status = $this->settingsService->get_connection_status();

        return rest_ensure_response(array_merge($basic_settings, [
            'connection_status' => $connection_status,
        ]));
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        Logger::log('settings', 'update_settings called');

        // SECURITY FIX: Explicit nonce validation for cookie-based auth
        if ($this->is_cookie_auth($request)) {
            $nonce_check = $this->verify_nonce($request);
            if (is_wp_error($nonce_check)) {
                Logger::log('settings', 'nonce verification failed', [
                    'code' => $nonce_check->get_error_code(),
                    'message' => $nonce_check->get_error_message(),
                ]);
                return $nonce_check;
            }
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }
        $old_api_key = $this->settingsService->get('site_api_key');

        // SECURITY: Input validation - sadece allowed params kabul et
        $allowed_params = ['site_api_key', 'api_base_url', 'meilisearch_url', 'cpt_slug', 'taxonomy_slug', 'inquiry_form_id', 'test_drive_form_id'];
        $pending_updates = [];
        $audit_entries = [];

        foreach ($params as $key => $value) {
            // SECURITY: Whitelist check
            if (!in_array($key, $allowed_params, true)) {
                continue; // İzin verilmeyen key'leri atla
            }

            // Skip placeholder values
            if (str_contains((string) $value, '***'))
                continue;

            // SECURITY: Key-specific validation ve sanitization
            if ($key === 'cpt_slug') {
                $value = sanitize_title($value);
                if ($value === '') {
                    $value = $this->settingsService->get('cpt_slug') ?: 'inventory';
                }

                // CPT slug validation
                if (empty($value) || strlen($value) > 20) {
                    Logger::log('settings', 'invalid cpt_slug', [
                        'value' => (string) $value,
                    ]);
                    return new WP_Error(
                        'rest_invalid_param',
                        'CPT slug must be 1-20 characters',
                        ['status' => 400]
                    );
                }
            } elseif ($key === 'taxonomy_slug') {
                $value = sanitize_title($value);
                if ($value === '') {
                    $value = $this->settingsService->get('taxonomy_slug') ?: 'type';
                }

                // Taxonomy slug validation
                if (empty($value) || strlen($value) > 20) {
                    Logger::log('settings', 'invalid taxonomy_slug', [
                        'value' => (string) $value,
                    ]);
                    return new WP_Error(
                        'rest_invalid_param',
                        'Taxonomy slug must be 1-20 characters',
                        ['status' => 400]
                    );
                }
            } elseif (in_array($key, ['api_base_url', 'meilisearch_url'], true)) {
                $value = esc_url_raw(untrailingslashit(trim((string) $value)));
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    Logger::log('settings', 'invalid backend url', [
                        'key' => $key,
                        'value' => (string) $value,
                    ]);
                    return new WP_Error(
                        'rest_invalid_param',
                        'Backend URL must be a valid URL',
                        ['status' => 400]
                    );
                }
            } elseif ($key === 'site_api_key') {
                $value = sanitize_text_field($value);
            } elseif (in_array($key, ['inquiry_form_id', 'test_drive_form_id'], true)) {
                $value = trim(sanitize_text_field((string) $value));

                if ($value === 'none') {
                    $value = '';
                }

                if ($value !== '' && !ctype_digit($value)) {
                    Logger::log('settings', 'invalid form id', [
                        'key' => $key,
                        'value' => (string) $value,
                    ]);
                    return new WP_Error(
                        'rest_invalid_param',
                        'Form ID must be a numeric value',
                        ['status' => 400]
                    );
                }
            }

            // Get old value for audit log
            $old_value = $this->settingsService->get($key);
            $new_value = (string) $value;

            if ($old_value === $new_value) {
                continue;
            }

            $pending_updates[$key] = $new_value;
            $audit_entries[] = [
                'key' => $key,
                'old' => $old_value,
                'new' => $new_value,
            ];
        }

        // If API key changed, connect and verify
        $connect_data = [];
        $should_refresh_connection_data = false;
        $new_api_key = $pending_updates['site_api_key'] ?? '';
        if ($new_api_key !== '' && $new_api_key !== $old_api_key && !str_contains($new_api_key, '***')) {
            // Connect with new API key
            $webhook_url = rest_url('heynorah/v2/webhook');
            $connect_response = $this->organizationService->connect_api_key($new_api_key, $webhook_url);
            $connect_data = is_array($connect_response['data'] ?? null)
                ? $connect_response['data']
                : [];

            $recoverable_connect = $this->is_recoverable_connect_response($connect_response, $connect_data);

            if (!$connect_response['success'] && !$recoverable_connect) {
                Logger::log('settings', 'connect failed', [
                    'status' => (int) ($connect_response['status'] ?? 0),
                    'error' => (string) ($connect_response['error'] ?? ''),
                ]);
                return new WP_Error(
                    'rest_invalid_api_key',
                    'Failed to connect API key: ' . (string) ($connect_response['error'] ?? 'Unknown error'),
                    array('status' => 400)
                );
            }

            $validated = !empty($connect_data['validated']);
            if (!$validated) {
                $verify_result = $this->run_domain_verify_flow($new_api_key, $connect_data);
                if (!$verify_result['success']) {
                    $verify_error = trim((string) ($verify_result['message'] ?? 'Domain verification failed.'));
                    Logger::log('settings', 'verify flow failed', [
                        'error' => $verify_error,
                    ]);

                    foreach ($pending_updates as $key => $value) {
                        $this->settingsService->save($key, $value);
                    }

                    $challenge_publish = is_array($verify_result['challenge'] ?? null)
                        ? $verify_result['challenge']
                        : [];
                    $connect_webhook = is_array($connect_data['webhook'] ?? null)
                        ? $connect_data['webhook']
                        : [];
                    $connect_data['validated'] = false;
                    $connect_data['webhook'] = array_merge($connect_webhook, [
                        'challengePublishedMode' => (string) ($challenge_publish['mode'] ?? ''),
                        'lastVerifyError' => $verify_error,
                    ]);
                    $this->settingsService->clear_connection_data();
                    $this->settingsService->save_connection_data($connect_data);
                    $this->settingsService->save_connect_error($verify_error);

                    return new WP_Error(
                        'rest_domain_verification_failed',
                        'Failed to verify domain challenge: ' . $verify_error,
                        ['status' => 400]
                    );
                }

                $verify_response = is_array($verify_result['verify_response'] ?? null)
                    ? $verify_result['verify_response']
                    : [];
                $verify_data = is_array($verify_response['data'] ?? null)
                    ? $verify_response['data']
                    : [];

                $verify_webhook = is_array($verify_data['webhook'] ?? null)
                    ? $verify_data['webhook']
                    : [];
                if (!empty($verify_webhook)) {
                    $connect_webhook = is_array($connect_data['webhook'] ?? null)
                        ? $connect_data['webhook']
                        : [];
                    $connect_data['webhook'] = array_merge($connect_webhook, $verify_webhook);
                }

                $connect_data['validated'] = array_key_exists('validated', $verify_data)
                    ? !empty($verify_data['validated'])
                    : true;
            }
            $should_refresh_connection_data = true;
        }

        foreach ($pending_updates as $key => $value) {
            $this->settingsService->save($key, $value);
        }

        if ($should_refresh_connection_data) {
            $this->settingsService->clear_connection_data();
            $this->settingsService->save_connection_data($connect_data);
            $this->settingsService->save_connect_error('');
            $this->auditLog->log_api_connection('connect', get_current_user_id());
        }

        if (!empty($audit_entries)) {
            $user_id = get_current_user_id();
            foreach ($audit_entries as $entry) {
                $this->auditLog->log_settings_change(
                    (string) $entry['key'],
                    (string) $entry['old'],
                    (string) $entry['new'],
                    $user_id
                );
            }
        }

        // Always refresh permalinks on settings save
        // Force re-registration with current slug (call directly, init hook already fired)
        $this->postTypeManager->register();

        // Flush rewrite rules to update permalinks
        flush_rewrite_rules(false);
        Logger::log('settings', 'update_settings success');

        return rest_ensure_response(['success' => true]);
    }

    public function get_gravity_forms(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('\GFAPI')) {
            return rest_ensure_response([
                'enabled' => false,
                'forms' => [],
                'message' => 'Gravity Forms plugin is not active.',
            ]);
        }

        try {
            $forms = \GFAPI::get_forms(true);

            if (!is_array($forms)) {
                $forms = [];
            }

            $normalized = [];
            foreach ($forms as $form) {
                $id = isset($form['id']) ? (string) $form['id'] : '';
                $title = isset($form['title']) ? sanitize_text_field((string) $form['title']) : '';

                if ($id === '' || $title === '' || !ctype_digit($id)) {
                    continue;
                }

                $normalized[] = [
                    'id' => $id,
                    'title' => $title,
                ];
            }

            usort($normalized, static function (array $left, array $right): int {
                return strcasecmp($left['title'], $right['title']);
            });

            return rest_ensure_response([
                'enabled' => true,
                'forms' => $normalized,
            ]);
        } catch (\Throwable $e) {
            return rest_ensure_response([
                'enabled' => false,
                'forms' => [],
                'message' => 'Failed to load Gravity Forms list.',
            ]);
        }
    }

    public function retry_verify(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($this->is_cookie_auth($request)) {
            $nonce_check = $this->verify_nonce($request);
            if (is_wp_error($nonce_check)) {
                return $nonce_check;
            }
        }

        $api_key = $this->settingsService->get('site_api_key');
        if ($api_key === '') {
            return new WP_Error(
                'rest_no_api_key',
                'No API key configured.',
                ['status' => 400]
            );
        }

        $connection = $this->settingsService->get_connection_status();
        $webhook = is_array($connection['webhook'] ?? null) ? $connection['webhook'] : [];
        $challenge_data = $this->settingsService->get_challenge_data() ?? [];

        $connect_data = [
            'validated' => false,
            'webhook' => array_merge(
                $webhook,
                [
                    'challenge' => $challenge_data,
                ]
            ),
        ];

        $result = $this->run_domain_verify_flow($api_key, $connect_data);

        return rest_ensure_response([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'verified' => $result['success'],
                'challenge' => $result['challenge'],
                'verify' => $result['verify_response'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $connect_response
     * @param array<string, mixed> $connect_data
     */
    private function is_recoverable_connect_response(array $connect_response, array $connect_data): bool
    {
        $status = (int) ($connect_response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            return false;
        }

        if (empty($connect_data)) {
            return false;
        }

        $webhook = is_array($connect_data['webhook'] ?? null) ? $connect_data['webhook'] : [];
        if (empty($webhook)) {
            return false;
        }

        $secret = trim((string) ($webhook['secret'] ?? ''));
        $endpoint_key = trim((string) ($webhook['endpointKey'] ?? ''));
        $has_connect_identifiers = $secret !== '' || $endpoint_key !== '';

        if (!$has_connect_identifiers) {
            return false;
        }

        if (array_key_exists('validated', $connect_data)) {
            return true;
        }

        $challenge = is_array($webhook['challenge'] ?? null) ? $webhook['challenge'] : [];
        $challenge_id = trim((string) ($challenge['challengeId'] ?? ''));
        $challenge_token = trim((string) ($challenge['token'] ?? ''));

        return $challenge_id !== '' || $challenge_token !== '';
    }

    /**
     * @param array<string, mixed> $connect_data
     * @return array{success:bool,message:string,challenge:array<string,mixed>,verify_response:array<string,mixed>}
     */
    private function run_domain_verify_flow(string $api_key, array $connect_data): array
    {
        $webhook = is_array($connect_data['webhook'] ?? null) ? $connect_data['webhook'] : [];
        $endpoint_key = sanitize_text_field((string) ($webhook['endpointKey'] ?? ''));

        $challenge = [];
        if (isset($webhook['challenge']) && is_array($webhook['challenge'])) {
            $challenge = $webhook['challenge'];
        } elseif (isset($connect_data['challenge']) && is_array($connect_data['challenge'])) {
            $challenge = $connect_data['challenge'];
        }

        $challenge_id = sanitize_text_field((string) ($challenge['id'] ?? ($webhook['challengeId'] ?? '')));
        $challenge_token = (string) ($challenge['token'] ?? ($webhook['challengeToken'] ?? ''));
        $challenge_url = (string) ($challenge['url'] ?? ($webhook['challengeUrl'] ?? ''));
        $challenge_expires_at = (string) ($challenge['expiresAt'] ?? ($webhook['challengeExpiresAt'] ?? ''));

        if ($endpoint_key === '' || $challenge_id === '') {
            $error = 'Missing endpointKey or challengeId for verify-domain flow.';
            $this->settingsService->save_verify_result(false, $error);

            return [
                'success' => false,
                'message' => $error,
                'challenge' => [],
                'verify_response' => [],
            ];
        }

        $challenge_publish = $this->domainChallengeService->publish_challenge([
            'id' => $challenge_id,
            'token' => $challenge_token,
            'url' => $challenge_url,
            'expiresAt' => $challenge_expires_at,
        ]);

        $verify_response = $this->organizationService->verify_domain($api_key, $endpoint_key, $challenge_id);
        if (!$verify_response['success']) {
            $error = (string) ($verify_response['error'] ?? 'Verify domain failed');
            $this->settingsService->save_verify_result(false, $error);

            return [
                'success' => false,
                'message' => $error,
                'challenge' => $challenge_publish,
                'verify_response' => $verify_response,
            ];
        }

        $verify_data = is_array($verify_response['data'] ?? null) ? $verify_response['data'] : [];
        $is_verified = array_key_exists('validated', $verify_data)
            ? !empty($verify_data['validated'])
            : true;

        if (isset($verify_data['webhook']) && is_array($verify_data['webhook'])) {
            $this->settingsService->update_webhook_meta($verify_data['webhook']);
        }

        $this->settingsService->save_verify_result($is_verified, $is_verified ? '' : 'Domain verify pending');

        return [
            'success' => $is_verified,
            'message' => $is_verified ? 'Domain verification completed.' : 'Domain verification pending.',
            'challenge' => $challenge_publish,
            'verify_response' => $verify_response,
        ];
    }
}
