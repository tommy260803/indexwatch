<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedContact;
use App\Models\Server;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function data(): JsonResponse
    {
        $server = Server::active()->first();

        $contacts = AuthorizedContact::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone_number' => $c->phone_e164,
                'role' => $c->role,
            ]);

        return response()->json([
            'thresholds' => [
                'warning' => $server ? (float) $server->warning_threshold : 5.0,
                'critical' => $server ? (float) $server->critical_threshold : 30.0,
            ],
            'contacts' => $contacts,
            'whatsapp_enabled' => true,
            'notifications' => [
                'email' => SystemSetting::getBoolean('alert_email', true),
                'realtime' => SystemSetting::getBoolean('alert_realtime', true),
                'weekly' => SystemSetting::getBoolean('alert_weekly', false),
                'whatsapp_commands' => SystemSetting::getBoolean('whatsapp_commands', true),
            ],
        ]);
    }

    public function saveThresholds(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'warning' => 'required|numeric|min:1|max:50',
            'critical' => 'required|numeric|min:20|max:80',
        ]);

        $servers = Server::active()->get();
        foreach ($servers as $server) {
            $server->update([
                'warning_threshold' => $request->warning,
                'critical_threshold' => $request->critical,
            ]);
        }

        return response()->json(['message' => 'Umbrales actualizados correctamente']);
    }

    public function saveWhatsappNumber(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        $contact = AuthorizedContact::where('active', true)->first();
        if ($contact) {
            $contact->update(['phone_e164' => $request->phone_number]);
        } else {
            AuthorizedContact::create([
                'name' => 'Alertas',
                'phone_e164' => $request->phone_number,
                'role' => 'operator',
                'active' => true,
            ]);
        }

        return response()->json(['message' => 'Número actualizado correctamente']);
    }

    public function saveNotifications(Request $request): JsonResponse
    {
        Gate::authorize('update', Server::class);

        $request->validate([
            'email' => 'required|boolean',
            'realtime' => 'required|boolean',
            'weekly' => 'required|boolean',
            'whatsapp_commands' => 'required|boolean',
        ]);

        SystemSetting::set('alert_email', $request->email ? '1' : '0');
        SystemSetting::set('alert_realtime', $request->realtime ? '1' : '0');
        SystemSetting::set('alert_weekly', $request->weekly ? '1' : '0');
        SystemSetting::set('whatsapp_commands', $request->whatsapp_commands ? '1' : '0');

        return response()->json(['message' => 'Preferencias de notificación actualizadas']);
    }
}
