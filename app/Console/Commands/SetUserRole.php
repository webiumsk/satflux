<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-role {email} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set user role (free, support, admin, pro, enterprise)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        // Validate role
        $validRoles = ['free', 'support', 'admin', 'pro', 'enterprise'];
        if (!in_array($role, $validRoles)) {
            $this->error("Invalid role. Allowed roles: " . implode(', ', $validRoles));
            return Command::FAILURE;
        }

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        // Update role
        $oldRole = $user->role ?? 'free';
        $user->role = $role;
        $user->save();

        $this->info("✓ User role updated successfully!");
        $this->line("  Email: {$user->email}");
        $this->line("  Old role: {$oldRole}");
        $this->line("  New role: {$role}");

        return Command::SUCCESS;
    }
}

