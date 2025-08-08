<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserMailer
{
    /**
     * Send mail using Laravel's dynamic mailer with user's custom SMTP settings.
     * 
     * @param User $user The user whose SMTP settings to use
     * @param Mailable $mailable The email to send
     * @param string|null $to Override recipient email address
     * @return \Illuminate\Mail\SentMessage|null
     * @throws \Exception
     */
    public static function send(User $user, Mailable $mailable, string $to = null)
    {
        // Get the recipient email
        $recipient = $to ?? $user->getEffectiveNotificationEmail();
        
        // Validate user has SMTP configuration
        if (!$user->hasSmtpConfig()) {
            throw new \Exception('User does not have SMTP configuration');
        }

        // Get user's SMTP configuration
        $smtpConfig = $user->getSmtpConfig();
        
        // Build the mailer configuration
        $config = self::buildMailerConfig($smtpConfig);
        
        try {
            // Configure the mailable with from address
            $mailable->from($config['from']['address'], $config['from']['name']);
            
            // Create a temporary mailer with the user's SMTP settings
            config(['mail.mailers.user_smtp' => $config]);
            
            // Send the email using the temporary mailer
            return Mail::mailer('user_smtp')->to($recipient)->send($mailable);
            
        } catch (\Exception $e) {
            Log::error('Failed to send email via user SMTP', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'smtp_host' => $smtpConfig['host'],
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Build mailer configuration array from user's SMTP settings
     * 
     * @param array $smtpConfig
     * @return array
     */
    private static function buildMailerConfig(array $smtpConfig): array
    {
        // Handle encryption types
        $encryption = self::normalizeEncryption($smtpConfig['encryption'] ?? null);
        
        // Base configuration
        $config = [
            'transport' => 'smtp',
            'host' => $smtpConfig['host'],
            'port' => $smtpConfig['port'],
            'encryption' => $encryption,
            'timeout' => 30,
            'from' => [
                'address' => $smtpConfig['from_address'],
                'name' => $smtpConfig['from_name'],
            ],
        ];
        
        // Add authentication if credentials provided
        if (!empty($smtpConfig['username']) && !empty($smtpConfig['password'])) {
            $config['username'] = $smtpConfig['username'];
            $config['password'] = $smtpConfig['password'];
        }
        
        // Add SSL verification options for local/development servers
        if (self::isLocalHost($smtpConfig['host'])) {
            $config['verify_peer'] = false;
            $config['verify_peer_name'] = false;
            $config['allow_self_signed'] = true;
        }
        
        return $config;
    }
    
    /**
     * Normalize encryption type to Laravel's expected format
     * 
     * @param string|null $encryption
     * @return string|null
     */
    private static function normalizeEncryption(?string $encryption): ?string
    {
        if (empty($encryption) || $encryption === 'none') {
            return null;
        }
        
        if ($encryption === 'starttls') {
            return 'tls';
        }
        
        return $encryption;
    }
    
    /**
     * Check if the host is localhost or local IP
     * 
     * @param string $host
     * @return bool
     */
    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1']);
    }
}