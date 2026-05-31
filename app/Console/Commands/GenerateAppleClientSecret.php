<?php

namespace App\Console\Commands;

use Firebase\JWT\JWT;
use Illuminate\Console\Command;

class GenerateAppleClientSecret extends Command
{
    /**
     * Apple "client secrets" are short-lived ES256 JWTs (max 6 months), not static
     * strings. This signs one from your downloaded .p8 key + the three IDs so it can
     * go into APPLE_CLIENT_SECRET. The .p8 never leaves your machine.
     */
    protected $signature = 'apple:client-secret
        {--key= : Path to your Apple .p8 private key file}
        {--team-id= : Apple Team ID (10 chars, e.g. 94RZN3XXCD)}
        {--key-id= : Key ID of the .p8 (10 chars, from the Keys tab)}
        {--client-id= : Services ID, e.g. com.wonderlively.everythingimmersive.web}
        {--months=6 : Lifetime in months (Apple caps this at 6)}';

    protected $description = 'Generate the APPLE_CLIENT_SECRET JWT for Sign in with Apple';

    public function handle(): int
    {
        $keyPath = $this->option('key') ?: $this->ask('Path to your .p8 private key file');
        $teamId = $this->option('team-id') ?: $this->ask('Apple Team ID');
        $keyId = $this->option('key-id') ?: $this->ask('Key ID');
        $clientId = $this->option('client-id') ?: $this->ask('Services ID (client_id)');
        $months = (int) $this->option('months');

        if (! is_file($keyPath)) {
            $this->error("Private key file not found: {$keyPath}");

            return self::FAILURE;
        }

        if ($months < 1 || $months > 6) {
            $this->error('Apple client secrets last at most 6 months — use 1–6.');

            return self::FAILURE;
        }

        $now = time();
        $exp = $now + ($months * 30 * 24 * 60 * 60); // ~months, safely under Apple's 6-month cap

        $payload = [
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $exp,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ];

        try {
            $jwt = JWT::encode($payload, file_get_contents($keyPath), 'ES256', $keyId);
        } catch (\Throwable $e) {
            $this->error('Failed to sign the JWT: '.$e->getMessage());
            $this->line('Check that the file is your Apple .p8 (an EC P-256 private key) and the IDs are correct.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('APPLE_CLIENT_SECRET (valid until '.date('Y-m-d', $exp).'):');
        $this->newLine();
        $this->line($jwt);
        $this->newLine();
        $this->comment('Add to your server .env as APPLE_CLIENT_SECRET=<above>, then: php artisan config:cache');
        $this->warn('Apple secrets expire — regenerate before '.date('Y-m-d', $exp).'.');

        return self::SUCCESS;
    }
}
