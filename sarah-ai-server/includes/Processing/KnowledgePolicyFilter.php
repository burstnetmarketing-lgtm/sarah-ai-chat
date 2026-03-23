<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

use SarahAiServer\DB\KnowledgeResourceTable;

/**
 * Enforces visibility policy on KB resources and chunks.
 *
 * SECURITY BOUNDARY
 * This class is the single enforcement point for KB access control.
 * Every code path that exposes KB data to an external caller (AI prompt,
 * widget API, third-party integration) MUST pass through this filter.
 *
 * RULES
 *   public  → allowed in AI prompts AND the public knowledge-fields widget API
 *   private → allowed in AI prompts ONLY (never returned by the public API)
 *
 * INTENT DETECTION NOTE (Task 7)
 * Intent detection (detecting whether a user is asking for restricted info)
 * is a UX convenience only — it must NOT be the primary access-control gate.
 * The policy filter is the authoritative gate. Intent detection may be added
 * later to improve UX (e.g. "I can't share that privately") but never to
 * replace this filter.
 */
class KnowledgePolicyFilter
{
    /**
     * Filters a list of resource rows to those with visibility = 'public'.
     * Use before exposing data via the widget API or any unauthenticated endpoint.
     *
     * @param  array[] $resources  Array of resource rows (associative arrays)
     * @return array[]             Only public resources
     */
    public static function publicOnly(array $resources): array
    {
        return array_values(array_filter($resources, fn($r) => ($r['visibility'] ?? KnowledgeResourceTable::VISIBILITY_PUBLIC) === KnowledgeResourceTable::VISIBILITY_PUBLIC));
    }

    /**
     * Returns a safe, pre-defined response text to use when a user requests
     * information that is marked private in the KB.
     *
     * IMPORTANT: Do NOT rely on AI improvisation for this case.
     * The response returned here must be injected into the system prompt so
     * the agent delivers it verbatim instead of guessing or fabricating.
     */
    public static function restrictedDataSafeResponse(): string
    {
        return 'I\'m sorry, that information is not available through this channel. Please contact us directly for assistance.';
    }

    /**
     * Validates that a visibility value is one of the allowed constants.
     */
    public static function isValidVisibility(string $visibility): bool
    {
        return in_array($visibility, [
            KnowledgeResourceTable::VISIBILITY_PUBLIC,
            KnowledgeResourceTable::VISIBILITY_PRIVATE,
        ], true);
    }
}
