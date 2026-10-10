<?php
declare(strict_types=1);

/**
 * In-system messaging helpers (staff ↔ students/alumni).
 */

function ensure_user_messages_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS User_Messages (
          Message_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
          Sender_ID INT UNSIGNED NOT NULL,
          Recipient_ID INT UNSIGNED NOT NULL,
          Message_Text TEXT NOT NULL,
          Sent_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          Is_Read TINYINT(1) NOT NULL DEFAULT 0,
          PRIMARY KEY (Message_ID),
          KEY idx_user_messages_sender (Sender_ID),
          KEY idx_user_messages_recipient (Recipient_ID),
          KEY idx_user_messages_sent_at (Sent_At)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ready = true;
}

function message_user_display_name(array $row): string
{
    $first = trim((string)($row['First_Name'] ?? ''));
    $last = trim((string)($row['Last_Name'] ?? ''));
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }
    $username = trim((string)($row['Username'] ?? ''));
    return $username !== '' ? $username : 'User';
}

function message_user_subtitle(array $row): string
{
    $role = (string)($row['Role'] ?? '');
    if ($role === 'Student') {
        $id = trim((string)($row['Student_No'] ?? ''));
        if ($id === '') {
            $id = trim((string)($row['Username'] ?? ''));
        }
        $parts = ['Student'];
        if ($id !== '') {
            $parts[] = $id;
        }
        $strand = trim((string)($row['Course'] ?? ''));
        if ($strand !== '') {
            $parts[] = $strand;
        }
        return implode(' · ', $parts);
    }
    return $role;
}

/**
 * Can $fromRole message a user with $toRole?
 */
function can_roles_message(string $fromRole, string $toRole): bool
{
    if ($fromRole === 'Student') {
        return in_array($toRole, ['Admin', 'Instructor'], true);
    }
    if (in_array($fromRole, ['Admin', 'Instructor'], true)) {
        return $toRole === 'Student';
    }
    return false;
}

