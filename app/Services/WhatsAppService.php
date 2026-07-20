<?php

namespace App\Services\WhatsApp;

use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $baseUrl;
    private string $token;
    private string $phoneId;

    public function __construct()
    {
        $this->token   = config('services.whatsapp.token');
        $this->phoneId = config('services.whatsapp.phone_id');
        $this->baseUrl = "https://graph.facebook.com/v19.0/{$this->phoneId}/messages";
    }

    public function sendAlertWithButtons(string $to, Alert $alert): array
    {
        $actions = ActionCatalog::getAllowedActions($alert->alert_type);

        // Limit to max 3 buttons (WhatsApp limit)
        $buttons = array_slice($actions, 0, 3, true);

        $interactiveButtons = [];
        foreach ($buttons as $key => $title) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id'    => ActionCatalog::makeButtonId($key, $alert->id),
                    'title' => $title,
                ],
            ];
        }

        // Add a "Dismiss" option if there's room
        if (count($interactiveButtons) < 3) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id'    => "dismiss_{$alert->id}",
                    'title' => 'DESCARTAR',
                ],
            ];
        }

        $response = Http::withToken($this->token)
            ->post($this->baseUrl, [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'interactive',
                'interactive'       => [
                    'type' => 'button',
                    'body' => [
                        'text' => $this->buildAlertMessage($alert),
                    ],
                    'action' => [
                        'buttons' => $interactiveButtons,
                    ],
                ],
            ]);

        return $response->json();
    }

    public function sendConfirmation(string $to, string $message): void
    {
        Http::withToken($this->token)
            ->post($this->baseUrl, [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);
    }

    private function buildAlertMessage(Alert $alert): string
    {
        $subject = $alert->getSubjectDisplay();
        $action = $alert->recommended_action?->value ?? 'REVIEW';
        $frag   = $alert->fragmentation_percent ? " ({$alert->fragmentation_percent}%)" : '';
        $size   = $alert->metadata['size_mb'] ?? $alert->sqlIndex?->size_mb ?? 'N/A';

        return "🚨 *ALERTA INDEXWATCH*\n\n"
            . "📋 *Recurso:* {$subject}\n"
            . "⚠️ *Tipo:* {$alert->getTypeLabel()}\n"
            . "📊 *Severidad:* {$alert->getSeverityLabel()}{$frag}\n"
            . "💾 *Tamaño:* {$size} MB\n"
            . "🎯 *Acción recomendada:* {$action}\n\n"
            . "¿Qué desea hacer?";
    }
}