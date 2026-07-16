<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/api/dashboard/data', [DashboardController::class, 'data']);

Route::get('/v1/channels/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/v1/channels/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
