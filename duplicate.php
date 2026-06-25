<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$a = App\Models\Alert::find(1)->replicate();
$a->status = 'pending';
$a->whatsapp_message_id = null;
$a->action_taken = null;
$a->resolved_at = null;
$a->save();
echo "Created new pending alert with ID " . $a->id;
