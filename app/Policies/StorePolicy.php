<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Admins and support bypass all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isSupport()) {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user can view the store listing (filtered by ownership in query).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * User can view their own store.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    /**
     * Any authenticated, non-guest user can create a store (plan limits enforced elsewhere).
     */
    public function create(User $user): bool
    {
        return !$user->is_guest;
    }

    /**
     * User can update their own store.
     */
    public function update(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    /**
     * User can delete their own store.
     */
    public function delete(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }
}
