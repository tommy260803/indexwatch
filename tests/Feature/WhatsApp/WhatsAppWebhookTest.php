<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Alert;
use App\Models\AuthorizedContact;
use App\Models\Server;
use App\Models\SqlIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock local environment to bypass signature verification in tests
        $this->app['env'] = 'local';
    }

    protected function mockWhatsAppHttp(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);
    }

    #[Test]
    public function test_webhook_verify_challenge(): void
    {
        config([
            'services.whatsapp.verify_token' => 'test_token',
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $response = $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test_token&hub.challenge=12345');

        $response->assertStatus(200);
        $response->assertSee('12345');
    }

    #[Test]
    public function test_webhook_verify_invalid_token(): void
    {
        config([
            'services.whatsapp.verify_token' => 'test_token',
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $response = $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=12345');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_webhook_ignores_non_message_payload(): void
    {
        $this->mockWhatsAppHttp();

        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $response = $this->post('/api/webhook/whatsapp', [
            'entry' => [['changes' => [['value' => []]]]],
        ]);

        $response->assertJson(['status' => 'ignored']);
    }

    #[Test]
    public function test_webhook_idempotency_prevents_duplicate_processing(): void
    {
        $this->mockWhatsAppHttp();

        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'fragmentation',
            'status' => 'pending',
        ]);

        $contact = AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        $payload = $this->buttonReplyPayload('rebuild_' . $alert->id, '+51999999999', 'msg_123');

        // First request
        $response1 = $this->post('/api/webhook/whatsapp', $payload);
        $response1->assertJson(['status' => 'ok']);

        // Second request with same message_id
        $response2 = $this->post('/api/webhook/whatsapp', $payload);
        $response2->assertJson(['status' => 'duplicate']);
    }

    #[Test]
    public function test_webhook_rejects_unauthorized_contact(): void
    {
        $this->mockWhatsAppHttp();

        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'pending',
        ]);

        $payload = $this->buttonReplyPayload('rebuild_' . $alert->id, '+51987654321', 'msg_456');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'unauthorized']);
    }

    #[Test]
    public function test_webhook_rejects_invalid_action_for_alert_type(): void
    {
        $this->mockWhatsAppHttp();

        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'stale_statistics',
            'status' => 'pending',
        ]);

        $contact = AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        // Try to execute 'rebuild' on a stale_statistics alert (not allowed)
        $payload = $this->buttonReplyPayload('rebuild_' . $alert->id, '+51999999999', 'msg_789');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'error', 'reason' => 'action not allowed for this alert type']);
    }

    #[Test]
    public function test_webhook_approves_alert_and_schedules(): void
    {
        $this->mockWhatsAppHttp();

        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
            'indexwatch.maintenance.lock_store' => 'database',
        ]);

        $server = Server::factory()->create(['timezone' => 'America/Lima']);
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'fragmentation',
            'status' => 'pending',
            'recommended_action' => 'REBUILD',
        ]);

        $contact = AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        // Create a maintenance window for today - use explicit times that don't cross midnight
        $today = (int) now($server->timezone)->format('w');
        $server->maintenanceWindows()->create([
            'day_of_week' => $today,
            'start_time' => '22:00',
            'end_time' => '23:00',
            'active' => true,
        ]);

        $payload = $this->buttonReplyPayload('rebuild_' . $alert->id, '+51999999999', 'msg_999');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'ok']);

        $alert->refresh();
        $this->assertTrue(in_array($alert->status->value, ['approved', 'scheduled']));
    }

    private function buttonReplyPayload(string $buttonId, string $from, string $messageId): array
    {
        return [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'id' => $messageId,
                                        'from' => $from,
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => [
                                                'id' => $buttonId,
                                                'title' => 'REBUILD',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}