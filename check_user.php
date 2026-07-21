<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = config('database.connections.sqlite.database');
echo $db . PHP_EOL;
$user = App\Models\User::where('email', 'admin@test.com')->first();
if ($user) {
    echo 'found' . PHP_EOL;
    echo $user->password . PHP_EOL;
    echo password_verify('password123', $user->password) ? 'verified' : 'not verified';
} else {
    echo 'not-found' . PHP_EOL;
}
