<?php
declare(strict_types=1);

namespace HeyNorah\Services;

class DomainChallengeService
{
    private const CHALLENGE_PATTERN = '#^/\.well-known/heynorah/challenge/([A-Za-z0-9_-]+)\.txt$#';

    private SettingsService $settingsService;

    public function __construct(?SettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?? new SettingsService();
    }

    /**
     * @param array{id?:mixed,token?:mixed,url?:mixed,expiresAt?:mixed} $challenge
     * @return array{published:bool,mode:string,url:string,path:string,error:string}
     */
    public function publish_challenge(array $challenge): array
    {
        $challenge_id = sanitize_text_field((string) ($challenge['id'] ?? ''));
        $challenge_token = trim((string) ($challenge['token'] ?? ''));

        if ($challenge_id === '' || $challenge_token === '') {
            return [
                'published' => false,
                'mode' => '',
                'url' => '',
                'path' => '',
                'error' => 'Missing challenge id or token',
            ];
        }

        $challenge_url = $this->build_challenge_url($challenge_id);
        $challenge_path = $this->build_challenge_path($challenge_id);

        $dir = dirname($challenge_path);
        $error = '';
        $mode = 'dynamic';
        $published = true;

        if (!wp_mkdir_p($dir)) {
            $error = 'Failed to create challenge directory';
        } else {
            $bytes = @file_put_contents($challenge_path, $challenge_token, LOCK_EX);
            if ($bytes === false) {
                $error = 'Failed to write challenge file';
            } else {
                $written = @file_get_contents($challenge_path);
                if ($written !== $challenge_token) {
                    $error = 'Challenge file verification failed';
                } else {
                    $mode = 'file';
                }
            }
        }

        $this->settingsService->update_webhook_meta([
            'challengeId' => $challenge_id,
            'challengeToken' => $challenge_token,
            'challengeUrl' => esc_url_raw((string) ($challenge['url'] ?? $challenge_url)),
            'challengeExpiresAt' => sanitize_text_field((string) ($challenge['expiresAt'] ?? '')),
            'challengePublishedMode' => $mode,
        ]);

        if ($error !== '') {
            // Dynamic fallback is still considered published when token is stored.
            $published = true;
        }

        return [
            'published' => $published,
            'mode' => $mode,
            'url' => $challenge_url,
            'path' => $challenge_path,
            'error' => $error,
        ];
    }

    public function register_rewrite_rule(): void
    {
        add_rewrite_rule(
            '^\.well-known/heynorah/challenge/([A-Za-z0-9_-]+)\.txt$',
            'index.php?heynorah_challenge_id=$matches[1]',
            'top'
        );
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public function register_query_vars(array $vars): array
    {
        $vars[] = 'heynorah_challenge_id';
        return $vars;
    }

    public function handle_request(): void
    {
        $requested_id = get_query_var('heynorah_challenge_id');

        if (!is_string($requested_id) || $requested_id === '') {
            $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $request_path = strtok($request_uri, '?');
            $matches = [];

            if (!is_string($request_path) || !preg_match(self::CHALLENGE_PATTERN, $request_path, $matches)) {
                return;
            }

            $requested_id = (string) ($matches[1] ?? '');
        }

        $challenge = $this->settingsService->get_challenge_data();
        if (!is_array($challenge)) {
            return;
        }

        $challenge_id = sanitize_text_field((string) ($challenge['id'] ?? ''));
        $challenge_token = (string) ($challenge['token'] ?? '');

        if ($challenge_id === '' || $challenge_token === '') {
            return;
        }

        if (!hash_equals($challenge_id, sanitize_text_field((string) $requested_id))) {
            return;
        }

        if (!$this->is_challenge_valid($challenge)) {
            return;
        }

        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        echo $challenge_token;
        exit;
    }

    private function build_challenge_url(string $challenge_id): string
    {
        return trailingslashit(get_site_url()) . '.well-known/heynorah/challenge/' . rawurlencode($challenge_id) . '.txt';
    }

    private function build_challenge_path(string $challenge_id): string
    {
        $base = trailingslashit(ABSPATH) . '.well-known/heynorah/challenge';
        return trailingslashit($base) . $challenge_id . '.txt';
    }

    /**
     * @param array{id?:mixed,token?:mixed,url?:mixed,expiresAt?:mixed} $challenge
     */
    private function is_challenge_valid(array $challenge): bool
    {
        $expires_at = (string) ($challenge['expiresAt'] ?? '');
        if ($expires_at === '') {
            return true;
        }

        if (is_numeric($expires_at)) {
            $expires_ts = (int) $expires_at;
            if ($expires_ts > 1000000000000) {
                $expires_ts = (int) floor($expires_ts / 1000);
            }
            return $expires_ts > time();
        }

        $parsed = strtotime($expires_at);
        if ($parsed === false) {
            return true;
        }

        return $parsed > time();
    }
}
