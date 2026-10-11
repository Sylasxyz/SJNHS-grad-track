<?php
declare(strict_types=1);

require_once __DIR__ . '/mail_config.php';

function mail_on_railway(): bool
{
    return app_env('RAILWAY_ENVIRONMENT') !== null
        || app_env('RAILWAY_PROJECT_ID') !== null
        || app_env('RAILWAY_SERVICE_NAME') !== null;
}

/** Whether SMTP is configured enough to attempt Gmail (or other) AUTH. */
function mail_smtp_credentials_ready(): bool
{
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_USER, $SMTP_PASS;
    if (empty($SMTP_ENABLED)) {
        return false;
    }
    $pass = preg_replace('/\s+/', '', (string)($SMTP_PASS ?? '')) ?? '';
    return trim((string)($SMTP_HOST ?? '')) !== ''
        && trim((string)($SMTP_USER ?? '')) !== ''
        && $pass !== '';
}

/** Staff-only hint written to logs (never show to public forms). */
function mail_operator_setup_hint(): string
{
    if (mail_on_railway()) {
        return 'Railway web service: set SMTP_PASS (Gmail App Password) and SMTP_ENABLED=1, then redeploy.';
    }
    return 'Local: set SMTP_PASS in includes/mail_config.local.php (gitignored).';
}

/** Safe message for students/public when outbound mail fails. */
function mail_public_delivery_notice(): string
{
    return 'We could not send a confirmation email right now. Your registration was received—please wait for administrator approval. You do not need to register again. Contact the school office if you need assistance.';
}

/**
 * @return array{ok:bool,via:string,error:string}
 */
function send_app_email(string $to, string $subject, string $body, string $from = '', bool $quick = false): array
{
    global $MAIL_FROM_EMAIL, $MAIL_FROM_NAME, $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE,
           $SMTP_USER, $SMTP_PASS, $SMTP_TIMEOUT, $MAIL_ALWAYS_LOG;

    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'via' => 'none', 'error' => 'Invalid recipient email.'];
    }

    $fromEmail = trim($from !== '' ? $from : (string)($MAIL_FROM_EMAIL ?? 'no-reply@sjnsh.local'));
    $fromName = trim((string)($MAIL_FROM_NAME ?? 'Graduate Tracking System'));
    if (!empty($SMTP_USER) && filter_var((string)$SMTP_USER, FILTER_VALIDATE_EMAIL)) {
        // Many SMTP providers require From to match the authenticated account.
        $fromEmail = (string)$SMTP_USER;
    }

    $result = ['ok' => false, 'via' => 'none', 'error' => ''];

    $smtpPass = preg_replace('/\s+/', '', (string)($SMTP_PASS ?? '')) ?? '';
    $smtpReady = !empty($SMTP_ENABLED)
        && trim((string)($SMTP_HOST ?? '')) !== ''
        && trim((string)($SMTP_USER ?? '')) !== ''
        && $smtpPass !== '';

    if ($smtpReady) {
        $host = (string)$SMTP_HOST;
        $timeout = $quick ? min(10, (int)($SMTP_TIMEOUT ?? 30)) : (int)($SMTP_TIMEOUT ?? 30);
        $smtp = ['ok' => false, 'error' => 'SMTP delivery failed.'];
        $lastError = '';

        $plans = smtp_attempt_plans((int)$SMTP_PORT, (string)$SMTP_SECURE);
        if ($quick) {
            $plans = array_slice($plans, 0, 1);
        }

        foreach ($plans as [$port, $secure]) {
            $attempt = smtp_send_email(
                $host,
                $port,
                $secure,
                (string)$SMTP_USER,
                $smtpPass,
                $timeout,
                $fromEmail,
                $fromName,
                $to,
                $subject,
                $body
            );
            if (!empty($attempt['ok'])) {
                $smtp = $attempt;
                break;
            }
            $lastError = (string)($attempt['error'] ?? '');
            // Stop after auth failure — wrong password won't fix on another port
            if (stripos($lastError, 'authentication failed') !== false) {
                $smtp = $attempt;
                break;
            }
        }

        if (!empty($smtp['ok'])) {
            $result = ['ok' => true, 'via' => 'smtp', 'error' => ''];
        } else {
            $errText = $lastError !== '' ? $lastError : (string)($smtp['error'] ?? 'SMTP failed');
            if (mail_on_railway() && smtp_looks_like_network_block($errText)) {
                $errText .= ' Cloud hosts sometimes cannot reach Gmail SMTP; use Brevo/SendGrid SMTP (see DEPLOY-RAILWAY.md) or verify SMTP_PASS is a Gmail App Password.';
            }
            $result = ['ok' => false, 'via' => 'smtp', 'error' => $errText];
        }
    } else {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . format_mail_address($fromEmail, $fromName);
        $headers[] = 'Reply-To: ' . $fromEmail;

        $sent = false;
        try {
            $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            $sent = false;
            $result['error'] = $e->getMessage();
        }

        if ($sent) {
            $result = ['ok' => true, 'via' => 'mail', 'error' => ''];
        } else {
            if ($result['error'] === '') {
                $result['error'] = 'PHP mail() failed or is not configured on this server.';
            }
            $result['via'] = 'mail';
        }
    }

    if (!empty($MAIL_ALWAYS_LOG) || !$result['ok']) {
        mail_write_log($to, $subject, $body, $result);
        if (!$result['ok'] && !mail_smtp_credentials_ready()) {
            mail_write_log('operator', 'SMTP setup', mail_operator_setup_hint(), [
                'ok' => false,
                'via' => 'config',
                'error' => 'Missing SMTP_PASS or SMTP disabled',
            ]);
        }
    }

    return $result;
}

