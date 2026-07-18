<?php

namespace App\Services\SqlServer;

use App\Models\Server;
use Throwable;

class SqlServerErrorSanitizer
{
    public function sanitize(Throwable|string $error, Server $server): string
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        try {
            $password = $server->password;
        } catch (Throwable) {
            $password = null;
        }

        foreach (array_filter([$password, $server->username]) as $secret) {
            $message = str_ireplace((string) $secret, '[redacted]', $message);
        }

        $message = preg_replace('/\b(?:password|pwd)\s*=\s*[^;\s]+/i', 'password=[redacted]', $message) ?? $message;
        $message = preg_replace('/\s*\(Connection:.*?SQL:.*\)$/is', '', $message) ?? $message;
        $message = trim(strip_tags($message));

        return mb_substr($message !== '' ? $message : 'SQL Server operation failed.', 0, 1000);
    }
}
