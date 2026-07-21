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

        Log::info('WhatsApp webhook verification', [
            'mode' => $mode,
            'token' => $token,
            'challenge' => $challenge,
            'expected_token' => config('services.whatsapp.verify_token')
        ]);

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed');
        return response('Forbidden', 403);
    }

    public function handle(Request $request, WhatsAppService $whatsapp): JsonResponse
    {
        Log::info('===== WHATSAPP WEBHOOK CALLED =====');
        Log::info('Headers:', $request->headers->all());

        try {
            $this->processWebhook($request, $whatsapp);
            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook critical error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'ok'], 200);
        }
    }

    private function processWebhook(Request $request, WhatsAppService $whatsapp): void
    {
        Log::info('===== PROCESSING WEBHOOK =====');
        Log::info('Payload:', $request->all());

        $this->verifySignature($request);

        $payload = $request->all();
        $entry = data_get($payload, 'entry.0');

        if (! $entry) {
            Log::warning('No entry in payload');
            return;
        }

        $changes = data_get($entry, 'changes.0.value');

        if (! $changes) {
            Log::warning('No changes in payload');
            return;
        }

        $messages = data_get($changes, 'messages', []);

        if (! empty($messages)) {
            $message = $messages[0];
            $type = data_get($message, 'type');
            Log::info('Message detected', [
                'type' => $type,
                'message_id' => data_get($message, 'id'),
                'from' => data_get($message, 'from')
            ]);
            $this->handleMessage($message, $changes, $whatsapp);
            return;
        }

        $statuses = data_get($changes, 'statuses', []);
        if (! empty($statuses)) {
            Log::info('WhatsApp status update', ['status_count' => count($statuses)]);
            return;
        }

        Log::info('No message or status in payload');
    }

    private function verifySignature(Request $request): void
    {
        $appSecret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (! $appSecret) {
            Log::info('WhatsApp: Signature verification skipped (no app_secret configured)');
            return;
        }

        if ($signature && $appSecret) {
            $payload = $request->getContent();
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

            if (! hash_equals($expected, $signature)) {
                Log::warning('WhatsApp: Invalid signature received', [
                    'expected_prefix' => substr($expected, 0, 10),
                    'received_prefix' => substr($signature, 0, 10),
                ]);
            }
            return;
        }

        if (! $signature && $appSecret) {
            Log::warning('WhatsApp: No signature in headers but app_secret is configured. Processing anyway.');
        }
    }

    private function handleMessage(array $message, array $changes, WhatsAppService $whatsapp): void
    {
        $messageId = data_get($message, 'id');
        $from = data_get($message, 'from');
        $from = (str_starts_with($from, '+') ? '' : '+') . $from;
        $type = data_get($message, 'type');

        Log::info('===== HANDLING MESSAGE =====');
        Log::info('Message details:', [
            'message_id' => $messageId,
            'from' => $from,
            'type' => $type
        ]);

        if (WhatsAppWebhookEvent::where('message_id', $messageId)->exists()) {
            Log::info('Duplicate message', ['message_id' => $messageId]);
            return;
        }

        if ($type === 'interactive') {
            Log::info('Interactive message detected');
            $buttonReply = data_get($message, 'interactive.button_reply');
            if ($buttonReply) {
                Log::info('Button reply', ['button_id' => data_get($buttonReply, 'id')]);
                $this->handleButtonReply($buttonReply, $from, $messageId, $whatsapp, $changes);
            }
            return;
        }

        if ($type === 'text') {
            $text = data_get($message, 'text.body');
            Log::info('Text message received', ['from' => $from, 'text' => $text]);

            if ($text) {
                $this->handleTextMessage($text, $from, $messageId, $whatsapp);
            }
            return;
        }

        Log::warning('Unsupported message type', ['type' => $type]);
    }

    private function handleTextMessage(string $text, string $from, string $messageId, WhatsAppService $whatsapp): void
    {
        $response = strtoupper(trim($text));

        Log::info('Processing text response', [
            'from' => $from,
            'response' => $response
        ]);

        $alert = Alert::where('status', AlertStatus::Pending)
            ->whereDoesntHave('whatsappEvents', function ($query) use ($from) {
                $query->where('from', $from);
            })
            ->latest()
            ->first();

        if (!$alert) {
            Log::info('No pending alert for user', ['from' => $from]);
            $this->sendWhatsAppMessage($whatsapp, $from, "📋 No hay alertas pendientes para procesar.");
            return;
        }

        $contact = AuthorizedContact::where('phone_e164', $from)
            ->where('active', true)
            ->first();

        if (!$contact || !$contact->isActive()) {
            Log::warning('Unauthorized contact attempted action', ['from' => $from]);
            $this->sendWhatsAppMessage($whatsapp, $from, "❌ No está autorizado para realizar esta acción.");
            return;
        }

        try {
            WhatsAppWebhookEvent::create([
                'message_id' => $messageId,
                'from' => $from,
                'action' => $response,
                'alert_id' => $alert->id,
                'contact_id' => $contact->id,
                'payload' => ['type' => 'text', 'body' => $text],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create WhatsApp webhook event', ['error' => $e->getMessage()]);
        }

        switch ($response) {
            case 'REBUILD':
            case 'REORGANIZE':
                $this->handleApproval($alert, $contact, $response, $from, $whatsapp);
                break;
            case 'DESCARTAR':
            case 'IGNORAR':
                $this->handleDismiss($alert, $contact, $from, $whatsapp);
                break;
            default:
                Log::info('Unknown text response', ['response' => $response]);
                $this->sendWhatsAppMessage($whatsapp, $from, "❌ Comando no reconocido. Usa: REBUILD, REORGANIZE, DESCARTAR o IGNORAR.");
        }
    }

    private function handleButtonReply(array $buttonReply, string $from, string $messageId, WhatsAppService $whatsapp, array $payload): void
    {
        try {
            $buttonId = data_get($buttonReply, 'id');

            Log::info('===== HANDLING BUTTON REPLY =====');
            Log::info('Button ID:', ['button_id' => $buttonId]);

            if (! $buttonId) {
                Log::warning('No button id');
                return;
            }

            $parsed = ActionCatalog::parseButtonId($buttonId);
            Log::info('Parsed button:', ['parsed' => $parsed]);

            if (! $parsed) {
                Log::warning('WhatsApp: invalid or tampered button ID', ['button_id' => $buttonId]);
                $this->sendWhatsAppMessage($whatsapp, $from, "❌ El botón no es válido o ha expirado.");
                return;
            }

            $action = $parsed['action'];
            $alertId = $parsed['alert_id'];

            Log::info('Action and Alert:', ['action' => $action, 'alert_id' => $alertId]);

            $alert = Alert::with(['sqlIndex', 'server'])->find($alertId);

            if (! $alert) {
                Log::warning('Alert not found', ['alert_id' => $alertId]);
                $this->sendWhatsAppMessage($whatsapp, $from, "❌ Alerta no encontrada.");
                return;
            }

            Log::info('Alert found:', [
                'id' => $alert->id,
                'status' => $alert->status->value,
                'subject' => $alert->getSubjectDisplay()
            ]);

            $contact = AuthorizedContact::where('phone_e164', $from)
                ->where('active', true)
                ->first();

            if (! $contact || ! $contact->isActive()) {
                Log::warning('Unauthorized contact', ['from' => $from]);
                $this->sendWhatsAppMessage($whatsapp, $from, "❌ No está autorizado.");
                return;
            }

            Log::info('Contact authorized:', ['contact_id' => $contact->id, 'name' => $contact->name]);

            if (! $alert->canBeApproved()) {
                Log::info('Alert cannot be approved', ['status' => $alert->status->value]);
                $this->sendWhatsAppMessage($whatsapp, $from, "⚠️ Esta alerta ya fue procesada.");
                return;
            }

            $allowedActions = array_keys(ActionCatalog::getAllowedActions($alert->alert_type));
            $allowedActions[] = 'dismiss';
            if (! in_array($action, $allowedActions, true)) {
                Log::warning('Action not allowed', ['action' => $action]);
                $this->sendWhatsAppMessage($whatsapp, $from, "❌ Acción no permitida.");
                return;
            }

            try {
                WhatsAppWebhookEvent::create([
                    'message_id' => $messageId,
                    'from' => $from,
                    'action' => $action,
                    'alert_id' => $alert->id,
                    'contact_id' => $contact->id,
                    'payload' => $payload,
                ]);
                Log::info('Webhook event recorded');
            } catch (\Throwable $e) {
                Log::error('Failed to create WhatsApp webhook event', ['error' => $e->getMessage()]);
            }

            if ($action === 'dismiss') {
                $this->handleDismiss($alert, $contact, $from, $whatsapp);
                return;
            }

            $this->handleApproval($alert, $contact, $action, $from, $whatsapp);
        } catch (\Throwable $e) {
            Log::error('WhatsApp handleButtonReply error', [
                'button_id' => data_get($buttonReply, 'id'),
                'from' => $from,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendWhatsAppMessage(WhatsAppService $whatsapp, string $to, string $message): void
    {
        try {
            $whatsapp->sendMessage($to, $message);
            Log::info('WhatsApp message sent', ['to' => $to]);
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp message', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDismiss(Alert $alert, AuthorizedContact $contact, string $from, WhatsAppService $whatsapp): void
    {
        try {
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

            Log::info('Alert dismissed', ['alert_id' => $alert->id]);

            $this->sendWhatsAppMessage($whatsapp, $from, sprintf(
                "✅ Alerta descartada\n\n📋 Recurso: %s",
                $alert->getSubjectDisplay()
            ));
        } catch (\Throwable $e) {
            Log::error('handleDismiss error', ['error' => $e->getMessage()]);
        }
    }

    private function handleApproval(Alert $alert, AuthorizedContact $contact, string $action, string $from, WhatsAppService $whatsapp): void
    {
        try {
            Log::info('handleApproval START', ['alert_id' => $alert->id, 'action' => $action]);

            $recommendedAction = ActionCatalog::getRecommendedAction($action, $alert->alert_type);

            if (
                config('indexwatch.maintenance.require_double_confirmation', true)
                && ActionCatalog::requiresDoubleConfirmation($action, $alert->alert_type)
                && ! $contact->isAdmin()
            ) {
                Log::warning('Double confirmation required');
                $this->sendWhatsAppMessage($whatsapp, $from, "⚠️ Requiere confirmación de administrador.");
                return;
            }

            $alert->forceFill([
                'status' => AlertStatus::Approved,
                'approved_by_contact_id' => $contact->id,
                'approved_at' => now(),
                'responded_by_contact_id' => $contact->id,
                'responded_action' => $recommendedAction,
            ])->save();

            $maintenanceAction = MaintenanceAction::create([
                'alert_id' => $alert->id,
                'server_id' => $alert->server_id,
                'sql_index_id' => $alert->sql_index_id,
                'action_type' => $recommendedAction,
                'status' => MaintenanceStatus::Pending,
                'initiated_by_contact_id' => $contact->id,
            ]);

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

            $this->sendWhatsAppMessage($whatsapp, $from, sprintf(
                "✅ Acción aprobada\n\n📋 Recurso: %s\n🔧 Acción: %s",
                $alert->getSubjectDisplay(),
                strtoupper($action)
            ));

            Log::info('handleApproval COMPLETE', ['alert_id' => $alert->id]);
        } catch (\Throwable $e) {
            Log::error('handleApproval error', ['error' => $e->getMessage()]);
        }
    }
}