function format_mail_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = trim($name);
    if ($name === '') {
        return $email;
    }
    $safeName = addcslashes($name, '"\\');
    return sprintf('"%s" <%s>', $safeName, $email);
}

/**
 * @param array{ok:bool,via:string,error:string} $result
 */
function mail_write_log(string $to, string $subject, string $body, array $result): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/mail.log';
    $status = $result['ok'] ? 'SENT' : 'FAILED';
    $entry =
        "---- " . date('c') . " ----\n" .
        "STATUS: {$status}\n" .
        "VIA: " . ($result['via'] ?: 'none') . "\n" .
        "ERROR: " . ($result['error'] ?: '-') . "\n" .
        "TO: {$to}\n" .
        "SUBJECT: {$subject}\n\n" .
        $body . "\n\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/** @return list<array{0:int,1:string}> */
function smtp_attempt_plans(int $configuredPort, string $configuredSecure): array
{
    $configuredSecure = strtolower(trim($configuredSecure));
    $plans = [[$configuredPort, $configuredSecure]];
    foreach ([[465, 'ssl'], [587, 'tls']] as $alt) {
        if ($alt[0] !== $configuredPort || $alt[1] !== $configuredSecure) {
            $plans[] = $alt;
        }
    }
    if (mail_on_railway()) {
        usort($plans, static function (array $a, array $b): int {
            $score = static fn (array $p): int => ($p[0] === 465 ? 0 : ($p[0] === 587 ? 1 : 2));
            return $score($a) <=> $score($b);
        });
    }
    return $plans;
}

function smtp_looks_like_network_block(string $error): bool
{
    $error = strtolower($error);
    return str_contains($error, 'connect failed')
        || str_contains($error, 'timed out')
        || str_contains($error, 'connection refused')
        || str_contains($error, 'network is unreachable');
}

function smtp_stream_context()
{
    return stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);
}

/** Quick TCP/TLS check for admin diagnostics (no AUTH). */
function smtp_tcp_probe(string $host, int $port, string $secure, int $timeout = 12): string
{
    $secure = strtolower(trim($secure));
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        max(5, $timeout),
        STREAM_CLIENT_CONNECT,
        smtp_stream_context()
    );
    if (!$fp) {
        return "failed ({$errno}: {$errstr})";
    }
    fclose($fp);
    return 'reachable';
}

/** @return list<string> */
function mail_admin_diagnostics(): array
{
    global $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_ENABLED;

    $lines = [];
    $lines[] = 'Hosting: ' . (mail_on_railway() ? 'Railway' : 'local/other');
    $lines[] = 'SMTP enabled: ' . (!empty($SMTP_ENABLED) ? 'yes' : 'no');
    $lines[] = 'Credentials loaded: ' . (mail_smtp_credentials_ready() ? 'yes' : 'no');
    $passFromEnv = app_env('SMTP_PASS');
    $lines[] = 'SMTP_PASS in environment: ' . ($passFromEnv !== null && trim($passFromEnv) !== '' ? 'yes' : 'no');
    $lines[] = 'OpenSSL extension: ' . (extension_loaded('openssl') ? 'yes' : 'no');
    $host = trim((string)($SMTP_HOST ?? ''));
    if ($host !== '') {
        $lines[] = 'Host ' . $host . ':465 (SSL): ' . smtp_tcp_probe($host, 465, 'ssl');
        $lines[] = 'Host ' . $host . ':587 (plain): ' . smtp_tcp_probe($host, 587, '');
    }
    $lines[] = 'Configured: ' . $host . ':' . (int)($SMTP_PORT ?? 0) . ' (' . (string)($SMTP_SECURE ?? '') . ') user ' . (string)($SMTP_USER ?? '');

    return $lines;
}

