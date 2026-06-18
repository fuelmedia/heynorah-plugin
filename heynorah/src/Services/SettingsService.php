<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use HeyNorah\Core\Plugin;
use HeyNorah\Utils\Encryption;
use InvalidArgumentException;

class SettingsService
{
    private const WEBHOOK_META_OPTION = 'heynorah_webhook_meta';
    private const INQUIRY_FORM_OPTION = 'heynorah_inquiry_form_id';
    private const TEST_DRIVE_FORM_OPTION = 'heynorah_test_drive_form_id';
    private const WEBHOOK_PATH_V2 = '/wp-json/heynorah/v2/webhook';
    private const DEFAULT_SIGNATURE_HEADERS = [
        'x-heynorah-event',
        'x-heynorah-timestamp',
        'x-heynorah-delivery-id',
        'x-heynorah-signature',
    ];

    private function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . Plugin::TABLE_SETTINGS;
    }

    private function get_settings_row(): ?array
    {
        global $wpdb;
        $table = $this->get_table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i ORDER BY id DESC LIMIT 1", $table), ARRAY_A);

        return $row ?: null;
    }

    private function ensure_settings_row_exists(): void
    {
        global $wpdb;
        $table = $this->get_table_name();

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table));

        if ($exists == 0) {
            $wpdb->insert($table, [
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    private function get_settings_row_id(): int
    {
        $this->ensure_settings_row_exists();
        $settings = $this->get_settings_row();

        if (!$settings) {
            return 1;
        }

        return (int) ($settings['id'] ?? 1);
    }

    public function get(string $key): string
    {
        if ($this->is_form_setting_key($key)) {
            $value = get_option($this->get_form_option_name($key), '');
            $value = is_scalar($value) ? (string) $value : '';
            return ctype_digit($value) ? $value : '';
        }

        $settings = $this->get_settings_row();

        if (!$settings) {
            return '';
        }

        $value = $settings[$key] ?? '';

        if (($key === 'webhook_secret' || $key === 'site_api_key') && !empty($value)) {
            return trim(Encryption::decrypt($value));
        }

        return (string) $value;
    }

    public function save(string $key, string $value): void
    {
        if ($this->is_form_setting_key($key)) {
            $value = trim(sanitize_text_field($value));

            if ($value !== '' && !ctype_digit($value)) {
                throw new InvalidArgumentException('Invalid form id for settings key: ' . esc_html($key));
            }

            update_option($this->get_form_option_name($key), $value, false);
            return;
        }

        global $wpdb;
        $table = $this->get_table_name();

        $allowed_keys = [
            'site_api_key',
            'api_base_url',
            'meilisearch_url',
            'cpt_slug',
            'taxonomy_slug',
            'inquiry_form_id',
            'test_drive_form_id',
            'webhook_secret',
            'organization_data',
            'user_data',
            'validated',
            'verified_at',
        ];

        if (!in_array($key, $allowed_keys, true)) {
            throw new InvalidArgumentException('Invalid settings key: ' . esc_html($key));
        }

        $this->ensure_settings_row_exists();

        if (($key === 'webhook_secret' || $key === 'site_api_key') && !empty($value)) {
            $value = Encryption::encrypt(trim($value));
        } elseif (in_array($key, ['api_base_url', 'meilisearch_url'], true)) {
            $value = esc_url_raw(untrailingslashit(trim($value)));
        } else {
            $value = sanitize_text_field($value);
        }

        $row_id = $this->get_settings_row_id();

        $wpdb->update(
            $table,
            [$key => $value],
            ['id' => $row_id],
            ['%s'],
            ['%d']
        );
    }

    public function get_all_for_api(): array
    {
        $settings = $this->get_settings_row();

        if (!$settings) {
            return [
                'site_api_key' => '',
                'api_base_url' => Plugin::get_api_base_url(),
                'meilisearch_url' => \HeyNorah\Core\Config::PROD_MS_URL,
                'cpt_slug' => '',
                'taxonomy_slug' => '',
                'inquiry_form_id' => $this->get('inquiry_form_id'),
                'test_drive_form_id' => $this->get('test_drive_form_id'),
            ];
        }

        $api_key_encrypted = $settings['site_api_key'] ?? '';
        $masked_api_key = '';

        if (!empty($api_key_encrypted)) {
            try {
                $decrypted_key = Encryption::decrypt($api_key_encrypted);

                if (empty($decrypted_key)) {
                    $decoded = base64_decode($api_key_encrypted, true);
                    if ($decoded !== false && str_contains($decoded, '::')) {
                        $masked_api_key = '';
                    } else {
                        $decrypted_key = $api_key_encrypted;
                    }
                }

                if (!empty($decrypted_key)) {
                    $decrypted_key = trim($decrypted_key);

                    if (strlen($decrypted_key) > 10) {
                        $masked_api_key = substr($decrypted_key, 0, 7) . str_repeat('*', strlen($decrypted_key) - 7);
                    } elseif (strlen($decrypted_key) > 0) {
                        $masked_api_key = str_repeat('*', strlen($decrypted_key));
                    }
                }
            } catch (\Exception $e) {
                $masked_api_key = '';
            }
        }

        return [
            'site_api_key' => $masked_api_key,
            'api_base_url' => $settings['api_base_url'] ?: Plugin::get_api_base_url(),
            'meilisearch_url' => $settings['meilisearch_url'] ?: \HeyNorah\Core\Config::PROD_MS_URL,
            'cpt_slug' => $settings['cpt_slug'] ?? '',
            'taxonomy_slug' => $settings['taxonomy_slug'] ?? '',
            'inquiry_form_id' => $this->get('inquiry_form_id'),
            'test_drive_form_id' => $this->get('test_drive_form_id'),
        ];
    }

    public function clear_form_settings(): void
    {
        delete_option(self::INQUIRY_FORM_OPTION);
        delete_option(self::TEST_DRIVE_FORM_OPTION);
    }

    public function save_connection_data(array $response_data): void
    {
        global $wpdb;
        $table = $this->get_table_name();

        $row_id = $this->get_settings_row_id();

        $update_data = [];
        $normalized_organization = null;

        if (isset($response_data['webhook']['secret'])) {
            $webhook_secret = (string) $response_data['webhook']['secret'];
            if ($webhook_secret !== '') {
                $update_data['webhook_secret'] = Encryption::encrypt(trim($webhook_secret));
            }
        }

        if (isset($response_data['organization']) && is_array($response_data['organization'])) {
            $normalized_organization = $this->normalize_organization_data($response_data['organization']);
            $org_json = wp_json_encode($normalized_organization);
            $update_data['organization_data'] = Encryption::encrypt((string) $org_json);
        }

        $organization_data = is_array($response_data['organization'] ?? null)
            ? $response_data['organization']
            : [];
        $meilisearch_url = $this->first_non_empty_string([
            $response_data['meilisearchUrl'] ?? null,
            $response_data['meilisearch_url'] ?? null,
            $organization_data['meilisearchUrl'] ?? null,
            $organization_data['meilisearch_url'] ?? null,
        ]);
        if ($meilisearch_url !== '') {
            $update_data['meilisearch_url'] = esc_url_raw(untrailingslashit($meilisearch_url));
        }

        if (isset($response_data['user']) && is_array($response_data['user'])) {
            $normalized_user = $this->normalize_user_data($response_data['user']);
            $user_json = wp_json_encode($normalized_user);
            $update_data['user_data'] = Encryption::encrypt((string) $user_json);
        }

        $validated = !empty($response_data['validated']);
        $update_data['validated'] = $validated ? 1 : 0;
        $update_data['verified_at'] = $validated ? current_time('mysql') : null;
        $update_data['updated_at'] = current_time('mysql');

        if (!empty($update_data)) {
            $wpdb->update(
                $table,
                $update_data,
                ['id' => $row_id],
                array_fill(0, count($update_data), '%s'),
                ['%d']
            );
        }

        $webhook_payload = (isset($response_data['webhook']) && is_array($response_data['webhook']))
            ? $response_data['webhook']
            : [];

        $webhook_payload['lastConnectAt'] = current_time('mysql');
        $webhook_payload['lastConnectError'] = '';

        $existing_meta = $this->get_webhook_meta_internal();
        $normalized_meta = $this->normalize_webhook_meta(array_merge($existing_meta, $webhook_payload), $response_data);
        update_option(self::WEBHOOK_META_OPTION, $normalized_meta, false);

        if (is_array($normalized_organization)) {
            $this->sync_industry_taxonomy($normalized_organization);
        }
    }

    public function save_connect_error(string $error): void
    {
        $this->update_webhook_meta([
            'lastConnectAt' => current_time('mysql'),
            'lastConnectError' => sanitize_text_field($error),
        ]);
    }

    public function save_verify_result(bool $success, string $error = ''): void
    {
        global $wpdb;
        $table = $this->get_table_name();

        $row_id = $this->get_settings_row_id();

        $wpdb->update(
            $table,
            [
                'validated' => $success ? 1 : 0,
                'verified_at' => $success ? current_time('mysql') : null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $row_id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        $this->update_webhook_meta([
            'domainStatus' => $success ? 'active' : 'pending',
            'endpointStatus' => $success ? 'active' : 'pending',
            'lastVerifyAt' => current_time('mysql'),
            'lastVerifyError' => $success ? '' : sanitize_text_field($error),
        ]);
    }

    public function save_ack_result(bool $success, string $error = '', ?int $http_status = null): void
    {
        $updates = [
            'lastAckAt' => current_time('mysql'),
            'lastAckError' => $success ? '' : sanitize_text_field($error),
        ];

        if ($http_status !== null) {
            $updates['lastAckHttpStatus'] = (int) $http_status;
        }

        $this->update_webhook_meta($updates);
    }

    public function update_webhook_meta(array $updates): void
    {
        $current = $this->get_webhook_meta_internal();
        $merged = array_merge($current, $updates);
        $normalized = $this->normalize_webhook_meta($merged, []);

        update_option(self::WEBHOOK_META_OPTION, $normalized, false);
    }

    public function get_webhook_meta_raw(): array
    {
        return $this->get_webhook_meta_internal();
    }

    public function get_challenge_data(): ?array
    {
        $meta = $this->get_webhook_meta_internal();
        $challenge_id = (string) ($meta['challengeId'] ?? '');
        $challenge_token = (string) ($meta['challengeToken'] ?? '');

        if ($challenge_id === '' || $challenge_token === '') {
            return null;
        }

        return [
            'id' => $challenge_id,
            'token' => $challenge_token,
            'url' => (string) ($meta['challengeUrl'] ?? ''),
            'expiresAt' => (string) ($meta['challengeExpiresAt'] ?? ''),
        ];
    }

    private function sync_industry_taxonomy(array $organization_data): void
    {
        $industries = [];

        if (isset($organization_data['organizationIndustries']) && is_array($organization_data['organizationIndustries'])) {
            $industries = $organization_data['organizationIndustries'];
        } elseif (isset($organization_data['industries']) && is_array($organization_data['industries'])) {
            foreach ($organization_data['industries'] as $industry) {
                if (!is_array($industry)) {
                    continue;
                }
                $industries[] = [
                    'id' => $industry['id'] ?? '',
                    'industry' => $industry,
                ];
            }
        }

        if (empty($industries)) {
            return;
        }

        foreach ($industries as $org_industry) {
            if (!isset($org_industry['industry']) || !is_array($org_industry['industry'])) {
                continue;
            }

            $industry = $org_industry['industry'];
            $industry_id = (string) ($industry['id'] ?? '');
            $industry_name = (string) ($industry['name'] ?? '');
            $industry_slug = (string) ($industry['slug'] ?? '');

            if (empty($industry_name) || empty($industry_slug) || empty($industry_id)) {
                continue;
            }

            $term = term_exists($industry_slug, Plugin::TAX_INDUSTRY);

            if ($term === 0 || $term === null) {
                $result = wp_insert_term(
                    $industry_name,
                    Plugin::TAX_INDUSTRY,
                    [
                        'slug' => $industry_slug,
                    ]
                );

                if (!is_wp_error($result)) {
                    update_term_meta($result['term_id'], 'heynorah_industry_id', $industry_id);

                    if (isset($org_industry['id'])) {
                        update_term_meta($result['term_id'], 'heynorah_org_industry_id', sanitize_text_field((string) $org_industry['id']));
                    }
                }
            } else {
                wp_update_term(
                    $term['term_id'],
                    Plugin::TAX_INDUSTRY,
                    [
                        'name' => $industry_name,
                        'slug' => $industry_slug,
                    ]
                );

                update_term_meta($term['term_id'], 'heynorah_industry_id', $industry_id);

                if (isset($org_industry['id'])) {
                    update_term_meta($term['term_id'], 'heynorah_org_industry_id', sanitize_text_field((string) $org_industry['id']));
                }
            }
        }
    }

    public function get_connection_status(): array
    {
        $settings = $this->get_settings_row();

        if (!$settings) {
            return [
                'verified' => false,
                'verified_at' => null,
                'organization' => null,
                'user' => null,
                'webhook' => null,
            ];
        }

        $validated = isset($settings['validated']) && (int) $settings['validated'] === 1;
        $organization_data = $settings['organization_data'] ?? null;
        $user_data = $settings['user_data'] ?? null;
        $verified_at = $settings['verified_at'] ?? null;

        $organization = null;
        if ($organization_data !== null) {
            $decrypted = Encryption::decrypt((string) $organization_data);
            if ($decrypted) {
                $decoded = json_decode($decrypted, true);
                $organization = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            }
        }

        $user = null;
        if ($user_data !== null) {
            $decrypted = Encryption::decrypt((string) $user_data);
            if ($decrypted) {
                $decoded = json_decode($decrypted, true);
                $user = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            }
        }

        $webhook = $this->get_webhook_meta();
        $domain_status = is_array($webhook) ? (string) ($webhook['domainStatus'] ?? '') : '';
        $is_verified = $validated && $organization !== null && ($domain_status === '' || $domain_status === 'active');

        return [
            'verified' => $is_verified,
            'verified_at' => $verified_at,
            'organization' => $organization,
            'user' => $user,
            'webhook' => $webhook,
        ];
    }

    public function clear_connection_data(): void
    {
        global $wpdb;
        $table = $this->get_table_name();

        $row_id = $this->get_settings_row_id();

        $wpdb->update(
            $table,
            [
                'webhook_secret' => null,
                'organization_data' => null,
                'user_data' => null,
                'verified_at' => null,
                'validated' => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $row_id],
            ['%s', '%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        delete_option(self::WEBHOOK_META_OPTION);
    }

    private function normalize_organization_data(array $organization): array
    {
        $result = $organization;

        $result['id'] = (string) ($organization['id'] ?? '');
        $result['name'] = (string) ($organization['name'] ?? '');
        $result['slug'] = (string) ($organization['slug'] ?? '');
        $result['meilisearchUrl'] = $this->first_non_empty_string([
            $organization['meilisearchUrl'] ?? null,
            $organization['meilisearch_url'] ?? null,
        ]);
        $result['meilisearchPublicKey'] = $this->first_non_empty_string([
            $organization['meilisearchPublicKey'] ?? null,
            $organization['publicKey'] ?? null,
            $organization['meilisearch_public_key'] ?? null,
        ]);

        $indexes = is_array($organization['meilisearchIndexes'] ?? null)
            ? $organization['meilisearchIndexes']
            : (
                is_array($organization['indexes'] ?? null)
                ? $organization['indexes']
                : []
            );

        $inventory_index = $this->first_non_empty_string([
            $indexes['inventory'] ?? null,
            $indexes['records'] ?? null,
        ]);
        $records_index = $this->first_non_empty_string([
            $indexes['records'] ?? null,
            $inventory_index,
        ]);

        $result['meilisearchIndexes'] = [
            'inventory' => $inventory_index,
            'records' => $records_index,
            'deals' => (string) ($indexes['deals'] ?? ''),
            'knowledge' => (string) ($indexes['knowledge'] ?? ''),
        ];

        if (!isset($result['organizationIndustries']) || !is_array($result['organizationIndustries'])) {
            $result['organizationIndustries'] = [];
        }

        if (empty($result['organizationIndustries']) && isset($organization['industries']) && is_array($organization['industries'])) {
            $normalized_industries = [];
            foreach ($organization['industries'] as $industry_entry) {
                if (is_string($industry_entry)) {
                    $slug = sanitize_title($industry_entry);
                    if ($slug === '') {
                        continue;
                    }
                    $normalized_industries[] = [
                        'id' => '',
                        'industry' => [
                            'id' => '',
                            'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                            'slug' => $slug,
                        ],
                    ];
                    continue;
                }

                if (!is_array($industry_entry)) {
                    continue;
                }

                $industry_payload = is_array($industry_entry['industry'] ?? null)
                    ? $industry_entry['industry']
                    : $industry_entry;

                $industry_slug = sanitize_title((string) ($industry_payload['slug'] ?? ($industry_payload['code'] ?? ($industry_payload['name'] ?? ''))));
                $industry_name = (string) ($industry_payload['name'] ?? ucwords(str_replace(['-', '_'], ' ', $industry_slug)));
                $industry_id = (string) ($industry_payload['id'] ?? ($industry_entry['id'] ?? $industry_slug));

                if ($industry_slug === '' || $industry_name === '') {
                    continue;
                }

                $normalized_industries[] = [
                    'id' => (string) ($industry_entry['id'] ?? $industry_id),
                    'industry' => [
                        'id' => $industry_id !== '' ? $industry_id : $industry_slug,
                        'name' => $industry_name,
                        'slug' => $industry_slug,
                    ],
                ];
            }

            $result['organizationIndustries'] = $normalized_industries;
        }

        return $result;
    }

    private function normalize_user_data(array $user): array
    {
        $result = $user;

        $result['id'] = (string) ($user['id'] ?? '');
        $result['name'] = (string) ($user['name'] ?? '');
        $result['email'] = (string) ($user['email'] ?? '');
        $result['role'] = (string) ($user['role'] ?? '');

        return $result;
    }

    private function normalize_webhook_meta(array $webhook, array $response_data): array
    {
        $validated = !empty($response_data['validated']) || !empty($webhook['validated']);
        $response_webhook = is_array($response_data['webhook'] ?? null) ? $response_data['webhook'] : [];

        $challenge = [];
        if (isset($webhook['challenge']) && is_array($webhook['challenge'])) {
            $challenge = $webhook['challenge'];
        } elseif (isset($response_data['challenge']) && is_array($response_data['challenge'])) {
            $challenge = $response_data['challenge'];
        }

        $event_types = $webhook['eventTypes']
            ?? ($response_webhook['eventTypes']
                ?? ($webhook['events']
                    ?? ($response_webhook['events'] ?? [])));
        if (!is_array($event_types)) {
            $event_types = [];
        }

        $signature_headers = $this->normalize_signature_headers(
            $webhook['signatureHeaders']
            ?? ($response_webhook['signatureHeaders'] ?? null)
        );

        $domain_status = (string) ($webhook['domainStatus'] ?? '');
        if ($domain_status === '') {
            $domain_status = $validated ? 'active' : 'pending';
        }

        $challenge_id = (string) ($challenge['id'] ?? ($webhook['challengeId'] ?? ''));
        $challenge_token = (string) ($challenge['token'] ?? ($webhook['challengeToken'] ?? ''));
        $challenge_url = (string) ($challenge['url'] ?? ($webhook['challengeUrl'] ?? ''));
        $challenge_expires_at = (string) ($challenge['expiresAt'] ?? ($webhook['challengeExpiresAt'] ?? ''));
        $api_base_url = (string) ($webhook['apiBaseUrl'] ?? ($response_webhook['apiBaseUrl'] ?? Plugin::get_api_base_url()));
        $api_base_url = untrailingslashit($api_base_url);

        $ack_url = $this->first_non_empty_string([
            $webhook['ackUrl'] ?? null,
            $webhook['ackURL'] ?? null,
            $webhook['ack_url'] ?? null,
            $response_webhook['ackUrl'] ?? null,
            $response_webhook['ackURL'] ?? null,
            $response_webhook['ack_url'] ?? null,
            $response_data['ackUrl'] ?? null,
            $response_data['ackURL'] ?? null,
            $response_data['ack_url'] ?? null,
            (is_array($response_data['ack'] ?? null) ? ($response_data['ack']['url'] ?? null) : null),
            (is_array($response_data['ack'] ?? null) ? ($response_data['ack']['ackUrl'] ?? null) : null),
        ]);
        if ($ack_url === '' && $api_base_url !== '') {
            $ack_url = $api_base_url . '/integrations/wordpress/sync-ack';
        }

        $verify_url = $this->first_non_empty_string([
            $webhook['verifyUrl'] ?? null,
            $webhook['verifyURL'] ?? null,
            $webhook['verify_url'] ?? null,
            $response_webhook['verifyUrl'] ?? null,
            $response_webhook['verifyURL'] ?? null,
            $response_webhook['verify_url'] ?? null,
            $response_data['verifyUrl'] ?? null,
            $response_data['verifyURL'] ?? null,
            $response_data['verify_url'] ?? null,
            (is_array($response_data['verify'] ?? null) ? ($response_data['verify']['url'] ?? null) : null),
            (is_array($response_data['verify'] ?? null) ? ($response_data['verify']['verifyUrl'] ?? null) : null),
        ]);
        if ($verify_url === '' && $api_base_url !== '') {
            $verify_url = $api_base_url . '/api/integrations/wordpress/verify-domain';
        }

        return [
            'apiBaseUrl' => esc_url_raw($api_base_url !== '' ? $api_base_url : Plugin::get_api_base_url()),
            'siteUrl' => esc_url_raw((string) ($webhook['siteUrl'] ?? get_site_url())),
            'webhookPath' => sanitize_text_field((string) ($webhook['webhookPath'] ?? self::WEBHOOK_PATH_V2)),
            'endpointKey' => sanitize_text_field((string) ($webhook['endpointKey'] ?? '')),
            'ackUrl' => esc_url_raw($ack_url),
            'verifyUrl' => esc_url_raw($verify_url),
            'eventTypes' => array_values(array_filter(array_map(
                static fn($value): string => sanitize_text_field((string) $value),
                $event_types
            ))),
            'signatureHeaders' => array_values(array_filter(array_map(
                static fn($value): string => strtolower(sanitize_text_field((string) $value)),
                $signature_headers
            ))),
            'challengeId' => sanitize_text_field($challenge_id),
            'challengeToken' => sanitize_text_field($challenge_token),
            'challengeUrl' => esc_url_raw($challenge_url),
            'challengeExpiresAt' => sanitize_text_field($challenge_expires_at),
            'challengePublishedMode' => sanitize_text_field((string) ($webhook['challengePublishedMode'] ?? '')),
            'domainStatus' => sanitize_text_field($domain_status),
            'endpointStatus' => sanitize_text_field((string) ($webhook['endpointStatus'] ?? ($validated ? 'active' : 'pending'))),
            'lastConnectAt' => sanitize_text_field((string) ($webhook['lastConnectAt'] ?? current_time('mysql'))),
            'lastConnectError' => sanitize_text_field((string) ($webhook['lastConnectError'] ?? '')),
            'lastVerifyAt' => sanitize_text_field((string) ($webhook['lastVerifyAt'] ?? '')),
            'lastVerifyError' => sanitize_text_field((string) ($webhook['lastVerifyError'] ?? '')),
            'lastAckAt' => sanitize_text_field((string) ($webhook['lastAckAt'] ?? '')),
            'lastAckError' => sanitize_text_field((string) ($webhook['lastAckError'] ?? '')),
            'lastAckHttpStatus' => (int) ($webhook['lastAckHttpStatus'] ?? 0),
        ];
    }

    /**
     * @param mixed $raw_signature_headers
     * @return array<int,string>
     */
    private function normalize_signature_headers($raw_signature_headers): array
    {
        if (!is_array($raw_signature_headers)) {
            return self::DEFAULT_SIGNATURE_HEADERS;
        }

        $candidate_headers = [];
        $keys = ['event', 'timestamp', 'deliveryId', 'signature'];
        foreach ($keys as $key) {
            $value = $raw_signature_headers[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $candidate_headers[] = trim($value);
            }
        }

        if (!empty($candidate_headers)) {
            return $candidate_headers;
        }

        $list = [];
        foreach ($raw_signature_headers as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '' || str_contains($value, 'HMAC_')) {
                continue;
            }
            $list[] = $value;
        }

        return !empty($list) ? $list : self::DEFAULT_SIGNATURE_HEADERS;
    }

    /**
     * @param array<int,mixed> $values
     */
    private function first_non_empty_string(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function get_webhook_meta_internal(): array
    {
        $webhook = get_option(self::WEBHOOK_META_OPTION, []);
        return is_array($webhook) ? $webhook : [];
    }

    private function get_webhook_meta(): ?array
    {
        $webhook = $this->get_webhook_meta_internal();
        if (empty($webhook)) {
            return null;
        }

        $normalized = $this->normalize_webhook_meta($webhook, []);

        return [
            'apiBaseUrl' => $normalized['apiBaseUrl'],
            'siteUrl' => $normalized['siteUrl'],
            'webhookPath' => $normalized['webhookPath'],
            'endpointKey' => $normalized['endpointKey'],
            'ackUrl' => $normalized['ackUrl'],
            'verifyUrl' => $normalized['verifyUrl'],
            'eventTypes' => $normalized['eventTypes'],
            'signatureHeaders' => $normalized['signatureHeaders'],
            'challengeId' => $normalized['challengeId'],
            'challengeUrl' => $normalized['challengeUrl'],
            'challengeExpiresAt' => $normalized['challengeExpiresAt'],
            'challengePublishedMode' => $normalized['challengePublishedMode'],
            'domainStatus' => $normalized['domainStatus'],
            'endpointStatus' => $normalized['endpointStatus'],
            'lastConnectAt' => $normalized['lastConnectAt'],
            'lastConnectError' => $normalized['lastConnectError'],
            'lastVerifyAt' => $normalized['lastVerifyAt'],
            'lastVerifyError' => $normalized['lastVerifyError'],
            'lastAckAt' => $normalized['lastAckAt'],
            'lastAckError' => $normalized['lastAckError'],
            'lastAckHttpStatus' => $normalized['lastAckHttpStatus'],
            'endpointSecretPresent' => trim($this->get('webhook_secret')) !== '',
            'challengePending' => $normalized['challengeId'] !== '' && $normalized['domainStatus'] !== 'active',
        ];
    }

    private function is_form_setting_key(string $key): bool
    {
        return in_array($key, ['inquiry_form_id', 'test_drive_form_id'], true);
    }

    private function get_form_option_name(string $key): string
    {
        return $key === 'test_drive_form_id'
            ? self::TEST_DRIVE_FORM_OPTION
            : self::INQUIRY_FORM_OPTION;
    }
}
