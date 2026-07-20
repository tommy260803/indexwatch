<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AuthorizedContact;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): mixed
    {
        $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, WhatsAppService $whatsapp): \Illuminate\Http\JsonResponse
    {
        // Verify HMAC signature from Meta
        $this->verifySignature($request);

        $payload = $request->all();
        $entry   = data_get($payload, 'entry.0');

        if (! $entry) {
            return response()->json(['status' => 'ignored', 'reason' => 'no entry']);
        }

        $changes = data_get($entry, 'changes.0.value');

        if (! $changes) {
            return response()->json(['status' => 'ignored', 'reason' => 'no changes']);
        }

        // Handle message (interactive button reply)
        $messages = data_get($changes, 'messages', []);
        if (! empty($messages)) {
            return $this->handleMessage($messages[0], $changes, $whatsapp);
        }

        // Handle status updates (sent, delivered, read, failed)
        $statuses = data_get($changes, 'statuses', []);
        if (! empty($statuses)) {
            Log::info('WhatsApp status update', ['statuses' => $statuses]);
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'no message or status']);
    }

    private function verifySignature(Request $request): void
    {
        $appSecret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (! $appSecret || ! $signature) {
            // In local development without app_secret, allow but log warning
            if (app()->environment('local')) {
                Log::warning('WhatsApp webhook received without X-Hub-Signature-256 (local dev)');
                return;
            }
            throw new InvalidArgumentException('Missing app_secret or signature header');
        }

        $payload = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('WhatsApp webhook invalid signature', [
                'expected_prefix' => substr($expected, 0, 10),
                'received_prefix' => substr($signature, 0, 10),
            ]);
            throw new InvalidArgumentException('Invalid signature');
        }
    }

    private function handleMessage(array $message, array $changes, WhatsAppService $whatsapp): \Illuminate\Http\JsonResponse
    {
        $messageId = data_get($message, 'id');
        $from      = data_get($message, 'from');
        $type      = data_get($message, 'type');

        // Idempotency: check if we already processed this message ID
        $existingEvent = WhatsAppWebhookEvent::where('message_id', $messageId)->first();
        if ($existingEvent) {
            return response()->json(['status' => 'duplicate', 'message_id' => $messageId]);
        }

        // Handle interactive button reply
        if ($type === 'interactive') {
            $buttonReply = data_get($message, 'interactive.button_reply');
            if ($buttonReply) {
                return $this->handleButtonReply($buttonReply, $from, $messageId, $whatsapp, $changes);
            }
        }

        // Handle text messages (optional: for help commands, etc.)
        if ($type === 'text') {
            $text = data_get($message, 'text.body');
            Log::info('WhatsApp text message received', ['from' => $from, 'text' => $text]);
            return response()->json(['status' => 'ignored', 'reason' => 'text message not handled']);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'unsupported message type']);
    }

