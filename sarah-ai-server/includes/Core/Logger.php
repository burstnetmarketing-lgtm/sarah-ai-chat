<?php

declare(strict_types=1);

namespace ProjectName\Core;

class Logger
{
    private static string $logFile = '';

    private static function path(): string
    {
        if (self::$logFile === '') {
            self::$logFile = PROJECT_NAME_PATH . 'project-name.log';
        }
        return self::$logFile;
    }

    public static function write(string $level, string $context, string $message, array $data = []): void
    {
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
}
