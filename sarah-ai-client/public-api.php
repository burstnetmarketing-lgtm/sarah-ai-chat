<?php

/**
 * Sarah AI Client — Public PHP API.
 *
 * Globally callable functions for use by any WordPress plugin without internal dependency.
 * All functions check plugin state before forwarding to PublicApiService.
 *
 * Available functions:
 *   sarah_chat_exists()                     → bool
 *   sarah_chat_is_ready()                   → bool
 *   sarah_chat_setup(array $payload)        → array
 *   sarah_chat_get_sessions(array $args)    → array
 *   sarah_chat_get_session_history(string)  → array
 *   sarah_chat_set_config(array $values)    → array
 */

if (! defined('ABSPATH')) {
    exit;
}

// ─── Guard ────────────────────────────────────────────────────────────────────

/**
 * Returns true if Sarah Chat is installed and its Public API is available.
 * Always check this first before calling any other sarah_chat_* function.
 */
function sarah_chat_exists(): bool
{
    return defined('SARAH_AI_CLIENT_VERSION')
        && class_exists('SarahAiClient\\Core\\PublicApiService');
}

// ─── Status ───────────────────────────────────────────────────────────────────

/**
 * Returns true if Sarah Chat is fully configured and ready to serve chat requests.
 *
 * Checks that server_url, account_key, site_key, and platform_key are all set.
 */
function sarah_chat_is_ready(): bool
{
    if (! sarah_chat_exists()) {
        return false;
    }
    return (new SarahAiClient\Core\PublicApiService())->isReady();
}

// ─── Setup ────────────────────────────────────────────────────────────────────

/**
 * Runs the Quick Setup flow externally.
 *
 * Connects to the Sarah AI Server, provisions tenant + site, and persists credentials.
 *
 * @param array $payload {
 *   @type string $server_url      Required. Base WordPress REST API URL of the server
 *                                 (e.g. https://server.example.com/wp-json).
 *   @type string $platform_key    Required. Platform authentication key.
 *   @type string $site_name       Optional. Defaults to the current site's blogname.
 *   @type string $site_url        Optional. Defaults to home_url().
 *   @type string $whmcs_key       Required. WHMCS license key.
 *   @type string $openai_api_key  Required. Site-specific OpenAI API key.
 * }
 * @return array {
 *   @type bool        $success  True on successful provisioning.
 *   @type bool        $ready    True if the plugin is now ready after setup.
 *   @type string|null $error    Error message, or null on success.
 * }
 */
function sarah_chat_setup(array $payload): array
{
    if (! sarah_chat_exists()) {
        return ['success' => false, 'ready' => false, 'error' => 'Sarah Chat plugin is not installed'];
    }
    return (new SarahAiClient\Core\PublicApiService())->setup($payload);
}

// ─── Sessions ─────────────────────────────────────────────────────────────────

/**
 * Returns a list of recent chat sessions for this site.
 *
 * @param array $args {
 *   @type int $limit  Max sessions to return (default 20, server-capped at 100).
 * }
 * @return array {
 *   @type bool        $success
 *   @type array       $data     List of session objects (uuid, status, created_at, …).
 *   @type string|null $error
 * }
 */
function sarah_chat_get_sessions(array $args = []): array
{
    if (! sarah_chat_exists()) {
        return ['success' => false, 'data' => [], 'error' => 'Sarah Chat plugin is not installed'];
    }
    if (! sarah_chat_is_ready()) {
        return ['success' => false, 'data' => [], 'error' => 'Sarah Chat is not configured'];
    }
    return (new SarahAiClient\Core\PublicApiService())->getSessions($args);
}

// ─── Stats ────────────────────────────────────────────────────────────────────

/**
 * Returns total session and message counts for this site.
 *
 * @return array {
 *   @type bool        $success
 *   @type array       $data    { total_sessions: int, total_messages: int }
 *   @type string|null $error
 * }
 */
function sarah_chat_get_site_stats(): array
{
    if (! sarah_chat_exists()) {
        return ['success' => false, 'data' => [], 'error' => 'Sarah Chat plugin is not installed'];
    }
    if (! sarah_chat_is_ready()) {
        return ['success' => false, 'data' => [], 'error' => 'Sarah Chat is not configured'];
    }
    return (new SarahAiClient\Core\PublicApiService())->getSiteStats();
}

// ─── Session History ──────────────────────────────────────────────────────────

/**
 * Returns the full message history for a specific session.
 *
 * @param string $session_uuid UUID of the session to retrieve.
 * @return array {
 *   @type bool        $success
 *   @type array|null  $session   Session metadata (uuid, status, created_at, …).
 *   @type array       $messages  Ordered messages [{role, content, created_at}].
 *   @type string|null $error
 * }
 */
function sarah_chat_get_session_history(string $session_uuid): array
{
    if (! sarah_chat_exists()) {
        return ['success' => false, 'session' => null, 'messages' => [], 'error' => 'Sarah Chat plugin is not installed'];
    }
    if (! sarah_chat_is_ready()) {
        return ['success' => false, 'session' => null, 'messages' => [], 'error' => 'Sarah Chat is not configured'];
    }
    return (new SarahAiClient\Core\PublicApiService())->getSessionHistory($session_uuid);
}

// ─── Config ───────────────────────────────────────────────────────────────────

/**
 * Sets one or more Sarah Chat configuration values.
 *
 * Allowed keys: widget_enabled, greeting_message, server_url,
 *               account_key, site_key, platform_key.
 *
 * @param array $values Key-value pairs to persist.
 * @return array {
 *   @type bool        $success  True only if all provided keys were saved.
 *   @type string[]    $saved    Keys that were saved.
 *   @type string[]    $errors   Validation error messages.
 *   @type string|null $error    Combined error string, or null on full success.
 * }
 */
function sarah_chat_set_config(array $values): array
{
    if (! sarah_chat_exists()) {
        return ['success' => false, 'saved' => [], 'errors' => [], 'error' => 'Sarah Chat plugin is not installed'];
    }
    return (new SarahAiClient\Core\PublicApiService())->setConfig($values);
}
