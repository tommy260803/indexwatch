<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Alert;
use App\Models\AuthorizedContact;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Services\WhatsApp\ActionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'local';

        config([
            'indexwatch.maintenance.lock_store' => 'database',
            'indexwatch.maintenance.unauthorized_policy' => 'reject',
            'indexwatch.maintenance.require_double_confirmation' => true,
        ]);
    }

    private function setWhatsAppConfig(): void
    {
        config([
            'services.whatsapp.token' => 'test_token',
            'services.whatsapp.phone_id' => 'test_phone_id',
            'services.whatsapp.app_secret' => 'test_secret',
        ]);
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
        ]);

        $response = $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test_token&hub.challenge=12345');

        $response->assertStatus(200);
        $response->assertSee('12345');
    }

    #[Test]
    public function test_webhook_verify_invalid_token(): void
    {
        $response = $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=12345');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_webhook_ignores_non_message_payload(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $response = $this->post('/api/webhook/whatsapp', [
            'entry' => [['changes' => [['value' => []]]]],
        ]);

        $response->assertJson(['status' => 'ignored']);
    }

    #[Test]
    public function test_webhook_idempotency_prevents_duplicate_processing(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'fragmentation',
            'status' => 'pending',
        ]);

        AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_123');

        $response1 = $this->post('/api/webhook/whatsapp', $payload);
        $response1->assertJson(['status' => 'ok']);

        $response2 = $this->post('/api/webhook/whatsapp', $payload);
        $response2->assertJson(['status' => 'duplicate']);
    }

    #[Test]
    public function test_webhook_rejects_unauthorized_contact(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'pending',
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51987654321', 'msg_456');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'unauthorized']);

        // Audit log should be created for the unauthorized attempt
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'unauthorized_attempt',
            'status' => 'rejected',
        ]);
    }

    #[Test]
    public function test_unauthorized_silent_policy_does_not_respond(): void
    {
        config(['indexwatch.maintenance.unauthorized_policy' => 'silent']);
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'pending',
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51987654321', 'msg_789');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'ignored']);
    }

    #[Test]
    public function test_webhook_rejects_invalid_action_for_alert_type(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'stale_statistics',
            'status' => 'pending',
        ]);

        AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_789');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'error', 'reason' => 'action not allowed for this alert type']);
    }

    #[Test]
    public function test_webhook_rejects_tampered_button_id(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'pending',
        ]);

        AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        // Tampered button ID (invalid HMAC)
        $payload = $this->buttonReplyPayload('rebuild_'.$alert->id.'_tampered', '+51999999999', 'msg_tamper');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'error', 'reason' => 'invalid button id']);
    }

    #[Test]
    public function test_webhook_approves_alert_and_schedules(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();
        Queue::fake();

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
            'role' => 'admin',
        ]);

        $now = now($server->timezone);
        $today = (int) $now->format('w');
        $startHour = max(0, $now->hour - 1);
        $endHour = min(23, $now->hour + 1);
        $server->maintenanceWindows()->create([
            'day_of_week' => $today,
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $endHour),
            'active' => true,
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_999');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'ok']);

        $alert->refresh();
        $this->assertContains($alert->status->value, ['approved', 'scheduled']);

        // Maintenance action should be created
        $this->assertDatabaseHas('maintenance_actions', [
            'alert_id' => $alert->id,
            'server_id' => $server->id,
            'action_type' => 'REBUILD',
        ]);

        // Audit log should be created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'authorized',
            'status' => 'approved',
            'actor_type' => 'whatsapp',
        ]);
    }

    #[Test]
    public function test_webhook_high_risk_action_requires_admin(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();
        Queue::fake();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        // DROP INDEX is very_high_risk
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => 'inactive',
            'status' => 'pending',
        ]);

        $contact = AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
            'role' => 'operator',
        ]);

        $buttonId = ActionCatalog::makeButtonId('drop_index', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_risky');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'error', 'reason' => 'high_risk_action_requires_admin']);
    }

    #[Test]
    public function test_webhook_rejects_already_processed_alert(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'succeeded',
        ]);

        AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        $buttonId = ActionCatalog::makeButtonId('rebuild', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_done');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'already_processed']);
    }

    #[Test]
    public function test_webhook_dismiss_creates_audit_log(): void
    {
        $this->setWhatsAppConfig();
        $this->mockWhatsAppHttp();

        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'status' => 'pending',
        ]);

        AuthorizedContact::factory()->create([
            'phone_e164' => '+51999999999',
            'active' => true,
        ]);

        $buttonId = ActionCatalog::makeButtonId('dismiss', $alert->id);
        $payload = $this->buttonReplyPayload($buttonId, '+51999999999', 'msg_dismiss');

        $response = $this->post('/api/webhook/whatsapp', $payload);

        $response->assertJson(['status' => 'ok']);

        $alert->refresh();
        $this->assertEquals('dismissed', $alert->status->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'dismissed',
            'status' => 'dismissed',
        ]);
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
