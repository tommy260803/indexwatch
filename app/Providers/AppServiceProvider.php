<?php

namespace App\Providers;

use App\Models\Server;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the short class name to the full class name for container resolution
        $this->app->bind('App\Services\WhatsAppService', function () {
            return new \App\Services\WhatsApp\WhatsAppService();
        });
        
        $this->app->bind(\App\Services\WhatsApp\WhatsAppService::class, function () {
            return new \App\Services\WhatsApp\WhatsAppService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $server = Server::active()->first();
            $view->with('activeServer', $server);
        });
    }
}