/**
 * Minimal SMTP client (AUTH LOGIN) with TLS/SSL support.
 *
 * @return array{ok:bool,error:string}
 */
function smtp_send_email(
    string $host,
    int $port,
    string $secure,
    string $username,
    string $password,
    int $timeout,
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $body
): array {
    $secure = strtolower(trim($secure));
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        max(5, $timeout),
        STREAM_CLIENT_CONNECT,
        smtp_stream_context()
    );
    if (!$fp) {
        return ['ok' => false, 'error' => "SMTP connect failed: {$errstr} ({$errno})"];
    }
    stream_set_timeout($fp, max(5, $timeout));

    $read = static function () use ($fp): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $expect = static function (string $response, array $codes) use (&$fp): ?string {
        foreach ($codes as $code) {
            if (str_starts_with($response, (string)$code)) {
                return null;
            }
        }
        fclose($fp);
        return 'Unexpected SMTP response: ' . trim($response);
    };

    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $greeting = $read();
    if ($err = $expect($greeting, [220])) {
        return ['ok' => false, 'error' => $err];
    }

    $ehloHost = preg_replace('/[^a-zA-Z0-9.-]/', '', (string)(app_env('RAILWAY_PUBLIC_DOMAIN') ?? 'localhost')) ?: 'localhost';
    $write('EHLO ' . $ehloHost);
    $ehlo = $read();
    if ($err = $expect($ehlo, [250])) {
        return ['ok' => false, 'error' => $err];
    }

    if ($secure === 'tls') {
        $write('STARTTLS');
        $tls = $read();
        if ($err = $expect($tls, [220])) {
            return ['ok' => false, 'error' => $err];
        }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Failed to enable TLS with SMTP server (check OpenSSL in PHP).'];
        }
        $write('EHLO ' . $ehloHost);
        $ehlo2 = $read();
        if ($err = $expect($ehlo2, [250])) {
            return ['ok' => false, 'error' => $err];
        }
    }

    $write('AUTH LOGIN');
    $auth = $read();
    if ($err = $expect($auth, [334])) {
        return ['ok' => false, 'error' => $err];
    }
    $write(base64_encode($username));
    $userResp = $read();
    if ($err = $expect($userResp, [334])) {
        return ['ok' => false, 'error' => $err];
    }
    $write(base64_encode($password));
    $passResp = $read();
    if ($err = $expect($passResp, [235])) {
        $detail = trim(preg_replace('/\s+/', ' ', $passResp) ?? '');
        $hint = 'Use a Gmail App Password (not your normal password). Create one: Google Account → Security → 2-Step Verification → App passwords.';
        if ($detail !== '') {
            return ['ok' => false, 'error' => 'SMTP authentication failed. Server: ' . $detail . '. ' . $hint];
        }
        return ['ok' => false, 'error' => 'SMTP authentication failed. ' . $hint];
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    $mailFrom = $read();
    if ($err = $expect($mailFrom, [250])) {
        return ['ok' => false, 'error' => $err];
    }

    $write('RCPT TO:<' . $to . '>');
    $rcpt = $read();
    if ($err = $expect($rcpt, [250, 251])) {
        return ['ok' => false, 'error' => $err];
    }

    $write('DATA');
    $dataResp = $read();
    if ($err = $expect($dataResp, [354])) {
        return ['ok' => false, 'error' => $err];
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . format_mail_address($fromEmail, $fromName),
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: SJNHS-GraduateTracking',
    ];

    // Dot-stuffing for body lines starting with "."
    $safeBody = preg_replace("/\r\n|\r|\n/", "\r\n", $body) ?? $body;
    $safeBody = preg_replace("/^\./m", "..", $safeBody) ?? $safeBody;

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.";
    $write($message);
    $queued = $read();
    if ($err = $expect($queued, [250])) {
        return ['ok' => false, 'error' => $err];
    }

    $write('QUIT');
    fclose($fp);
    return ['ok' => true, 'error' => ''];
}

function app_absolute_url(string $relativePath): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = rtrim(dirname($script), '/');
    if ($base === '\\' || $base === '.') {
        $base = '';
    }
    $path = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    return $scheme . '://' . $host . $base . $path;
}
