<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use HeyNorah\Core\Plugin;

class WebhookEventService
{
    private function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . Plugin::TABLE_WEBHOOK_EVENTS;
    }

    public function get_by_event_id(string $event_id): ?array
    {
        if ($event_id === '') {
            return null;
        }

        global $wpdb;
        $table = $this->table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE event_id = %s LIMIT 1",
                $table,
                $event_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function is_duplicate(string $event_id): bool
    {
        return $this->get_by_event_id($event_id) !== null;
    }

    public function save_result(
        string $event_id,
        string $delivery_id,
        string $event_type,
        string $item_id,
        string $status,
        string $last_error = ''
    ): void {
        if ($event_id === '') {
            return;
        }

        global $wpdb;
        $table = $this->table_name();

        $now = current_time('mysql');
        $row = $this->get_by_event_id($event_id);

        if ($row) {
            $wpdb->update(
                $table,
                [
                    'delivery_id' => $delivery_id,
                    'event_type' => $event_type,
                    'item_id' => $item_id,
                    'status' => $status,
                    'processed_at' => $now,
                    'last_error' => $last_error,
                    'updated_at' => $now,
                ],
                ['event_id' => $event_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%s']
            );
            return;
        }

        $wpdb->insert(
            $table,
            [
                'event_id' => $event_id,
                'delivery_id' => $delivery_id,
                'event_type' => $event_type,
                'item_id' => $item_id,
                'status' => $status,
                'processed_at' => $now,
                'last_error' => $last_error,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}
