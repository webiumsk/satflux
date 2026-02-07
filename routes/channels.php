<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/** Support/admin only: instant wallet connection notifications (Reverb) */
Broadcast::channel('support.wallet-connections', function ($user) {
    $user->makeVisible('role');
    return $user->role === 'support' || $user->role === 'admin';
});
