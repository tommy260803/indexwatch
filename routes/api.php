<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

// ---------- Webhook (no auth) ----------
Route::get('/webhook/whatsapp',  [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);
Route::post('/v1/channels/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

// ---------- Auth required ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Dashboard
    Route::get('/dashboard/data', [\App\Http\Controllers\DashboardController::class, 'data']);

    // Servers
    Route::get('/servers', [\App\Http\Controllers\ServerController::class, 'index']);
    Route::post('/servers', [\App\Http\Controllers\ServerController::class, 'store']);
    Route::get('/servers/{server}', [\App\Http\Controllers\ServerController::class, 'show']);
    Route::patch('/servers/{server}', [\App\Http\Controllers\ServerController::class, 'update']);
    Route::delete('/servers/{server}', [\App\Http\Controllers\ServerController::class, 'destroy']);
    Route::post('/servers/{server}/test-connection', [\App\Http\Controllers\ServerController::class, 'testConnection']);

    // Maintenance windows
    Route::get('/maintenance-windows', [\App\Http\Controllers\MaintenanceWindowController::class, 'index']);
    Route::post('/maintenance-windows', [\App\Http\Controllers\MaintenanceWindowController::class, 'store']);
    Route::patch('/maintenance-windows/{window}', [\App\Http\Controllers\MaintenanceWindowController::class, 'update']);
    Route::delete('/maintenance-windows/{window}', [\App\Http\Controllers\MaintenanceWindowController::class, 'destroy']);

    // Maintenance actions
    Route::get('/maintenance-actions', [\App\Http\Controllers\MaintenanceActionController::class, 'index']);
    Route::get('/maintenance-actions/{action}', [\App\Http\Controllers\MaintenanceActionController::class, 'show']);
    Route::post('/maintenance-actions/{action}/cancel', [\App\Http\Controllers\MaintenanceActionController::class, 'cancel']);

    // Audit logs
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index']);

    // Reports
    Route::post('/reports', [\App\Http\Controllers\ReportController::class, 'store']);
    Route::get('/reports/{report}', [\App\Http\Controllers\ReportController::class, 'show']);
    Route::get('/reports/{report}/download', [\App\Http\Controllers\ReportController::class, 'download']);
});