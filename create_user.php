<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = new App\Models\User();
$user->name = 'Admin Test';
$user->email = 'admin@test.com';
$user->password = bcrypt('password123');
$user->role = 'admin';
$user->save();

echo "created\n";