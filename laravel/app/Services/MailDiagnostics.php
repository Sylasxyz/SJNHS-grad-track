<?php

namespace App\Services;

/**
 * Admin-only mail checks for the Test email page, ported from the diagnostics
 * helpers in includes/mailer.php. Laravel sends only through the Brevo API
 * (see BrevoMailer), so the legacy SMTP checks are left out.
 */
class MailDiagnostics
{
    public function onRailway(): bool
    {
        return (bool) config('services.railway.detected');
    }

    public function apiKey(): string
    {
        return trim((string) config('services.brevo.key'));
    }

    public function outboundReady(): bool
    {
        return $this->apiKey() !== '';
    }

    /** Brevo REST API keys start with xkeysib-; SMTP keys (xsmtpsib-) do not work for the API. */
    public function apiKeyProblem(): string
    {
        $key = $this->apiKey();
        if ($key === '') {
            return 'BREVO_API_KEY is not set on Railway.';
        }
        if (str_starts_with($key, 'xsmtpsib-')) {
            return 'BREVO_API_KEY looks like an SMTP key (xsmtpsib-). Create an API key (xkeysib-) under Brevo → SMTP & API → API Keys.';
        }
        if (! str_starts_with($key, 'xkeysib-')) {
            return 'BREVO_API_KEY should start with xkeysib- (Brevo → SMTP & API → API Keys → Generate).';
        }

        return '';
    }

    /** Staff-only hint; never show it on public forms. */
    public function operatorSetupHint(): string
    {
        if ($this->onRailway()) {
            return 'Railway web service: set BREVO_API_KEY (Brevo → SMTP & API → API Keys), MAIL_FROM_EMAIL (verified sender), then redeploy. SMTP ports are often blocked; API uses HTTPS.';
        }

        return 'Local: set BREVO_API_KEY in laravel/.env.';
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];
        $lines[] = 'Hosting: '.($this->onRailway() ? 'Railway' : 'local/other');
        $lines[] = 'Outbound ready (Brevo API): '.($this->outboundReady() ? 'yes' : 'no');
        $lines[] = 'BREVO_API_KEY in environment: '.($this->outboundReady() ? 'yes' : 'no');
        $keyProblem = $this->apiKeyProblem();
        if ($keyProblem !== '') {
            $lines[] = 'BREVO_API_KEY note: '.$keyProblem;
        }
        $lines[] = 'Sender (MAIL_FROM_EMAIL): '.(string) config('services.brevo.from_email');
        $lines[] = 'cURL extension: '.(function_exists('curl_init') ? 'yes' : 'no');
        $lines[] = 'OpenSSL extension: '.(extension_loaded('openssl') ? 'yes' : 'no');
        $lines[] = 'HTTPS api.brevo.com:443: '.$this->tcpProbe('api.brevo.com', 443, 8);

        return $lines;
    }

    /** Quick TLS connect check (no API call). */
    private function tcpProbe(string $host, int $port, int $timeout): string
    {
        $errno = 0;
        $errstr = '';
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]]);
        $fp = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, max(5, $timeout), STREAM_CLIENT_CONNECT, $context);
        if (! $fp) {
            return "failed ({$errno}: {$errstr})";
        }
        fclose($fp);

        return 'reachable';
    }
}
