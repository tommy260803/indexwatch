<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Enums\AuditActorType;
use App\Enums\AuditSource;
use App\Enums\MaintenanceStatus;
use App\Enums\RecommendedAction;
use App\Jobs\ExecuteMaintenanceJob;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\AuthorizedContact;
use App\Models\MaintenanceAction;
use App\Models\WhatsAppWebhookEvent;
use App\Services\Maintenance\MaintenanceWindowResolver;
use App\Services\WhatsApp\ActionCatalog;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): mixed
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, WhatsAppService $whatsapp): JsonResponse
    {
        $this->verifySignature($request);

        $payload = $request->all();
        $entry = data_get($payload, 'entry.0');

        if (! $entry) {
            return response()->json(['status' => 'ignored', 'reason' => 'no entry']);
        }

        $changes = data_get($entry, 'changes.0.value');

        if (! $changes) {
            return response()->json(['status' => 'ignored', 'reason' => 'no changes']);
        }

        $messages = data_get($changes, 'messages', []);
        if (! empty($messages)) {
            return $this->handleMessage($messages[0], $changes, $whatsapp);
        }

        $statuses = data_get($changes, 'statuses', []);
        if (! empty($statuses)) {
            Log::info('WhatsApp status update', ['status_count' => count($statuses)]);

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'no message or status']);
    }

    private function verifySignature(Request $request): void
    {
        $appSecret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (! $appSecret || ! $signature) {
            if (app()->environment('local', 'testing')) {
                Log::warning('WhatsApp webhook received without signature (dev environment)');

                return;
            }
            abort(403, 'Missing signature or app secret');
        }

        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('WhatsApp webhook invalid signature', [
                'expected_prefix' => substr($expected, 0, 10),
                'received_prefix' => substr($signature, 0, 10),
            ]);
            abort(403, 'Invalid signature');
        }
    }

    private function handleMessage(array $message, array $changes, WhatsAppService $whatsapp): JsonResponse
    {
        $messageId = data_get($message, 'id');
        $from = data_get($message, 'from');
        $type = data_get($message, 'type');

        // Idempotency: skip already-processed messages
        if (WhatsAppWebhookEvent::where('message_id', $messageId)->exists()) {
            return response()->json(['status' => 'duplicate', 'message_id' => $messageId]);
        }

        if ($type === 'interactive') {
            $buttonReply = data_get($message, 'interactive.button_reply');
            if ($buttonReply) {
                return $this->handleButtonReply($buttonReply, $from, $messageId, $whatsapp, $changes);
            }
        }

        if ($type === 'text') {
            Log::info('WhatsApp text message received', ['from' => $from]);

            return response()->json(['status' => 'ignored', 'reason' => 'text message not handled']);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'unsupported message type']);
    }

    private function handleButtonReply(array $buttonReply, string $from, string $messageId, WhatsAppService $whatsapp, array $payload): JsonResponse
    {
        $buttonId = data_get($buttonReply, 'id');

        if (! $buttonId) {
            return response()->json(['status' => 'ignored', 'reason' => 'no button id']);
        }

        // Parse and verify signed button ID
        $parsed = ActionCatalog::parseButtonId($buttonId);
        if (! $parsed) {
            Log::warning('WhatsApp: invalid or tampered button ID', ['button_id' => $buttonId]);

            return response()->json(['status' => 'error', 'reason' => 'invalid button id']);
        }

        $action = $parsed['action'];
        $alertId = $parsed['alert_id'];

        $alert = Alert::with(['sqlIndex', 'server'])->find($alertId);

        if (! $alert) {
            return response()->json(['status' => 'error', 'reason' => 'alert not found']);
        }

        // Validate authorized contact
        $contact = AuthorizedContact::where('phone_e164', $from)
            ->where('active', true)
            ->first();

        if (! $contact || ! $contact->isActive()) {
            return $this->handleUnauthorizedContact($from, $action, $alertId, $whatsapp);
        }

        // Validate alert can be approved
        if (! $alert->canBeApproved()) {
            return response()->json(['status' => 'already_processed', 'current_status' => $alert->status?->value]);
        }

        // Validate action is allowed for this alert type
        $allowedActions = array_keys(ActionCatalog::getAllowedActions($alert->alert_type));
        $allowedActions[] = 'dismiss';
        if (! in_array($action, $allowedActions, true)) {
            return response()->json(['status' => 'error', 'reason' => 'action not allowed for this alert type']);
        }

        // Record the webhook event (idempotency guard)
        WhatsAppWebhookEvent::create([
            'message_id' => $messageId,
            'from' => $from,
            'action' => $action,
            'alert_id' => $alert->id,
            'contact_id' => $contact->id,
            'payload' => $payload,
        ]);

        // Handle DISMISS
        if ($action === 'dismiss') {
            return $this->handleDismiss($alert, $contact, $from, $whatsapp);
        }

        // Handle approval
        return $this->handleApproval($alert, $contact, $action, $from, $whatsapp);
    }

    private function handleDismiss(Alert $alert, AuthorizedContact $contact, string $from, WhatsAppService $whatsapp): JsonResponse
    {
        $alert->forceFill([
            'status' => AlertStatus::Dismissed,
            'responded_by_contact_id' => $contact->id,
            'responded_action' => RecommendedAction::Ignore,
        ])->save();

        AuditLog::create([
            'server_id' => $alert->server_id,
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'action' => 'dismissed',
            'actor_type' => AuditActorType::WhatsApp,
            'actor_identifier' => $from,
            'source' => AuditSource::Webhook,
            'status' => 'dismissed',
        ]);

        $whatsapp->sendConfirmation($from, sprintf(
            "Alerta descartada — IndexWatch\n\n".
            "Indice: %s\n".
            'Hora: %s',
            $alert->getSubjectDisplay(),
            now()->format('H:i:s')
        ));

        return response()->json(['status' => 'ok']);
    }

    private function handleApproval(Alert $alert, AuthorizedContact $contact, string $action, string $from, WhatsAppService $whatsapp): JsonResponse
    {
        $recommendedAction = ActionCatalog::getRecommendedAction($action, $alert->alert_type);

        // Check if double confirmation is required
        if (
            config('indexwatch.maintenance.require_double_confirmation', true)
            && ActionCatalog::requiresDoubleConfirmation($action, $alert->alert_type)
            && ! $contact->isAdmin()
        ) {
            return response()->json([
                'status' => 'error',
                'reason' => 'high_risk_action_requires_admin',
            ]);
        }

        // Update alert
        $alert->forceFill([
            'status' => AlertStatus::Approved,
            'approved_by_contact_id' => $contact->id,
            'approved_at' => now(),
            'responded_by_contact_id' => $contact->id,
            'responded_action' => $recommendedAction,
        ])->save();

        // Create maintenance action record
        $maintenanceAction = MaintenanceAction::create([
            'alert_id' => $alert->id,
            'server_id' => $alert->server_id,
            'sql_index_id' => $alert->sql_index_id,
            'action_type' => $recommendedAction,
            'status' => MaintenanceStatus::Pending,
            'initiated_by_contact_id' => $contact->id,
        ]);

        // Audit: authorization recorded
        AuditLog::create([
            'server_id' => $alert->server_id,
            'auditable_type' => MaintenanceAction::class,
            'auditable_id' => $maintenanceAction->id,
            'action' => 'authorized',
            'actor_type' => AuditActorType::WhatsApp,
            'actor_identifier' => $from,
            'source' => AuditSource::Webhook,
            'status' => 'approved',
        ]);

        // Resolve maintenance window
        $windowResolver = app(MaintenanceWindowResolver::class);
        $scheduledFor = $windowResolver->resolveNextWindow($alert->server);

        if ($scheduledFor) {
            $alert->forceFill([
                'status' => AlertStatus::Scheduled,
                'scheduled_for' => $scheduledFor,
            ])->save();

            $maintenanceAction->forceFill([
                'status' => MaintenanceStatus::Scheduled,
                'scheduled_for' => $scheduledFor,
            ])->save();

            $whatsapp->sendConfirmation($from, sprintf(
                "Accion aprobada — IndexWatch\n\n".
                "Indice: %s\n".
                "Accion: %s\n".
                "Programada para: %s (%s)\n\n".
                'Se ejecutara en la proxima ventana de mantenimiento.',
                $alert->getSubjectDisplay(),
                strtoupper($action),
                $scheduledFor->format('d/m/Y H:i'),
                $alert->server->timezone
            ));

            ExecuteMaintenanceJob::dispatch($alert->id)
                ->delay($scheduledFor);
        } else {
            $whatsapp->sendConfirmation($from, sprintf(
                "Accion aprobada — IndexWatch\n\n".
                "Indice: %s\n".
                "Accion: %s\n\n".
                'No hay ventana de mantenimiento configurada. La accion queda aprobada pendiente de ejecucion manual.',
                $alert->getSubjectDisplay(),
                strtoupper($action)
            ));
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleUnauthorizedContact(string $from, string $action, int $alertId, WhatsAppService $whatsapp): JsonResponse
    {
        $policy = config('indexwatch.maintenance.unauthorized_policy', 'reject');

        Log::warning('WhatsApp: unauthorized contact attempted action', [
            'from' => $from,
            'action' => $action,
            'alert_id' => $alertId,
            'policy' => $policy,
        ]);

        if ($policy === 'silent') {
            return response()->json(['status' => 'ignored']);
        }

        // Reject with audit
        AuditLog::create([
            'auditable_type' => Alert::class,
            'auditable_id' => $alertId,
            'action' => 'unauthorized_attempt',
            'actor_type' => AuditActorType::WhatsApp,
            'actor_identifier' => $from,
            'source' => AuditSource::Webhook,
            'status' => 'rejected',
        ]);

        $whatsapp->sendConfirmation($from, 'No esta autorizado para realizar esta accion. Contacte al administrador.');

        return response()->json(['status' => 'unauthorized']);
    }
}
