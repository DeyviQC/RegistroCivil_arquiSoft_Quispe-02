<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $modelo): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $modelo): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $modelo): bool
    {
        return false; // Sección 24: nunca se elimina, solo se desactiva.
    }
}
