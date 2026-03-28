<?php

/**
 * Sarah Chat — Integration Adapter for Parent Products.
 *
 * Copy this file into your plugin. It provides a safe, decoupled layer around
 * all sarah_chat_* public functions so your plugin never crashes if Sarah Chat
 * is not installed or not yet configured.
 *
 * Requirements:
 *   - Sarah AI Client plugin must be installed and activated.
 *   - No direct dependency on Sarah Chat's internal classes.
 *
 * Quick start:
 *
 *   // Check availability
 *   if ( ! SarahChatAdapter::isAvailable() ) {
 *       return; // Sarah Chat not installed
 *   }
 *
 *   // One-time setup (usually on your plugin's activation hook)
 *   $result = SarahChatAdapter::setup([
 *       'server_url'  => 'https://server.example.com/wp-json',
 *       'platform_key' => 'your-platform-key',
 *   ]);
 *
 *   // Fetch sessions
 *   $sessions = SarahChatAdapter::getSessions(10);
 *   foreach ($sessions['data'] as $session) {
 *       echo $session['uuid'];
 *   }
 *
 *   // Fetch history
 *   $history = SarahChatAdapter::getSessionHistory($session['uuid']);
 *   foreach ($history['messages'] as $msg) {
 *       echo $msg['role'] . ': ' . $msg['content'];
 *   }
 */

if (! defined('ABSPATH')) {
    exit;
}

class SarahChatAdapter
{
    // ─── Availability ─────────────────────────────────────────────────────

    /**
     * Returns true if Sarah Chat is installed with its Public API loaded.
     * Always call this before any other method.
     */
    public static function isAvailable(): bool
    {
        return function_exists('sarah_chat_exists') && sarah_chat_exists();
    }

    /**
     * Returns true if Sarah Chat is installed AND fully configured.
     */
    public static function isReady(): bool
    {
        if (! self::isAvailable()) {
            return false;
        }
        return sarah_chat_is_ready();
    }

    // ─── Setup ────────────────────────────────────────────────────────────

    /**
     * Runs Quick Setup to connect and provision this site on the Sarah AI Server.
     *
     * @param array $payload {
     *   @type string $server_url      Required. Base WP REST API URL of the server.
     *   @type string $platform_key    Required. Platform authentication key.
     *   @type string $site_name       Optional.
     *   @type string $site_url        Optional.
     *   @type string $whmcs_key       Optional.
     *   @type string $openai_api_key  Optional.
     * }
     * @return array { success: bool, ready: bool, error: string|null }
     */
    public static function setup(array $payload): array
    {
        if (! self::isAvailable()) {
            return ['success' => false, 'ready' => false, 'error' => 'Sarah Chat is not installed'];
        }
        try {
            return sarah_chat_setup($payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'ready' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Stats ────────────────────────────────────────────────────────────

    /**
     * Returns total session and message counts for this site.
     *
     * @return array { success: bool, data: array{total_sessions: int, total_messages: int}, error: string|null }
     */
    public static function getSiteStats(): array
    {
        if (! self::isAvailable()) {
            return ['success' => false, 'data' => [], 'error' => 'Sarah Chat is not installed'];
        }
        try {
            return sarah_chat_get_site_stats();
        } catch (\Throwable $e) {
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    // ─── Sessions ─────────────────────────────────────────────────────────

    /**
     * Returns a list of recent chat sessions.
     *
     * @param int $limit Max number of sessions (default 20, server caps at 100).
     * @return array { success: bool, data: array, error: string|null }
     */
    public static function getSessions(int $limit = 20): array
    {
        if (! self::isAvailable()) {
            return ['success' => false, 'data' => [], 'error' => 'Sarah Chat is not installed'];
        }
        try {
            return sarah_chat_get_sessions(['limit' => $limit]);
        } catch (\Throwable $e) {
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Returns session metadata and the full ordered message history.
     *
     * @param string $sessionUuid Session UUID.
     * @return array { success: bool, session: array|null, messages: array, error: string|null }
     */
    public static function getSessionHistory(string $sessionUuid): array
    {
        if (! self::isAvailable()) {
            return ['success' => false, 'session' => null, 'messages' => [], 'error' => 'Sarah Chat is not installed'];
        }
        try {
            return sarah_chat_get_session_history($sessionUuid);
        } catch (\Throwable $e) {
            return ['success' => false, 'session' => null, 'messages' => [], 'error' => $e->getMessage()];
        }
    }

    // ─── Config ───────────────────────────────────────────────────────────

    /**
     * Sets Sarah Chat configuration values.
     *
     * Allowed keys: widget_enabled, greeting_message, server_url,
     *               account_key, site_key, platform_key.
     *
     * @param array $values Key-value pairs.
     * @return array { success: bool, saved: string[], errors: string[], error: string|null }
     */
    public static function setConfig(array $values): array
    {
        if (! self::isAvailable()) {
            return ['success' => false, 'saved' => [], 'errors' => [], 'error' => 'Sarah Chat is not installed'];
        }
        try {
            return sarah_chat_set_config($values);
        } catch (\Throwable $e) {
            return ['success' => false, 'saved' => [], 'errors' => [], 'error' => $e->getMessage()];
        }
    }
}
