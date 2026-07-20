<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOperator(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Operator]);
    }

    public function isViewer(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Operator, UserRole::Viewer]);
    }

    public function hasRole(string|UserRole $role): bool
    {
        $role = $role instanceof UserRole ? $role->value : $role;
        return $this->role?->value === $role;
    }

    public function canManageServers(): bool
    {
        return $this->isAdmin();
    }

    public function canApproveActions(): bool
    {
        return $this->isOperator();
    }

    public function canViewDashboard(): bool
    {
        return $this->isViewer();
    }
}