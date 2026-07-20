<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'whats_app_webhook_events';

    protected $fillable = [
        'message_id',
        'from',
        'action',
        'alert_id',
        'contact_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}