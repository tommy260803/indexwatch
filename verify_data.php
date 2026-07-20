<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$s = \App\Models\Server::first();
$u = \App\Models\User::first();
echo "=== INDEXWATCH DATA VERIFICATION ===\n\n";
echo "User: {$u->email} (role: {$u->role->value})\n";
echo "Server: {$s->name} (health: {$s->health_score})\n";
echo "Indexes: " . \App\Models\SqlIndex::count() . "\n";
echo "Alerts total: " . \App\Models\Alert::count() . "\n";
echo "Alerts pending: " . \App\Models\Alert::where('status','pending')->count() . "\n";
echo "Alerts approved: " . \App\Models\Alert::where('status','approved')->count() . "\n";
echo "Audit logs: " . \App\Models\AuditLog::count() . "\n";
echo "Maint windows: " . \App\Models\MaintenanceWindow::count() . "\n";
echo "Contacts: " . \App\Models\AuthorizedContact::count() . "\n";
echo "Reports: " . \App\Models\GeneratedReport::count() . "\n";
echo "\nSYSTEM READY FOR UI TESTING.\n";
echo "Run: php artisan serve\n";
echo "Open: http://127.0.0.1:8000\n";
echo "Login: test@example.com / admin123\n";