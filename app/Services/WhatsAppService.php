<?php

namespace App\Services;

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

    public function sendAlertWithButtons(string $to, array $data): array
    {
        $response = Http::withToken($this->token)
            ->withoutVerifying()
            ->post($this->baseUrl, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type' => 'button',
                'body' => [
                    'text' => $this->buildAlertMessage($data),
                ],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => "rebuild_{$data['alert_id']}",    'title' => 'REBUILD']],
                        ['type' => 'reply', 'reply' => ['id' => "reorganize_{$data['alert_id']}", 'title' => 'REORGANIZE']],
                        ['type' => 'reply', 'reply' => ['id' => "drop_{$data['alert_id']}",       'title' => 'DROP INDEX']],
                    ],
                ],
            ],
        ]);

        return $response->json();
    }

    public function sendConfirmation(string $to, string $message): void
    {
        Http::withToken($this->token)
            ->withoutVerifying()
            ->post($this->baseUrl, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);
    }

    private function buildAlertMessage(array $data): string
    {
        return "🚨 *ALERTA INDEXWATCH*\n\n"
            . "📋 *Índice:* {$data['index_name']}\n"
            . "📁 *Tabla:* {$data['table_name']}\n"
            . "⚠️ *Problema:* {$data['problem']}\n"
            . "💾 *Tamaño:* {$data['size_mb']} MB\n\n"
            . "¿Qué desea hacer?";
    }
}