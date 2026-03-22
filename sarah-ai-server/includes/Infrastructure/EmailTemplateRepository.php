<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\EmailTemplateTable;

class EmailTemplateRepository
{
    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . EmailTemplateTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE slug = %s AND status = 'active'",
                $slug
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Renders a template by replacing {{variable}} placeholders with provided values.
     * Returns an array with 'subject' and 'body', or null if template not found.
     */
    public function render(string $slug, array $vars = []): ?array
    {
        $template = $this->findBySlug($slug);
        if ($template === null) {
            return null;
        }
        $subject = $template['subject'];
        $body    = $template['body'];
        foreach ($vars as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject     = str_replace($placeholder, (string) $value, $subject);
            $body        = str_replace($placeholder, (string) $value, $body);
        }
        return ['subject' => $subject, 'body' => $body];
    }

    /** Inserts a template only if the slug does not already exist. Idempotent. */
    public function insertIfMissing(string $slug, string $type, string $subject, string $body, array $variables = []): void
    {
        global $wpdb;
        $table  = $wpdb->prefix . EmailTemplateTable::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($exists) {
            return;
        }
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'slug'       => $slug,
            'type'       => $type,
            'subject'    => $subject,
            'body'       => $body,
            'variables'  => $variables ? wp_json_encode($variables) : null,
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
