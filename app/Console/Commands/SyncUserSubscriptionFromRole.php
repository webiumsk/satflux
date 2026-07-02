<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Console\Command;

class SyncUserSubscriptionFromRole extends Command
{
    protected $signature = 'user:sync-subscription-from-role {email? : User email; omit to sync all pro/enterprise/free merchant roles}';

    protected $description = 'Align subscription rows with users.role after admin role changes';

    public function handle(SubscriptionEntitlementService $subscriptionService): int
    {
        $email = $this->argument('email');

        $users = $email
            ? User::where('email', $email)->get()
            : User::query()
                ->whereIn('role', ['free', 'pro', 'enterprise'])
                ->get();

        if ($users->isEmpty()) {
            $this->error($email ? "User '{$email}' not found." : 'No users matched.');

            return Command::FAILURE;
        }

        foreach ($users as $user) {
            $role = $user->role ?? 'free';
            $subscriptionService->syncSubscriptionForAdminRole($user, $role);
            $this->line("Synced {$user->email} (role: {$role})");
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
