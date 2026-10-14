<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends plain-text email through the Brevo transactional HTTPS API
 * (SMTP ports are blocked on Railway). Mirrors includes/mailer.php.
 */
class BrevoMailer
{
    /**
     * @return array{ok: bool, error: string}
     */
    public function send(string $to, string $subject, string $body): array
    {
        $result = $this->deliver(trim($to), $subject, $body);

        if (config('services.brevo.always_log') || ! $result['ok']) {
            $this->log($to, $subject, $body, $result);
        }

        return $result;
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private function deliver(string $to, string $subject, string $body): array
    {
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }

        $key = trim((string) config('services.brevo.key'));
        if ($key === '') {
            return ['ok' => false, 'error' => 'BREVO_API_KEY is not set.'];
        }
        if (str_starts_with($key, 'xsmtpsib-')) {
            return ['ok' => false, 'error' => 'BREVO_API_KEY looks like an SMTP key (xsmtpsib-). Use an API key (xkeysib-).'];
        }

        $fromEmail = (string) config('services.brevo.from_email');
        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid sender (set MAIL_FROM_EMAIL to your Brevo verified sender).'];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['api-key' => $key])
                ->connectTimeout(15)
                ->timeout(25)
                ->post(config('services.brevo.endpoint'), [
                    'sender' => ['name' => (string) config('services.brevo.from_name'), 'email' => $fromEmail],
                    'to' => [['email' => $to]],
                    'subject' => $subject,
                    'textContent' => $body,
                ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Brevo API request failed: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'error' => ''];
        }

        $detail = (string) ($response->json('message') ?? $response->json('error') ?? '');
        if ($detail === '') {
            $detail = mb_strimwidth(trim($response->body()), 0, 200, '…') ?: 'empty response';
        }

        return ['ok' => false, 'error' => 'Brevo API HTTP '.$response->status().': '.$detail];
    }

    /**
     * @param  array{ok: bool, error: string}  $result
     */
    private function log(string $to, string $subject, string $body, array $result): void
    {
        $entry = '---- '.now()->toIso8601String()." ----\n"
            .'STATUS: '.($result['ok'] ? 'SENT' : 'FAILED')."\n"
            ."VIA: brevo-api\n"
            .'ERROR: '.($result['error'] ?: '-')."\n"
            ."TO: {$to}\n"
            ."SUBJECT: {$subject}\n\n"
            .$body."\n\n";

        @file_put_contents(storage_path('logs/mail.log'), $entry, FILE_APPEND);
    }
}
