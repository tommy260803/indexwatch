<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceActionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('servers', ServerController::class);
    Route::get('/servers/{server}/thresholds', [ServerController::class, 'edit'])->name('servers.thresholds');
    Route::post('/servers/{server}/test-connection', [ServerController::class, 'testConnection'])->name('servers.test-connection');
    Route::resource('contacts', ContactController::class);
    Route::get('/indices', function () { return view('indices'); })->name('indices');
    
    // Operations center
    Route::get('/actions', function () { return view('actions'); })->name('actions');
    
    // Audit logs
    Route::get('/audit', function () { return view('audit.index'); })->name('audit');
    
    // Reports
    Route::get('/reports', function () { return view('reports.index'); })->name('reports');
    
    // Settings
    Route::get('/settings', function () { return view('settings'); })->name('settings');
    
    // API dashboard data (kept in web for CSRF)
    Route::get('/api/dashboard/data', [DashboardController::class, 'data']);
    Route::get('/api/maintenance-actions/data', [MaintenanceActionController::class, 'data']);
    Route::post('/api/maintenance-actions/execute', [MaintenanceActionController::class, 'execute']);
    Route::post('/api/maintenance-actions/schedule', [MaintenanceActionController::class, 'schedule']);
    Route::patch('/api/maintenance-actions/{action}/type', [MaintenanceActionController::class, 'updateType']);
    Route::delete('/api/maintenance-actions/{action}', [MaintenanceActionController::class, 'cancel']);
    Route::get('/api/settings', [\App\Http\Controllers\SettingsController::class, 'data']);
    Route::post('/api/settings/thresholds', [\App\Http\Controllers\SettingsController::class, 'saveThresholds']);
    Route::post('/api/settings/whatsapp', [\App\Http\Controllers\SettingsController::class, 'saveWhatsappNumber']);
    Route::post('/api/settings/notifications', [\App\Http\Controllers\SettingsController::class, 'saveNotifications']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';