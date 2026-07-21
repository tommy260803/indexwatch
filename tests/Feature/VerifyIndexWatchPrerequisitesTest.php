<?php

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyIndexWatchPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_when_server_does_not_exist(): void
    {
        $this->artisan('indexwatch:verify', ['server' => 999])
            ->expectsOutput('The requested IndexWatch server does not exist.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_server_exists_but_sqlsrv_extension_is_missing(): void
    {
        Server::factory()->create([
            'name' => 'Test Server',
            'host' => '127.0.0.1',
            'database_name' => 'master',
            'username' => 'sa',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->artisan('indexwatch:verify', ['server' => 1])
            ->expectsOutput('The pdo_sqlsrv PHP extension is required.')
            ->assertExitCode(1);
    }
}
