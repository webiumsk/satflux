<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users with their roles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::select('id', 'email', 'role', 'email_verified_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');
            return Command::SUCCESS;
        }

        $headers = ['ID', 'Email', 'Role', 'Email Verified', 'Created At'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->email,
                $user->role ?? 'free',
                $user->email_verified_at ? '✓ Yes' : '✗ No',
                $user->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Total users: {$users->count()}");

        return Command::SUCCESS;
    }
}

