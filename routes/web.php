<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('servers', ServerController::class);
    Route::resource('contacts', ContactController::class);
    Route::get('/indices', function () { return view('indices'); })->name('indices');
    Route::get('/actions', function () { return view('actions'); })->name('actions');
    Route::get('/settings', function () { return view('settings'); })->name('settings');
    Route::get('/api/dashboard/data', [DashboardController::class, 'data']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';