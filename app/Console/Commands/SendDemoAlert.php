<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendDemoAlert extends Command
{
    protected $signature   = 'demo:send-alert';
    protected $description = 'Envía una alerta de demo por WhatsApp';

    public function handle(WhatsAppService $whatsapp): void
    {
        $alert = Alert::with('index.server')
                      ->where('status', 'pending')
                      ->latest()
                      ->firstOrFail();

        $index = $alert->index;

        $problem = $alert->type === 'fragmentation'
            ? "Fragmentación del {$index->fragmentation_percent}% (Crítico)"
            : "Inactivo por más de 60 días (0 seeks/scans)";

        $this->info("Enviando alerta por WhatsApp...");

        $result = $whatsapp->sendAlertWithButtons(config('services.whatsapp.to'), [
            'alert_id'   => $alert->id,
            'index_name' => $index->index_name,
            'table_name' => $index->table_name,
            'problem'    => $problem,
            'size_mb'    => $index->size_mb,
        ]);

        if (isset($result['messages'][0]['id'])) {
            $alert->update(['whatsapp_message_id' => $result['messages'][0]['id']]);
            $this->info("✅ Alerta enviada correctamente.");
            $this->line("Message ID: " . $result['messages'][0]['id']);
        } else {
            $this->error("❌ Error al enviar:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        }
    }
}