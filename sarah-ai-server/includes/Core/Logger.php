<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

class Logger
{
    private static string $logFile = '';
    private static ?bool  $enabled = null;

    // ── Enable / Disable ────────────────────────────────────────────────────

    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled ?? true; // default on until explicitly set
    }

    // ── Core Write ──────────────────────────────────────────────────────────

    public static function write(string $level, string $context, string $message, array $data = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $context,
            $message,
            $data ? ' ' . wp_json_encode($data) : ''
        );

        file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }

    // ── Shorthand Methods ────────────────────────────────────────────────────

    public static function error(string $context, string $message, array $data = []): void
    {
        self::write('error', $context, $message, $data);
    }

    public static function warn(string $context, string $message, array $data = []): void
    {
        self::write('warn', $context, $message, $data);
    }

    public static function info(string $context, string $message, array $data = []): void
    {
        self::write('info', $context, $message, $data);
    }

    // ── PHP Error Hooks ──────────────────────────────────────────────────────

    /**
     * Register shutdown handler to catch PHP fatal errors.
     * Call once from Plugin::boot(). Safe to call on every request.
     */
    public static function registerShutdownHandler(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if (! $error) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (! in_array($error['type'], $fatal, true)) {
                return;
            }
            self::error('php:fatal', $error['message'], [
                'file' => basename($error['file']),
                'line' => $error['line'],
            ]);
        });

        set_exception_handler(function (\Throwable $e) {
            self::error('php:exception', $e->getMessage(), [
                'class' => get_class($e),
                'file'  => basename($e->getFile()),
                'line'  => $e->getLine(),
            ]);
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function path(): string
    {
        if (self::$logFile === '') {
            self::$logFile = SARAH_AI_SERVER_PATH . 'sarah-ai-server.log';
        }
        return self::$logFile;
    }
}
