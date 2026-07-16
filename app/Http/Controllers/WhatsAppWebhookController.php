<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

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
        $payload = $request->all();

        $buttonId = data_get($payload, 'messages.0.reply.buttons_reply.id');
        $from     = data_get($payload, 'messages.0.from');

        if (!$buttonId) {
            return response()->json(['status' => 'ignored']);
        }

        [$action, $alertId] = explode('_', $buttonId, 2);

        $alert = Alert::with('index')->find($alertId);

        if (!$alert || $alert->status !== 'pending') {
            return response()->json(['status' => 'already_processed']);
        }

        $alert->update(['status' => 'in_progress', 'action_taken' => $action]);

        $script = $this->generateTSQL($action, $alert->index);

        sleep(1);

        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        
        // Simular el arreglo en la base de datos para la presentación
        if ($action === 'rebuild') {
            $alert->index->update(['fragmentation_percent' => 2.00]);
        } elseif ($action === 'reorganize') {
            $alert->index->update(['fragmentation_percent' => 4.50]);
        }

        $whatsapp->sendConfirmation($from,
            "✅ *Operación completada — IndexWatch*\n\n"
            . "🔹 Índice: {$alert->index->index_name}\n"
            . "🔹 Acción: " . strtoupper($action) . "\n"
            . "🔹 Hora: " . now()->format('H:i:s') . "\n\n"
            . "📜 Script ejecutado:\n{$script}"
        );

        return response()->json(['status' => 'ok']);
    }

    private function generateTSQL(string $action, $index): string
    {
        return match($action) {
            'rebuild'    => "ALTER INDEX [{$index->index_name}] ON [dbo].[{$index->table_name}] REBUILD WITH (ONLINE = ON);",
            'reorganize' => "ALTER INDEX [{$index->index_name}] ON [dbo].[{$index->table_name}] REORGANIZE;",
            'stats'      => "UPDATE STATISTICS [dbo].[{$index->table_name}] [{$index->index_name}];",
            'drop'       => "DROP INDEX [{$index->index_name}] ON [dbo].[{$index->table_name}];",
            default      => '-- Operación no reconocida',
        };
    }
}