<?php
declare(strict_types=1);

namespace HeyNorah\Services;
use HeyNorah\Core\Plugin;

class LoggerService
{
    // SECURITY: Payload size limit (2MB)
    private const MAX_PAYLOAD_SIZE = 2 * 1024 * 1024; // 2MB

    public function log(string $event, string $heynorah_id, string $status, int $code, string $raw_payload, string $msg = ''): void
    {
        global $wpdb;
        $table = $wpdb->prefix . Plugin::TABLE_LOGS;

        // SECURITY FIX: Truncate large payloads
        if (strlen($raw_payload) > self::MAX_PAYLOAD_SIZE) {
            $raw_payload = substr($raw_payload, 0, self::MAX_PAYLOAD_SIZE) . '... [truncated - exceeded 2MB limit]';
        }

        $stored_payload = [
            'raw_body' => json_decode($raw_payload, true) ?: $raw_payload,
            'message' => $msg,
            'headers' => $this->get_headers()
        ];

        $wpdb->insert($table, [
            'event' => substr($event, 0, 100),
            'heynorah_id' => substr($heynorah_id, 0, 100),
            'status' => $status,
            'response_code' => $code,
            'payload' => json_encode($stored_payload, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql')
        ]);
    }

    private function get_headers(): array
    {
        $headers = [];
        foreach ([
            'X-HeyNorah-Signature',
            'X-HeyNorah-Event',
            'X-HeyNorah-Timestamp',
            'X-HeyNorah-Delivery-Id',
            'X-HeyNorah-Endpoint-Key',
            'User-Agent',
        ] as $k) {
            $headers[$k] = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] ?? null;
        }
        return $headers;
    }
}
