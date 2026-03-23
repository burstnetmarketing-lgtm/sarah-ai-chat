<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

/**
 * Canonical field key schema for structured KB data.
 *
 * These keys are the authoritative identifiers for business and contact fields
 * across the full stack (PHP backend, JS widget, AI prompt instructions).
 * Any change to a key here must be mirrored in:
 *   - sarah-ai-client/assets/src/widget/knowledgeFieldSchema.js
 *   - OpenAiAgentExecutor::buildStructuredOutputInstruction() canonical key list
 *
 * FIELD GROUPS
 *   contact.*   — communication channels (phone, email, website)
 *   business.*  — physical presence and operating info
 */
class KnowledgeFieldSchema
{
    // Contact fields
    public const FIELD_PHONE_ADMIN      = 'contact.phone_admin';
    public const FIELD_PHONE_MARKETING  = 'contact.phone_marketing';
    public const FIELD_PHONE_SALES      = 'contact.phone_sales';
    public const FIELD_EMAIL_SUPPORT    = 'contact.email_support';
    public const FIELD_EMAIL_SALES      = 'contact.email_sales';
    public const FIELD_WEBSITE          = 'contact.website';

    // Business fields
    public const FIELD_ADDRESS          = 'business.address';
    public const FIELD_HOURS            = 'business.hours';
    public const FIELD_NAME             = 'business.name';
    public const FIELD_DESCRIPTION      = 'business.description';

    /**
     * Returns all canonical field keys.
     *
     * @return string[]
     */
    public static function allKeys(): array
    {
        return [
            self::FIELD_PHONE_ADMIN,
            self::FIELD_PHONE_MARKETING,
            self::FIELD_PHONE_SALES,
            self::FIELD_EMAIL_SUPPORT,
            self::FIELD_EMAIL_SALES,
            self::FIELD_WEBSITE,
            self::FIELD_ADDRESS,
            self::FIELD_HOURS,
            self::FIELD_NAME,
            self::FIELD_DESCRIPTION,
        ];
    }

    /**
     * Extracts structured fields from a resource's meta JSON.
     *
     * Resources can carry an optional `structured_fields` key in their meta
     * column — a flat JSON object of canonical_key → value pairs.
     * Only keys that match the canonical schema are returned.
     *
     * Example meta JSON:
     *   {"structured_fields": {"contact.phone_admin": "04XX XXX XXX", "contact.website": "https://..."}}
     *
     * @param  array  $resource  Full resource row from KnowledgeResourceRepository
     * @return array<string,string>  Map of canonical_key → string value
     */
    public static function extractFromResource(array $resource): array
    {
        $meta = [];
        $raw  = $resource['meta'] ?? '';
        if ($raw) {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['structured_fields']) && is_array($decoded['structured_fields'])) {
                $meta = $decoded['structured_fields'];
            }
        }

        $allowed = array_flip(self::allKeys());
        $out     = [];
        foreach ($meta as $key => $value) {
            if (isset($allowed[$key]) && is_string($value) && trim($value) !== '') {
                $out[$key] = trim($value);
            }
        }
        return $out;
    }
}
