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
        return 'Railway web service: set BREVO_API_KEY (Brevo → SMTP & API → API Keys), MAIL_FROM_EMAIL (verified sender), SMTP_ENABLED=1, then redeploy. SMTP ports are often blocked; API uses HTTPS.';
    }
    return 'Local: set SMTP_PASS in includes/mail_config.local.php (gitignored), or BREVO_API_KEY for API.';
}

function mail_is_brevo_smtp(): bool
{
    global $SMTP_HOST;
    return str_contains(strtolower(trim((string)($SMTP_HOST ?? ''))), 'brevo');
}

function mail_brevo_api_ready(): bool
{
    global $BREVO_API_KEY;
    if (isset($BREVO_API_KEY) && trim((string)$BREVO_API_KEY) !== '') {
        return true;
    }
    return trim((string)(app_env('BREVO_API_KEY') ?? '')) !== '';
}

function mail_brevo_api_key(): string
{
    global $BREVO_API_KEY;
    if (isset($BREVO_API_KEY) && trim((string)$BREVO_API_KEY) !== '') {
        return trim((string)$BREVO_API_KEY);
    }
    return trim((string)(app_env('BREVO_API_KEY') ?? ''));
}

/** Brevo REST API keys start with xkeysib-; SMTP keys (xsmtpsib-) do not work for the API. */
function mail_brevo_api_key_problem(): string
{
    $key = mail_brevo_api_key();
    if ($key === '') {
        return 'BREVO_API_KEY is not set on Railway.';
    }
    if (str_starts_with($key, 'xsmtpsib-')) {
        return 'BREVO_API_KEY looks like an SMTP key (xsmtpsib-). Create an API key (xkeysib-) under Brevo → SMTP & API → API Keys.';
    }
    if (!str_starts_with($key, 'xkeysib-')) {
        return 'BREVO_API_KEY should start with xkeysib- (Brevo → SMTP & API → API Keys → Generate).';
    }
    return '';
}

function mail_resolve_from_email(string $fromOverride): string
{
    global $MAIL_FROM_EMAIL, $SMTP_USER;

    $default = trim((string)($MAIL_FROM_EMAIL ?? ''));
    $from = trim($fromOverride !== '' ? $fromOverride : $default);
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = $default;
    }

    // Brevo: From must be a verified sender (MAIL_FROM_EMAIL), not @smtp-brevo.com login.
    if (mail_brevo_api_ready() || mail_is_brevo_smtp()) {
        return $from !== '' ? $from : $default;
    }

    if (!empty($SMTP_USER) && filter_var((string)$SMTP_USER, FILTER_VALIDATE_EMAIL)) {
        return (string)$SMTP_USER;
    }

    return $from !== '' ? $from : $default;
}

function mail_outbound_ready(): bool
{
    return mail_smtp_credentials_ready() || mail_brevo_api_ready();
}

/** Safe message for students/public when outbound mail fails. */
function mail_public_delivery_notice(): string
{
    return 'We could not send a confirmation email right now. Your registration was received—please wait for administrator approval. You do not need to register again. Contact the school office if you need assistance.';
}

/**
 * @return array{ok:bool,via:string,error:string}
 */
function brevo_api_send(
    string $to,
    string $subject,
    string $body,
    string $fromEmail,
    string $fromName
): array {
    $keyProblem = mail_brevo_api_key_problem();
    if ($keyProblem !== '') {
        return ['ok' => false, 'via' => 'brevo-api', 'error' => $keyProblem];
    }
    $apiKey = mail_brevo_api_key();
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'via' => 'brevo-api',
            'error' => 'Invalid sender (set MAIL_FROM_EMAIL to your Brevo verified sender).',
        ];
    }

    $payload = json_encode([
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'textContent' => $body,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'via' => 'brevo-api', 'error' => 'Could not encode email payload.'];
    }

    $http = brevo_http_post('https://api.brevo.com/v3/smtp/email', $apiKey, $payload);
    if ($http['error'] !== '') {
        return ['ok' => false, 'via' => 'brevo-api', 'error' => 'Brevo API request failed: ' . $http['error']];
    }

    $code = (int)$http['code'];
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'via' => 'brevo-api', 'error' => ''];
    }

    $detail = brevo_api_error_message((string)$http['body']);
    if ($code === 401) {
        $detail .= ' Check BREVO_API_KEY: use a new API key (xkeysib-) from Brevo → API Keys, not the SMTP key.';
    }
    return ['ok' => false, 'via' => 'brevo-api', 'error' => 'Brevo API HTTP ' . $code . ': ' . $detail];
}

function brevo_api_error_message(string $body): string
{
    $body = trim($body);
    if ($body === '') {
        return 'empty response';
    }
    $json = json_decode($body, true);
    if (is_array($json)) {
        $msg = (string)($json['message'] ?? $json['error'] ?? '');
        if ($msg !== '') {
            return $msg;
        }
    }
    return strlen($body) > 200 ? substr($body, 0, 200) . '…' : $body;
}

/**
 * @return array{code:int,body:string,error:string}
 */