function fetch_user_for_messaging(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT ua.User_ID, ua.First_Name, ua.Last_Name, ua.Username, ua.Role, ua.Status,
                    ua.Student_No, ua.Employee_ID, ua.Email, gp.Course
             FROM User_Accounts ua
             LEFT JOIN Graduate_Profiles gp ON gp.User_ID = ua.User_ID
             WHERE ua.User_ID = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        $stmt = $pdo->prepare(
            "SELECT User_ID, First_Name, Last_Name, Username, Role, Status, Student_No, Employee_ID, Email
             FROM User_Accounts WHERE User_ID = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

/**
 * Users the current account is allowed to message.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_messageable_users(PDO $pdo, int $myId, string $myRole, string $search = ''): array
{
    $search = trim($search);
    $params = [];
    $where = ["ua.Status = 'Active'", 'ua.User_ID <> ?'];
    $params[] = $myId;

    if ($myRole === 'Student') {
        $where[] = "ua.Role IN ('Admin', 'Instructor')";
    } else {
        $where[] = "ua.Role = 'Student'";
    }

    $joinProfile = '';
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(ua.First_Name LIKE ? OR ua.Last_Name LIKE ? OR ua.Username LIKE ?
                     OR ua.Student_No LIKE ? OR ua.Email LIKE ?
                     OR CONCAT(ua.First_Name, \' \', ua.Last_Name) LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if ($myRole !== 'Student') {
        $joinProfile = 'LEFT JOIN Graduate_Profiles gp ON gp.User_ID = ua.User_ID';
        $selectCourse = ', gp.Course';
    } else {
        $selectCourse = ', NULL AS Course';
    }

    $sql =
        "SELECT ua.User_ID, ua.First_Name, ua.Last_Name, ua.Username, ua.Role,
                ua.Student_No, ua.Employee_ID, ua.Email{$selectCourse}
         FROM User_Accounts ua
         {$joinProfile}
         WHERE " . implode(' AND ', $where) . "
         ORDER BY ua.Last_Name ASC, ua.First_Name ASC
         LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Conversation list for inbox (one row per partner).
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_message_conversations(PDO $pdo, int $myId): array
{
    $sql =
        "SELECT
            CASE WHEN m.Sender_ID = ? THEN m.Recipient_ID ELSE m.Sender_ID END AS Partner_ID,
            MAX(m.Message_ID) AS Last_Message_ID,
            SUM(CASE WHEN m.Recipient_ID = ? AND m.Is_Read = 0 THEN 1 ELSE 0 END) AS Unread_Count
         FROM User_Messages m
         WHERE m.Sender_ID = ? OR m.Recipient_ID = ?
         GROUP BY Partner_ID
         ORDER BY Last_Message_ID DESC
         LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$myId, $myId, $myId, $myId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }

    $partnerIds = array_map(static fn(array $r): int => (int)$r['Partner_ID'], $rows);
    $lastIds = array_map(static fn(array $r): int => (int)$r['Last_Message_ID'], $rows);

    $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));
    $usersStmt = $pdo->prepare(
        "SELECT ua.User_ID, ua.First_Name, ua.Last_Name, ua.Username, ua.Role, ua.Student_No, ua.Employee_ID,
                gp.Course
         FROM User_Accounts ua
         LEFT JOIN Graduate_Profiles gp ON gp.User_ID = ua.User_ID
         WHERE ua.User_ID IN ($placeholders)"
    );
    $usersStmt->execute($partnerIds);
    $usersById = [];
    foreach ($usersStmt->fetchAll() as $u) {
        $usersById[(int)$u['User_ID']] = $u;
    }

    $msgPlaceholders = implode(',', array_fill(0, count($lastIds), '?'));
    $msgStmt = $pdo->prepare(
        "SELECT Message_ID, Message_Text, Sent_At FROM User_Messages WHERE Message_ID IN ($msgPlaceholders)"
    );
    $msgStmt->execute($lastIds);
    $msgsById = [];
    foreach ($msgStmt->fetchAll() as $m) {
        $msgsById[(int)$m['Message_ID']] = $m;
    }

    $out = [];
    foreach ($rows as $row) {
        $pid = (int)$row['Partner_ID'];
        $lastId = (int)$row['Last_Message_ID'];
        $user = $usersById[$pid] ?? null;
        $msg = $msgsById[$lastId] ?? null;
        if (!$user || !$msg) {
            continue;
        }
        $out[] = [
            'partner_id' => $pid,
            'partner' => $user,
            'display_name' => message_user_display_name($user),
            'subtitle' => message_user_subtitle($user),
            'last_message' => (string)$msg['Message_Text'],
            'last_sent_at' => (string)$msg['Sent_At'],
            'unread_count' => (int)$row['Unread_Count'],
        ];
    }
    return $out;
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetch_message_thread(PDO $pdo, int $myId, int $partnerId, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT m.Message_ID, m.Sender_ID, m.Recipient_ID, m.Message_Text, m.Sent_At, m.Is_Read
         FROM User_Messages m
         WHERE (m.Sender_ID = ? AND m.Recipient_ID = ?)
            OR (m.Sender_ID = ? AND m.Recipient_ID = ?)
         ORDER BY m.Sent_At ASC, m.Message_ID ASC
         LIMIT " . max(1, min(500, $limit))
    );
    $stmt->execute([$myId, $partnerId, $partnerId, $myId]);
    return $stmt->fetchAll();
}

function mark_messages_read(PDO $pdo, int $myId, int $partnerId): void
{
    $stmt = $pdo->prepare(
        'UPDATE User_Messages
         SET Is_Read = 1
         WHERE Recipient_ID = ? AND Sender_ID = ? AND Is_Read = 0'
    );
    $stmt->execute([$myId, $partnerId]);
}

function count_unread_messages(PDO $pdo, int $myId): int
{
    try {
        ensure_user_messages_table($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM User_Messages WHERE Recipient_ID = ? AND Is_Read = 0');
        $stmt->execute([$myId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function send_user_message(PDO $pdo, int $senderId, int $recipientId, string $text): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO User_Messages (Sender_ID, Recipient_ID, Message_Text, Is_Read)
         VALUES (?, ?, ?, 0)'
    );
    $stmt->execute([$senderId, $recipientId, $text]);
}
