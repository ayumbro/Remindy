<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixSmtpEncryption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smtp:fix-encryption 
                            {--user= : Fix encryption for a specific user ID}
                            {--password= : Set a new SMTP password for the user}
                            {--list : List users with SMTP encryption issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix SMTP password encryption issues caused by APP_KEY changes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('list')) {
            $this->listUsersWithEncryptionIssues();
            return;
        }

        $userId = $this->option('user');
        $newPassword = $this->option('password');

        if (!$userId) {
            $this->error('Please specify a user ID with --user=ID');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Checking SMTP configuration for user: {$user->email}");

        // Check if user has basic SMTP config
        if (empty($user->smtp_host) || empty($user->smtp_port)) {
            $this->warn('User does not have basic SMTP configuration (host/port)');
            return 1;
        }

        // Check if password can be decrypted
        try {
            $currentPassword = $user->smtp_password;
            $this->info('✓ SMTP password can be decrypted successfully');
            
            if (!$newPassword) {
                $this->info('No encryption issues found. Use --password to set a new password if needed.');
                return 0;
            }
        } catch (\Exception $e) {
            $this->error("✗ SMTP password decryption failed: {$e->getMessage()}");
            
            if (!$newPassword) {
                $this->warn('Use --password=YOUR_SMTP_PASSWORD to fix this issue');
                return 1;
            }
        }

        if ($newPassword) {
            $this->info('Setting new SMTP password...');
            
            try {
                $user->update(['smtp_password' => $newPassword]);
                $this->info('✓ SMTP password updated successfully');
                
                // Verify the password can be decrypted
                $decryptedPassword = $user->fresh()->smtp_password;
                $this->info('✓ Password decryption verified');
                
                Log::info('SMTP password encryption fixed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                
            } catch (\Exception $e) {
                $this->error("Failed to update SMTP password: {$e->getMessage()}");
                return 1;
            }
        }

        return 0;
    }

    /**
     * List users with SMTP encryption issues.
     */
    private function listUsersWithEncryptionIssues()
    {
        $this->info('Checking all users for SMTP encryption issues...');
        
        $users = User::whereNotNull('smtp_password')->get();
        $issueCount = 0;
        
        foreach ($users as $user) {
            try {
                $password = $user->smtp_password;
                $this->line("✓ User {$user->id} ({$user->email}): OK");
            } catch (\Exception $e) {
                $this->error("✗ User {$user->id} ({$user->email}): {$e->getMessage()}");
                $issueCount++;
            }
        }
        
        if ($issueCount === 0) {
            $this->info('No SMTP encryption issues found.');
        } else {
            $this->warn("Found {$issueCount} user(s) with SMTP encryption issues.");
            $this->info('Use: php artisan smtp:fix-encryption --user=ID --password=PASSWORD to fix');
        }
    }
}