function brevo_http_post(string $url, string $apiKey, string $jsonBody): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['code' => 0, 'body' => '', 'error' => 'curl_init failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return ['code' => 0, 'body' => '', 'error' => $err !== '' ? $err : 'curl_exec failed'];
        }
        return ['code' => $code, 'body' => (string)$body, 'error' => ''];
    }

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "accept: application/json\r\n"
                . 'api-key: ' . $apiKey . "\r\n"
                . "content-type: application/json\r\n"
                . 'Content-Length: ' . strlen($jsonBody) . "\r\n",
            'content'       => $jsonBody,
            'timeout'       => 25,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', (string)$http_response_header[0], $m)) {
        $code = (int)$m[0];
    }
    if ($body === false) {
        return ['code' => 0, 'body' => '', 'error' => 'HTTP request failed (enable curl extension or allow_url_fopen).'];
    }
    return ['code' => $code, 'body' => (string)$body, 'error' => ''];
}

/**
 * @return array{ok:bool,via:string,error:string}
 */
function send_app_email(string $to, string $subject, string $body, string $from = '', bool $quick = false): array
{
    global $MAIL_FROM_NAME, $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE,
           $SMTP_USER, $SMTP_PASS, $SMTP_TIMEOUT, $MAIL_ALWAYS_LOG;

    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'via' => 'none', 'error' => 'Invalid recipient email.'];
    }

    $fromEmail = mail_resolve_from_email($from);
    $fromName = trim((string)($MAIL_FROM_NAME ?? 'Graduate Tracking System'));

    $result = ['ok' => false, 'via' => 'none', 'error' => ''];

    $useApiFirst = mail_brevo_api_ready() && (mail_on_railway() || mail_is_brevo_smtp());
    if ($useApiFirst) {
        $result = brevo_api_send($to, $subject, $body, $fromEmail, $fromName);
        if (!empty($result['ok'])) {
            if (!empty($MAIL_ALWAYS_LOG)) {
                mail_write_log($to, $subject, $body, $result);
            }
            return $result;
        }
        if (mail_on_railway()) {
            if (!empty($MAIL_ALWAYS_LOG) || !$result['ok']) {
                mail_write_log($to, $subject, $body, $result);
            }
            return $result;
        }
    }

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
            if (stripos($lastError, 'authentication failed') !== false) {
                $smtp = $attempt;
                break;
            }
        }

        if (!empty($smtp['ok'])) {
            $result = ['ok' => true, 'via' => 'smtp', 'error' => ''];
        } else {
            $errText = $lastError !== '' ? $lastError : (string)($smtp['error'] ?? 'SMTP failed');
            if (mail_brevo_api_ready() && smtp_looks_like_network_block($errText)) {
                $apiFallback = brevo_api_send($to, $subject, $body, $fromEmail, $fromName);
                if (!empty($apiFallback['ok'])) {
                    $result = $apiFallback;
                } else {
                    $result = [
                        'ok' => false,
                        'via' => 'smtp',
                        'error' => $errText . ' Fallback Brevo API: ' . ($apiFallback['error'] ?? 'failed'),
                    ];
                }
            } else {
                if (mail_on_railway() && smtp_looks_like_network_block($errText)) {
                    $errText .= ' Set BREVO_API_KEY on Railway (HTTPS) — SMTP ports are often blocked.';
                }
                $result = ['ok' => false, 'via' => 'smtp', 'error' => $errText];
            }
        }
    } elseif (mail_brevo_api_ready()) {
        $result = brevo_api_send($to, $subject, $body, $fromEmail, $fromName);
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
        if (!$result['ok'] && !mail_outbound_ready()) {
            mail_write_log('operator', 'Mail setup', mail_operator_setup_hint(), [
                'ok' => false,
                'via' => 'config',
                'error' => 'Missing BREVO_API_KEY or SMTP credentials',
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
    $lines[] = 'Outbound ready (SMTP or Brevo API): ' . (mail_outbound_ready() ? 'yes' : 'no');
    $lines[] = 'BREVO_API_KEY in environment: ' . (mail_brevo_api_ready() ? 'yes' : 'no');
    $brevoKeyHint = mail_brevo_api_key_problem();
    if ($brevoKeyHint !== '') {
        $lines[] = 'BREVO_API_KEY note: ' . $brevoKeyHint;
    }
    $lines[] = 'SMTP credentials loaded: ' . (mail_smtp_credentials_ready() ? 'yes' : 'no');
    $passFromEnv = app_env('SMTP_PASS');
    $lines[] = 'SMTP_PASS in environment: ' . ($passFromEnv !== null && trim($passFromEnv) !== '' ? 'yes' : 'no');
    $lines[] = 'cURL extension: ' . (function_exists('curl_init') ? 'yes' : 'no');
    $lines[] = 'OpenSSL extension: ' . (extension_loaded('openssl') ? 'yes' : 'no');
    $lines[] = 'HTTPS api.brevo.com:443: ' . smtp_tcp_probe('api.brevo.com', 443, 'ssl', 8);
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
