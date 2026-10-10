<?php
declare(strict_types=1);

/** Failed attempts within this window trigger lockout and admin security alerts. */
const LOGIN_FAILURE_WINDOW_MINUTES = 30;

/** Failed attempts before temporary lockout. */
const LOGIN_MAX_FAILURES = 5;

/** Lockout duration after too many failures. */
const LOGIN_LOCKOUT_MINUTES = 5;

/** Minimum failed attempts (per username + IP) to show in Security Alerts. */
const LOGIN_ALERT_THRESHOLD = 5;

function login_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        $val = (string)($_SERVER[$key] ?? '');
        if ($val === '') {
            continue;
        }
        $ip = trim(explode(',', $val)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

function ensure_login_attempts_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS Login_Attempts (
                Attempt_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
                Username VARCHAR(50) NOT NULL,
                IP_Address VARCHAR(45) NOT NULL,
                Was_Successful TINYINT(1) NOT NULL DEFAULT 0,
                Attempted_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (Attempt_ID),
                KEY idx_login_attempts_user_time (Username, Attempted_At),
                KEY idx_login_attempts_ip_time (IP_Address, Attempted_At),
                KEY idx_login_attempts_failed_time (Was_Successful, Attempted_At)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    } catch (Throwable $e) {
        // Table may already exist or DB user lacks CREATE privilege.
    }
}

function count_recent_login_failures(PDO $pdo, string $username, int $windowMinutes = LOGIN_FAILURE_WINDOW_MINUTES): int
{
    try {
        ensure_login_attempts_table($pdo);
        if ($username === '') {
            return 0;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Login_Attempts
             WHERE Username = ?
               AND Was_Successful = 0
               AND Attempted_At >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$username, $windowMinutes]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function record_login_attempt(PDO $pdo, string $username, string $ip, bool $success): void
{
    try {
        ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO Login_Attempts (Username, IP_Address, Was_Successful) VALUES (?, ?, ?)'
        );
        $stmt->execute([substr($username, 0, 50), substr($ip, 0, 45), $success ? 1 : 0]);
    } catch (Throwable $e) {
        // Silently ignore if table not ready yet
    }
}

/**
 * Rows for admin Security Alerts (grouped by username + IP).
 *
 * @return list<array<string, mixed>>
 */
function fetch_login_security_alerts(PDO $pdo, int $limit = 50): array
{
    try {
        ensure_login_attempts_table($pdo);
        $stmt = $pdo->query(
            "SELECT Username,
                    IP_Address,
                    COUNT(*) AS Attempt_Count,
                    MAX(Attempted_At) AS Last_Attempt
             FROM Login_Attempts
             WHERE Was_Successful = 0
               AND Attempted_At >= DATE_SUB(NOW(), INTERVAL " . (int)LOGIN_FAILURE_WINDOW_MINUTES . " MINUTE)
             GROUP BY Username, IP_Address
             HAVING Attempt_Count >= " . (int)LOGIN_ALERT_THRESHOLD . "
             ORDER BY Last_Attempt DESC
             LIMIT " . max(1, min(200, $limit))
        );
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

function clear_login_attempts_for(PDO $pdo, string $username, string $ip): int
{
    try {
        ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare(
            "DELETE FROM Login_Attempts
             WHERE Username = ?
               AND IP_Address = ?
               AND Was_Successful = 0
               AND Attempted_At >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([substr($username, 0, 50), substr($ip, 0, 45), LOGIN_FAILURE_WINDOW_MINUTES]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Remove all recent failed attempts for an account (any IP) so alerts disappear for that login ID. */
function clear_login_attempts_for_username(PDO $pdo, string $username): int
{
    try {
        ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare(
            "DELETE FROM Login_Attempts
             WHERE Username = ?
               AND Was_Successful = 0
               AND Attempted_At >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([substr($username, 0, 50), LOGIN_FAILURE_WINDOW_MINUTES]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}
