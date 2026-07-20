<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isViewer();
    }

    public function view(User $user, Server $server): bool
    {
        return $user->isViewer();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Server $server): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Server $server): bool
    {
        return $user->isAdmin();
    }

    public function testConnection(User $user, Server $server): bool
    {
        return $user->isAdmin();
    }
}