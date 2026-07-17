<?php

namespace App\Models;

use App\Enums\ContactRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes; // ← Descomentar si agregas softDeletes

    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'role',
        'active',
        'allowed_since',
    ];

    protected $casts = [
        'role' => ContactRole::class,
        'active' => 'boolean',
        'allowed_since' => 'datetime',
    ];

    protected $attributes = [
        'role' => 'viewer',
        'active' => true,
    ];

    // ============ RELACIONES ============

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_contact');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============ SCOPES ============

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeDba($query)
    {
        return $query->where('role', ContactRole::Dba);
    }

    public function scopeApprover($query)
    {
        return $query->where('role', ContactRole::Approver);
    }

    public function scopeViewer($query)
    {
        return $query->where('role', ContactRole::Viewer);
    }

    public function scopeAllowed($query)
    {
        return $query->where('active', true)
                     ->where(function($q) {
                         $q->whereNull('allowed_since')
                           ->orWhere('allowed_since', '<=', now());
                     });
    }

    // ============ MÉTODOS DE UTILIDAD ============

    public function isDba(): bool
    {
        return $this->role === ContactRole::Dba;
    }

    public function isApprover(): bool
    {
        return $this->role === ContactRole::Approver;
    }

    public function isViewer(): bool
    {
        return $this->role === ContactRole::Viewer;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isAllowed(): bool
    {
        return $this->isActive() && 
               ($this->allowed_since === null || $this->allowed_since <= now());
    }

    public function canApprove(): bool
    {
        return $this->isAllowed() && ($this->isDba() || $this->isApprover());
    }

    public function canView(): bool
    {
        return $this->isAllowed();
    }

    public function hasAccessToServer(Server $server): bool
    {
        return $this->servers()->where('server_id', $server->id)->exists();
    }

    public function getRoleLabel(): string
    {
        return match($this->role) {
            ContactRole::Dba => 'DBA',
            ContactRole::Approver => 'Aprobador',
            ContactRole::Viewer => 'Visualizador',
            default => 'Desconocido',
        };
    }

    public function getPhoneNumberFormatted(): string
    {
        // Formato E.164
        return $this->phone_number;
    }

    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->getRoleLabel() . ')';
    }
}