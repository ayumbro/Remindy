<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class SmtpValidationService
{
    /**
     * Validate SMTP credentials by attempting to connect to the server.
     */
    public function validateCredentials(array $config): array
    {
        $result = [
            'valid' => false,
            'message' => '',
            'details' => [],
        ];

        try {
            // Validate required fields
            $requiredFields = ['host', 'port', 'username', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($config[$field])) {
                    $result['message'] = "Missing required field: {$field}";

                    return $result;
                }
            }

            // Validate port is numeric and in valid range
            $port = (int) $config['port'];
            if ($port < 1 || $port > 65535) {
                $result['message'] = 'Port must be between 1 and 65535';

                return $result;
            }

            // Validate encryption type
            $validEncryptions = ['tls', 'ssl', '', 'none'];
            if (isset($config['encryption']) && ! in_array($config['encryption'], $validEncryptions)) {
                $result['message'] = 'Encryption must be TLS, SSL, or none';

                return $result;
            }

            // Validate email addresses
            if (isset($config['from_address']) && ! filter_var($config['from_address'], FILTER_VALIDATE_EMAIL)) {
                $result['message'] = 'Invalid from email address';

                return $result;
            }

            // Test SMTP connection
            $connectionResult = $this->testSmtpConnection($config);

            if ($connectionResult['success']) {
                $result['valid'] = true;
                $result['message'] = 'SMTP configuration is valid';
                $result['details'] = $connectionResult['details'];
            } else {
                $result['message'] = $connectionResult['error'];
                $result['details'] = $connectionResult['details'];
            }

        } catch (Exception $e) {
            $result['message'] = 'Validation failed: '.$e->getMessage();
            Log::error('SMTP validation error', [
                'error' => $e->getMessage(),
                'config' => array_merge($config, ['password' => '[REDACTED]']),
            ]);
        }

        return $result;
    }

    /**
     * Test SMTP connection without sending an email.
     */
    private function testSmtpConnection(array $config): array
    {
        $result = [
            'success' => false,
            'error' => '',
            'details' => [],
        ];

        try {
            // Create socket connection
            $host = $config['host'];
            $port = (int) $config['port'];
            $encryption = $config['encryption'] ?? '';

            // Determine connection type
            if ($encryption === 'ssl') {
                $host = "ssl://{$host}";
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = stream_socket_client(
                "{$host}:{$port}",
                $errno,
                $errstr,
                10, // 10 second timeout
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $socket) {
                $result['error'] = "Cannot connect to SMTP server: {$errstr} ({$errno})";

                return $result;
            }

            // Read initial response
            $response = fgets($socket);
            $result['details'][] = 'Server: '.trim($response);

            if (! str_starts_with($response, '220')) {
                $result['error'] = 'SMTP server not ready: '.trim($response);
                fclose($socket);

                return $result;
            }

            // Send EHLO command
            fwrite($socket, 'EHLO '.gethostname()."\r\n");
            $response = fgets($socket);
            $result['details'][] = 'EHLO: '.trim($response);

            if (! str_starts_with($response, '250')) {
                $result['error'] = 'EHLO failed: '.trim($response);
                fclose($socket);

                return $result;
            }

            // Start TLS if required
            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $response = fgets($socket);
                $result['details'][] = 'STARTTLS: '.trim($response);

                if (! str_starts_with($response, '220')) {
                    $result['error'] = 'STARTTLS failed: '.trim($response);
                    fclose($socket);

                    return $result;
                }

                // Enable crypto
                if (! stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $result['error'] = 'Failed to enable TLS encryption';
                    fclose($socket);

                    return $result;
                }

                // Send EHLO again after TLS
                fwrite($socket, 'EHLO '.gethostname()."\r\n");
                $response = fgets($socket);
                $result['details'][] = 'EHLO (after TLS): '.trim($response);
            }

            // Test authentication
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket);
            $result['details'][] = 'AUTH LOGIN: '.trim($response);

            if (! str_starts_with($response, '334')) {
                $result['error'] = 'AUTH LOGIN not supported: '.trim($response);
                fclose($socket);

                return $result;
            }

            // Send username
            fwrite($socket, base64_encode($config['username'])."\r\n");
            $response = fgets($socket);
            $result['details'][] = 'Username: '.trim($response);

            if (! str_starts_with($response, '334')) {
                $result['error'] = 'Username rejected: '.trim($response);
                fclose($socket);

                return $result;
            }

            // Send password
            fwrite($socket, base64_encode($config['password'])."\r\n");
            $response = fgets($socket);
            $result['details'][] = 'Password: '.trim($response);

            if (! str_starts_with($response, '235')) {
                $result['error'] = 'Authentication failed: '.trim($response);
                fclose($socket);

                return $result;
            }

            // Send QUIT
            fwrite($socket, "QUIT\r\n");
            $response = fgets($socket);
            $result['details'][] = 'QUIT: '.trim($response);

            fclose($socket);

            $result['success'] = true;
            $result['details'][] = 'Connection test successful';

        } catch (Exception $e) {
            $result['error'] = 'Connection test failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Get common SMTP configurations for popular providers.
     */
    public function getCommonConfigurations(): array
    {
        return [
            'gmail' => [
                'name' => 'Gmail',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use App Password instead of regular password',
            ],
            'outlook' => [
                'name' => 'Outlook/Hotmail',
                'host' => 'smtp-mail.outlook.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use your regular email and password',
            ],
            'yahoo' => [
                'name' => 'Yahoo Mail',
                'host' => 'smtp.mail.yahoo.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use App Password instead of regular password',
            ],
            'custom' => [
                'name' => 'Custom SMTP Server',
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Contact your email provider for settings',
            ],
        ];
    }
}
