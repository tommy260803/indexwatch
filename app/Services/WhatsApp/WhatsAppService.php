<?php

namespace App\Services\WhatsApp;

use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    private string $baseUrl;

    private string $token;

    private string $phoneId;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token', '');
        $this->phoneId = config('services.whatsapp.phone_id', '');
        $this->baseUrl = "https://graph.facebook.com/v19.0/{$this->phoneId}/messages";
    }

    public function sendAlertWithButtons(string $to, Alert $alert): array
    {
        $actions = ActionCatalog::getAllowedActions($alert->alert_type);

        // WhatsApp allows max 3 buttons
        $buttons = array_slice($actions, 0, 3, true);

        $interactiveButtons = [];
        foreach ($buttons as $key => $title) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => ActionCatalog::makeButtonId($key, $alert->id),
                    'title' => $title,
                ],
            ];
        }

        // Add "Dismiss" if room
        if (count($interactiveButtons) < 3) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => ActionCatalog::makeButtonId('dismiss', $alert->id),
                    'title' => 'DESCARTAR',
                ],
            ];
        }

        $body = $this->buildAlertMessage($alert);

        $response = $this->client()->post($this->baseUrl, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $interactiveButtons],
            ],
        ]);

        return $this->handleResponse($response, 'sendAlertWithButtons');
    }

    public function sendConfirmation(string $to, string $message): ?array
    {
        Log::info('WhatsApp: sendConfirmation START', ['to' => $to, 'message_length' => strlen($message)]);

        $response = $this->client()->post($this->baseUrl, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        Log::info('WhatsApp: sendConfirmation response received', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        return $this->handleResponse($response, 'sendConfirmation');
    }

    private function buildAlertMessage(Alert $alert): string
    {
        $subject = $alert->getSubjectDisplay();
        $frag = $alert->fragmentation_percent ? " ({$alert->fragmentation_percent}%)" : '';
        $size = $alert->metadata['size_mb'] ?? $alert->sqlIndex?->size_mb ?? 'N/A';

        return match ($alert->alert_type) {
            AlertType::Fragmentation, AlertType::FillFactor, AlertType::PageSplits => $this->buildIndexAlertMessage($subject, $alert, $frag, $size),
            AlertType::StaleStatistics => $this->buildStatsAlertMessage($subject, $alert),
            AlertType::MissingIndex => $this->buildMissingIndexMessage($subject, $alert),
            AlertType::Inactive, AlertType::DuplicateIndex => $this->buildInactiveIndexMessage($subject, $alert),
            AlertType::Heap => $this->buildHeapMessage($subject, $alert),
            default => $this->buildGenericAlertMessage($subject, $alert),
        };
    }

    private function buildIndexAlertMessage(string $subject, Alert $alert, string $frag, mixed $size): string
    {
        $action = $alert->recommended_action?->value ?? 'REVIEW';

        return "🚨 *ALERTA INDEXWATCH*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}{$frag}\n"
            . "💾 *Tamaño:* {$size} MB\n"
            . "🎯 *Acción recomendada:* {$action}\n\n"
            . '¿Qué desea hacer?';
    }

    private function buildStatsAlertMessage(string $subject, Alert $alert): string
    {
        return "📉 *ESTADÍSTICAS OBSOLETAS — IndexWatch*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}\n\n"
            . "Las estadísticas necesitan actualización para mantener planes de consulta óptimos.\n\n"
            . '¿Qué desea hacer?';
    }

    private function buildMissingIndexMessage(string $subject, Alert $alert): string
    {
        return "🔍 *ÍNDICE FALTANTE — IndexWatch*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}\n\n"
            . "Se detectó un índice faltante que podría mejorar significativamente el rendimiento.\n\n"
            . '¿Qué desea hacer?';
    }

    private function buildInactiveIndexMessage(string $subject, Alert $alert): string
    {
        return "💤 *ÍNDICE INACTIVO — IndexWatch*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}\n\n"
            . "Este índice no tiene uso reciente y consume espacio y recursos.\n\n"
            . '¿Qué desea hacer?';
    }

    private function buildHeapMessage(string $subject, Alert $alert): string
    {
        return "🏚️ *HEAP DETECTADO — IndexWatch*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}\n\n"
            . "La tabla no tiene índice clustered, lo que afecta rendimiento y consumo de espacio.\n\n"
            . '¿Qué desea hacer?';
    }

    private function buildGenericAlertMessage(string $subject, Alert $alert): string
    {
        return "🚨 *ALERTA INDEXWATCH*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}\n\n"
            . '¿Qué desea hacer?';
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->timeout(15)
            ->retry(2, 500);
    }

    private function handleResponse(Response $response, string $context): array
    {
        if ($response->successful()) {
            return $response->json('data', $response->json());
        }

        $status = $response->status();
        $body = $response->json('error', ['message' => 'Unknown error']);
        $errorCode = $body['code'] ?? null;
        $errorSubcode = $body['error_subcode'] ?? null;
        $errorMessage = $body['message'] ?? 'Unknown error';

        Log::warning('WhatsApp API error', [
            'context' => $context,
            'status' => $status,
            'error_code' => $errorCode,
            'error_subcode' => $errorSubcode,
            'error_message' => $errorMessage,
        ]);

        match (true) {
            $status === 429 => throw new RuntimeException(
                'WhatsApp rate limit exceeded. Retry later.'
            ),
            $status === 401 => throw new RuntimeException(
                'WhatsApp access token is invalid or expired.'
            ),
            $status === 400 && $errorSubcode === 131047 => throw new RuntimeException(
                'WhatsApp recipient phone number is not valid.'
            ),
            $status === 400 && $errorSubcode === 131026 => throw new RuntimeException(
                'WhatsApp message failed to send (recipient may have blocked the number).'
            ),
            $status === 400 && str_contains((string) $errorCode, '368') => throw new RuntimeException(
                'WhatsApp account temporarily restricted (possible spam detection).'
            ),
            default => Log::error('WhatsApp API unexpected error', [
                'context' => $context,
                'status' => $status,
                'error_code' => $errorCode,
            ]),
        };

        return ['error' => true, 'status' => $status];
    }
}
