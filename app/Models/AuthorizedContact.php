<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizedContact extends Model
{
    use HasFactory;

    protected $table = 'authorized_contacts';

    protected $fillable = [
        'name',
        'phone_e164',
        'role',
        'active',
        'allowed_from',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allowed_from' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'role' => 'operator',
        'active' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->active
            && ($this->allowed_from === null || $this->allowed_from->isPast());
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['admin', 'operator'], true);
    }

    public function isViewer(): bool
    {
        return in_array($this->role, ['admin', 'operator', 'viewer'], true);
    }
}