<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isStaff() || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /** Admins manage customers; only a super admin touches another staff account. */
    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isStaff() && ! $target->isStaff();
    }

    public function delete(User $user, User $target): bool
    {
        // Nobody deletes themselves, and staff accounts are super-admin only.
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id && ! $target->isStaff();
    }
}
