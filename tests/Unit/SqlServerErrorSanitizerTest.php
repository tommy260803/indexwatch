<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use RuntimeException;
use Tests\TestCase;

class SqlServerErrorSanitizerTest extends TestCase
{
    public function test_it_removes_credentials_and_sql_text(): void
    {
        $server = new Server;
        $server->forceFill([
            'username' => 'scanner_user',
            'password' => 'super-secret',
        ]);
        $error = new RuntimeException(
            'Login scanner_user password=super-secret failed (Connection: scan, SQL: SELECT secret FROM users)',
        );

        $sanitized = (new SqlServerErrorSanitizer)->sanitize($error, $server);

        $this->assertStringNotContainsString('scanner_user', $sanitized);
        $this->assertStringNotContainsString('super-secret', $sanitized);
        $this->assertStringNotContainsString('SELECT secret', $sanitized);
        $this->assertStringContainsString('[redacted]', $sanitized);
    }

    public function test_it_does_not_fail_when_encrypted_password_is_corrupt(): void
    {
        $server = new Server;
        $server->setRawAttributes([
            'username' => 'scanner_user',
            'password' => 'not-valid-encrypted-data',
        ]);

        $sanitized = (new SqlServerErrorSanitizer)->sanitize('Connection failed', $server);

        $this->assertSame('Connection failed', $sanitized);
    }
}
