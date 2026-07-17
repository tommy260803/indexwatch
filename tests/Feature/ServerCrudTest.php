<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Contact;
use App\Models\IndexSnapshot;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_warning_threshold_must_be_lower_than_critical_threshold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('servers.create'))->post(route('servers.store'), $this->validPayload([
            'warning_threshold' => 40,
            'critical_threshold' => 30,
        ]));

        $response->assertSessionHasErrors('critical_threshold');
        $this->assertDatabaseCount('servers', 0);
    }

    public function test_password_is_not_visible_after_creating_or_updating_server(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user)->followingRedirects()->post(route('servers.store'), $this->validPayload([
            'name' => 'SQL-01',
            'password' => 'MySecret123!',
        ]));

        $created->assertOk();
        $created->assertDontSee('MySecret123!');

        $server = Server::firstOrFail();

        $updated = $this->actingAs($user)->followingRedirects()->patch(route('servers.update', $server), $this->validPayload([
            'password' => 'AnotherSecret456!',
        ], false));

        $updated->assertOk();
        $updated->assertDontSee('AnotherSecret456!');
    }

    public function test_soft_delete_keeps_related_records_alive(): void
    {
        $user = User::factory()->create();
        $server = Server::create($this->serverAttributes());
        $contact = Contact::create([
            'name' => 'DBA Principal',
            'phone_number' => '+51999999999',
            'role' => 'dba',
            'active' => true,
        ]);

        $server->contacts()->sync([$contact->id]);

        $sqlIndex = SqlIndex::create([
            'server_id' => $server->id,
            'table_name' => 'orders',
            'index_name' => 'IX_orders_created_at',
            'object_id' => 1001,
            'index_id_native' => 1,
        ]);

        $alert = Alert::create([
            'server_id' => $server->id,
            'sql_index_id' => $sqlIndex->id,
            'fingerprint' => 'server-'.$server->id.'-alert',
            'alert_type' => 'fragmentation',
            'severity' => 'critical',
            'status' => 'pending',
        ]);

        $snapshot = IndexSnapshot::create([
            'sql_index_id' => $sqlIndex->id,
            'fragmentation_percent' => 52.4,
            'scanned_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('servers.destroy', $server))->assertRedirect(route('servers.index'));

        $this->assertSoftDeleted('servers', ['id' => $server->id]);
        $this->assertDatabaseHas('server_contact', ['server_id' => $server->id, 'contact_id' => $contact->id]);
        $this->assertDatabaseHas('sql_indexes', ['id' => $sqlIndex->id]);
        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
        $this->assertDatabaseHas('index_snapshots', ['id' => $snapshot->id]);
    }

    public function test_editing_without_password_preserves_existing_encrypted_password(): void
    {
        $user = User::factory()->create();
        $server = Server::create($this->serverAttributes([
            'password' => 'original-secret',
            'name' => 'SQL-Editable',
        ]));

        $originalPassword = $server->getRawOriginal('password');

        $this->actingAs($user)->patch(route('servers.update', $server), $this->validPayload([
            'name' => 'SQL-Editable-Updated',
            'password' => '',
        ], false))->assertRedirect(route('servers.show', $server));

        $this->assertSame($originalPassword, $server->fresh()->getRawOriginal('password'));
    }

    protected function validPayload(array $overrides = [], bool $includePassword = true): array
    {
        $payload = $this->serverAttributes([
            'password' => $includePassword ? 'MySecret123!' : '',
        ]);

        return array_replace_recursive($payload, $overrides);
    }

    protected function serverAttributes(array $overrides = []): array
    {
        return array_replace([
            'name' => 'SQL-01',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database_name' => 'IndexWatch',
            'username' => 'sa',
            'password' => 'MySecret123!',
            'status' => 'active',
            'warning_threshold' => 5,
            'critical_threshold' => 30,
            'stats_stale_threshold' => 20,
            'minimum_index_pages' => 1000,
            'timezone' => 'America/Lima',
            'connection_options' => [
                'encrypt' => 1,
                'trust_server_certificate' => 0,
                'timeout' => 30,
            ],
            'contacts' => [],
        ], $overrides);
    }
}