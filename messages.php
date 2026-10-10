<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/message_helpers.php';

require_login();
$user = current_user();
$myId = (int)($user['User_ID'] ?? 0);
$myRole = (string)($user['Role'] ?? '');

if ($myRole === 'Principal') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Messages';
$errors = [];
$success = '';
$partnerId = (int)($_GET['with'] ?? $_POST['recipient_id'] ?? 0);
$composeMode = isset($_GET['compose']) || (isset($_POST['action']) && $_POST['action'] === 'start');
$search = trim((string)($_GET['q'] ?? ''));

try {
    ensure_user_messages_table($pdo);
} catch (Throwable $e) {
    $errors[] = 'Messaging is unavailable right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    csrf_validate();
    $action = (string)($_POST['action'] ?? 'send');
    $text = trim((string)($_POST['message_text'] ?? ''));
    $recipientId = (int)($_POST['recipient_id'] ?? 0);

    if ($text === '' || strlen($text) < 1) {
        $errors[] = 'Please enter a message.';
    } elseif (strlen($text) > 2000) {
        $errors[] = 'Message is too long. Maximum 2000 characters.';
    }

    $recipient = fetch_user_for_messaging($pdo, $recipientId);
    if (!$recipient || (string)($recipient['Status'] ?? '') !== 'Active') {
        $errors[] = 'Recipient not found or inactive.';
    } elseif ($recipientId === $myId) {
        $errors[] = 'You cannot message yourself.';
    } elseif (!can_roles_message($myRole, (string)($recipient['Role'] ?? ''))) {
        $errors[] = $myRole === 'Student'
            ? 'Students can only message instructors or admins.'
            : 'Staff can only message students and alumni.';
    }

    if (!$errors) {
        try {
            send_user_message($pdo, $myId, $recipientId, $text);
            $partnerId = $recipientId;
            $composeMode = false;
            $success = 'Message sent.';
            header('Location: messages.php?with=' . $partnerId . '&sent=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Could not send message. Please try again.';
        }
    } else {
        $partnerId = $recipientId;
        $composeMode = true;
    }
}

if (isset($_GET['sent'])) {
    $success = 'Message sent.';
}

$conversations = [];
$thread = [];
$partner = null;
$messageable = [];
$unreadTotal = 0;

if (!$errors || $success) {
    try {
        $conversations = fetch_message_conversations($pdo, $myId);
        $unreadTotal = count_unread_messages($pdo, $myId);
        $messageable = fetch_messageable_users($pdo, $myId, $myRole, $search);

        if ($partnerId > 0) {
            $partner = fetch_user_for_messaging($pdo, $partnerId);
            if ($partner && (string)($partner['Status'] ?? '') === 'Active'
                && can_roles_message($myRole, (string)($partner['Role'] ?? ''))) {
                mark_messages_read($pdo, $myId, $partnerId);
                $thread = fetch_message_thread($pdo, $myId, $partnerId);
                $conversations = fetch_message_conversations($pdo, $myId);
                $unreadTotal = count_unread_messages($pdo, $myId);
            } else {
                $partner = null;
                $partnerId = 0;
                if (!$composeMode) {
                    $errors[] = 'You cannot open that conversation.';
                }
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Unable to load messages.';
    }
}

$composeLabel = $myRole === 'Student' ? 'Message instructor or admin' : 'Message student or alumni';

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1 class="h4 mb-1">Messages</h1>
          <div class="text-muted small">
            <?php if ($myRole === 'Student'): ?>
              Contact instructors or admins inside the system.
            <?php else: ?>
              Message students and alumni inside the system.
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-primary" href="messages.php?compose=1">
            <i class="bi bi-pencil-square me-1"></i>New message
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Back</a>
        </div>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="messages-layout">
      <aside class="app-card messages-sidebar">
        <div class="messages-sidebar-head">
          <strong>Inbox</strong>
          <?php if ($unreadTotal > 0): ?>
            <span class="badge text-bg-danger"><?= (int)$unreadTotal ?></span>
          <?php endif; ?>
        </div>
        <?php if (!$conversations): ?>
          <div class="text-muted small p-3">No conversations yet.</div>
        <?php else: ?>
          <div class="messages-conv-list">
            <?php foreach ($conversations as $c): ?>
              <a class="messages-conv-item <?= $partnerId === (int)$c['partner_id'] ? 'is-active' : '' ?>"
                 href="messages.php?with=<?= (int)$c['partner_id'] ?>">
                <div class="d-flex justify-content-between gap-2">
                  <div class="messages-conv-name"><?= htmlspecialchars((string)$c['display_name']) ?></div>
                  <?php if ((int)$c['unread_count'] > 0): ?>
                    <span class="badge text-bg-danger"><?= (int)$c['unread_count'] ?></span>
                  <?php endif; ?>
                </div>
                <div class="messages-conv-sub"><?= htmlspecialchars((string)$c['subtitle']) ?></div>
                <div class="messages-conv-preview"><?= htmlspecialchars(strlen((string)$c['last_message']) > 80 ? substr((string)$c['last_message'], 0, 77) . '…' : (string)$c['last_message']) ?></div>
                <div class="messages-conv-time"><?= htmlspecialchars((string)$c['last_sent_at']) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </aside>

      <section class="app-card messages-main">
        <?php if ($composeMode && !$partner): ?>
          <div class="messages-main-head">
            <h2 class="h6 mb-0"><?= htmlspecialchars($composeLabel) ?></h2>
          </div>
          <div class="p-3">
            <form method="get" class="mb-3">
              <input type="hidden" name="compose" value="1">
              <div class="input-group input-group-sm">
                <input class="form-control" type="search" name="q" placeholder="Search by name or Student ID"
                       value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit">Search</button>
              </div>
            </form>

            <?php if (!$messageable): ?>
              <div class="alert alert-secondary mb-0">No matching recipients found.</div>
            <?php else: ?>
              <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="start">
                <div>
                  <label class="form-label" for="recipient_id">Recipient</label>
                  <select class="form-select" id="recipient_id" name="recipient_id" required>
                    <option value="">Select recipient</option>
                    <?php foreach ($messageable as $r): ?>
                      <option value="<?= (int)$r['User_ID'] ?>">
                        <?= htmlspecialchars(message_user_display_name($r) . ' — ' . message_user_subtitle($r)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label" for="message_text">Message</label>
                  <textarea class="form-control" id="message_text" name="message_text" rows="5" required maxlength="2000"
                    placeholder="Write your message…"><?= htmlspecialchars((string)($_POST['message_text'] ?? '')) ?></textarea>
                  <div class="form-text">Maximum 2000 characters.</div>
                </div>
                <div>
                  <button class="btn btn-primary" type="submit">Send message</button>
                  <a class="btn btn-link" href="messages.php">Cancel</a>
                </div>
              </form>
            <?php endif; ?>
          </div>

        <?php elseif ($partner): ?>
          <div class="messages-main-head">
            <div>
              <h2 class="h6 mb-0"><?= htmlspecialchars(message_user_display_name($partner)) ?></h2>
              <div class="text-muted small"><?= htmlspecialchars(message_user_subtitle($partner)) ?></div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="messages.php?compose=1">New</a>
          </div>

          <div class="messages-thread" id="messagesThread">
            <?php if (!$thread): ?>
              <div class="text-muted small text-center py-4">No messages yet. Say hello below.</div>
            <?php else: ?>
              <?php foreach ($thread as $m): ?>
                <?php $mine = ((int)$m['Sender_ID'] === $myId); ?>
                <div class="messages-bubble <?= $mine ? 'is-mine' : 'is-theirs' ?>">
                  <div class="messages-bubble-text"><?= nl2br(htmlspecialchars((string)$m['Message_Text'])) ?></div>
                  <div class="messages-bubble-meta"><?= htmlspecialchars((string)$m['Sent_At']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <form method="post" class="messages-compose">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="recipient_id" value="<?= (int)$partnerId ?>">
            <textarea class="form-control" name="message_text" rows="2" required maxlength="2000"
              placeholder="Type a reply…"></textarea>
            <button class="btn btn-primary" type="submit">Send</button>
          </form>

        <?php else: ?>
          <div class="messages-empty">
            <i class="bi bi-chat-dots"></i>
            <h2 class="h6 mb-1">Select a conversation</h2>
            <p class="text-muted small mb-3">Or start a new message to <?= $myRole === 'Student' ? 'an instructor/admin' : 'a student or alumni' ?>.</p>
            <a class="btn btn-primary btn-sm" href="messages.php?compose=1">New message</a>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<script>
  const thread = document.getElementById('messagesThread');
  if (thread) thread.scrollTop = thread.scrollHeight;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