private function handleButtonReply(array $buttonReply, string $from, string $messageId, WhatsAppService $whatsapp, array $payload): \Illuminate\Http\JsonResponse
    {
        $buttonId = data_get($buttonReply, 'id');
        $title    = data_get($buttonReply, 'title');

        if (! $buttonId) {
            return response()->json(['status' => 'ignored', 'reason' => 'no button id']);
        }

        // Parse button ID: action_alertId (e.g., "rebuild_123")
        $parts = explode('_', $buttonId, 2);
        if (count($parts) !== 2) {
            return response()->json(['status' => 'error', 'reason' => 'invalid button id format']);
        }

        [$action, $alertId] = $parts;

        $alert = Alert::with(['sqlIndex', 'server'])->find($alertId);

        if (! $alert) {
            return response()->json(['status' => 'error', 'reason' => 'alert not found']);
        }

        // Validate contact is authorized (use AuthorizedContact model)
        $contact = AuthorizedContact::where('phone_e164', $from)
            ->where('active', true)
            ->first();

        if (! $contact || ! $contact->isActive()) {
            Log::warning('WhatsApp: unauthorized contact attempted action', ['from' => $from, 'action' => $action]);
            return response()->json(['status' => 'unauthorized']);
        }

        // Validate alert can be approved
        if (! $alert->canBeApproved()) {
            return response()->json(['status' => 'already_processed', 'current_status' => $alert->status?->value]);
        }

        // Validate action is allowed for this alert type
        $allowedActions = $this->getAllowedActionsForAlert($alert);
        if (! in_array($action, $allowedActions, true)) {
            return response()->json(['status' => 'error', 'reason' => 'action not allowed for this alert type']);
        }

        // Handle DISMISS action specially
        if ($action === 'dismiss') {
            $alert->forceFill([
                'status' => \App\Enums\AlertStatus::Dismissed,
                'responded_by_contact_id' => $contact->id,
                'responded_action' => \App\Enums\RecommendedAction::Ignore,
            ])->save();

            // Record webhook event
            WhatsAppWebhookEvent::create([
                'message_id' => $messageId,
                'from'       => $from,
                'action'     => $action,
                'alert_id'   => $alert->id,
                'contact_id' => $contact->id,
                'payload'    => $payload,
            ]);

            $whatsapp->sendConfirmation($from, sprintf(
                "🗑️ *Alerta descartada — IndexWatch*\n\n" .
                "🔹 Índice: %s\n" .
                "🔹 Acción: DESCARTAR\n" .
                "🔹 Hora: %s",
                $alert->getSubjectDisplay(),
                now()->format('H:i:s')
            ));

            return response()->json(['status' => 'ok']);
        }

        // Record the webhook event for idempotency
        WhatsAppWebhookEvent::create([
            'message_id' => $messageId,
            'from'       => $from,
            'action'     => $action,
            'alert_id'   => $alert->id,
            'contact_id' => $contact->id,
            'payload'    => $payload,
        ]);

        // Approve the alert and schedule for maintenance window
        $alert->forceFill([
            'status'                => \App\Enums\AlertStatus::Approved,
            'approved_by_contact_id' => $contact->id,
            'approved_at'           => now(),
            'responded_by_contact_id' => $contact->id,
            'responded_action'      => \App\Enums\RecommendedAction::tryFrom(strtoupper($action)),
        ])->save();

        // Determine next maintenance window
        $windowResolver = app(\App\Services\Maintenance\MaintenanceWindowResolver::class);
        $scheduledFor   = $windowResolver->resolveNextWindow($alert->server);

        if ($scheduledFor) {
            $alert->forceFill([
                'status'         => \App\Enums\AlertStatus::Scheduled,
                'scheduled_for'  => $scheduledFor,
            ])->save();

            $whatsapp->sendConfirmation($from, sprintf(
                "✅ *Acción aprobada — IndexWatch*\n\n" .
                "🔹 Índice: %s\n" .
                "🔹 Acción: %s\n" .
                "🔹 Programada para: %s (%s)\n\n" .
                "Se ejecutará en la próxima ventana de mantenimiento.",
                $alert->getSubjectDisplay(),
                strtoupper($action),
                $scheduledFor->format('d/m/Y H:i'),
                $alert->server->timezone
            ));
        } else {
            // No maintenance window configured - keep as approved pending manual trigger
            $alert->forceFill([
                'status' => \App\Enums\AlertStatus::Approved,
            ])->save();

            $whatsapp->sendConfirmation($from, sprintf(
                "✅ *Acción aprobada — IndexWatch*\n\n" .
                "🔹 Índice: %s\n" .
                "🔹 Acción: %s\n\n" .
                "⚠️ No hay ventana de mantenimiento configurada. La acción queda aprobada pendiente de ejecución manual.",
                $alert->getSubjectDisplay(),
                strtoupper($action)
            ));
        }

        // Dispatch maintenance job if scheduled
        if ($scheduledFor) {
            \App\Jobs\ExecuteMaintenanceJob::dispatch($alert->id)
                ->delay($scheduledFor->diffInSeconds(now()));
        }

        return response()->json(['status' => 'ok']);
    }

    private function getAllowedActionsForAlert(Alert $alert): array
    {
        $riskLevel = $alert->recommended_action?->riskLevel() ?? 'none';

        return match ($alert->alert_type?->value) {
            'fragmentation' => ['rebuild', 'reorganize', 'review', 'dismiss'],
            'fill_factor' => ['rebuild', 'reorganize', 'review', 'dismiss'],
            'page_splits' => ['rebuild', 'reorganize', 'review', 'dismiss'],
            'stale_statistics' => ['stats', 'review', 'dismiss'],
            'missing_index' => ['review', 'create_index', 'dismiss'],
            'inactive' => ['review', 'disable_index', 'drop_index', 'dismiss'],
            'duplicate_index' => ['review', 'drop_index', 'dismiss'],
            'heap' => ['review', 'create_clustered', 'dismiss'],
            default => ['review', 'dismiss'],
        };
    }
}