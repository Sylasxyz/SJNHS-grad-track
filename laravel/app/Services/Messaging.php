<?php

namespace App\Services;

use App\Models\UserAccount;
use App\Models\UserMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * In-system messaging, ported from includes/message_helpers.php.
 * The User_Messages table already exists; it is never created here.
 */
class Messaging
{
    private const USER_COLUMNS = [
        'User_Accounts.User_ID', 'User_Accounts.First_Name', 'User_Accounts.Last_Name', 'User_Accounts.Username',
        'User_Accounts.Role', 'User_Accounts.Status', 'User_Accounts.Student_No', 'User_Accounts.Employee_ID',
        'User_Accounts.Email',
    ];

    /**
     * Students message Admins/Instructors; staff message Students only.
     */
    public static function canRolesMessage(string $fromRole, string $toRole): bool
    {
        if ($fromRole === UserAccount::ROLE_STUDENT) {
            return in_array($toRole, [UserAccount::ROLE_ADMIN, UserAccount::ROLE_INSTRUCTOR], true);
        }
        if (in_array($fromRole, [UserAccount::ROLE_ADMIN, UserAccount::ROLE_INSTRUCTOR], true)) {
            return $toRole === UserAccount::ROLE_STUDENT;
        }

        return false;
    }

    public static function displayName(UserAccount $user): string
    {
        $full = trim(trim((string) $user->First_Name).' '.trim((string) $user->Last_Name));
        if ($full !== '') {
            return $full;
        }
        $username = trim((string) $user->Username);

        return $username !== '' ? $username : 'User';
    }

    public static function subtitle(UserAccount $user): string
    {
        $role = (string) $user->Role;
        if ($role !== UserAccount::ROLE_STUDENT) {
            return $role;
        }

        $id = trim((string) $user->Student_No);
        if ($id === '') {
            $id = trim((string) $user->Username);
        }
        $parts = ['Student'];
        if ($id !== '') {
            $parts[] = $id;
        }
        $strand = trim((string) (array_key_exists('Course', $user->getAttributes()) ? $user->Course : $user->profile?->Course));
        if ($strand !== '') {
            $parts[] = $strand;
        }

        return implode(' · ', $parts);
    }

    /**
     * Active users the given account may message (fetch_messageable_users).
     *
     * @return Collection<int, UserAccount>
     */
    public function messageableUsers(UserAccount $me, string $search = ''): Collection
    {
        $search = trim($search);

        return $this->usersWithCourse()
            ->where('User_Accounts.Status', UserAccount::STATUS_ACTIVE)
            ->where('User_Accounts.User_ID', '<>', $me->User_ID)
            ->when(
                $me->isStudent(),
                fn (Builder $q) => $q->whereIn('User_Accounts.Role', [UserAccount::ROLE_ADMIN, UserAccount::ROLE_INSTRUCTOR]),
                fn (Builder $q) => $q->where('User_Accounts.Role', UserAccount::ROLE_STUDENT),
            )
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('User_Accounts.First_Name', 'like', $like)
                        ->orWhere('User_Accounts.Last_Name', 'like', $like)
                        ->orWhere('User_Accounts.Username', 'like', $like)
                        ->orWhere('User_Accounts.Student_No', 'like', $like)
                        ->orWhere('User_Accounts.Email', 'like', $like)
                        ->orWhereRaw("CONCAT(User_Accounts.First_Name, ' ', User_Accounts.Last_Name) LIKE ?", [$like]);
                });
            })
            ->orderBy('User_Accounts.Last_Name')
            ->orderBy('User_Accounts.First_Name')
            ->limit(200)
            ->get();
    }

    /**
     * Inbox: one row per conversation partner, newest first (fetch_message_conversations).
     *
     * @return list<array{partner_id: int, partner: UserAccount, display_name: string, subtitle: string, last_message: string, last_sent_at: string, unread_count: int}>
     */
    public function conversations(UserAccount $me): array
    {
        $myId = (int) $me->User_ID;
        $rows = DB::select(
            'SELECT
                CASE WHEN m.Sender_ID = ? THEN m.Recipient_ID ELSE m.Sender_ID END AS Partner_ID,
                MAX(m.Message_ID) AS Last_Message_ID,
                SUM(CASE WHEN m.Recipient_ID = ? AND m.Is_Read = 0 THEN 1 ELSE 0 END) AS Unread_Count
             FROM User_Messages m
             WHERE m.Sender_ID = ? OR m.Recipient_ID = ?
             GROUP BY Partner_ID
             ORDER BY Last_Message_ID DESC
             LIMIT 100',
            [$myId, $myId, $myId, $myId]
        );
        if (! $rows) {
            return [];
        }

        $users = $this->usersWithCourse()
            ->whereIn('User_Accounts.User_ID', array_map(fn ($r) => (int) $r->Partner_ID, $rows))
            ->get()
            ->keyBy('User_ID');
        $messages = UserMessage::query()
            ->whereIn('Message_ID', array_map(fn ($r) => (int) $r->Last_Message_ID, $rows))
            ->get(['Message_ID', 'Message_Text', 'Sent_At'])
            ->keyBy('Message_ID');

        $out = [];
        foreach ($rows as $row) {
            $user = $users->get((int) $row->Partner_ID);
            $message = $messages->get((int) $row->Last_Message_ID);
            if (! $user || ! $message) {
                continue;
            }
            $out[] = [
                'partner_id' => (int) $row->Partner_ID,
                'partner' => $user,
                'display_name' => self::displayName($user),
                'subtitle' => self::subtitle($user),
                'last_message' => (string) $message->Message_Text,
                'last_sent_at' => (string) $message->getRawOriginal('Sent_At'),
                'unread_count' => (int) $row->Unread_Count,
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, UserMessage>
     */
    public function thread(UserAccount $me, UserAccount $partner, int $limit = 200): Collection
    {
        return UserMessage::query()
            ->where(fn (Builder $q) => $q->where('Sender_ID', $me->User_ID)->where('Recipient_ID', $partner->User_ID))
            ->orWhere(fn (Builder $q) => $q->where('Sender_ID', $partner->User_ID)->where('Recipient_ID', $me->User_ID))
            ->orderBy('Sent_At')
            ->orderBy('Message_ID')
            ->limit(max(1, min(500, $limit)))
            ->get();
    }

    public function markRead(UserAccount $me, UserAccount $partner): void
    {
        UserMessage::query()
            ->where('Recipient_ID', $me->User_ID)
            ->where('Sender_ID', $partner->User_ID)
            ->where('Is_Read', 0)
            ->update(['Is_Read' => 1]);
    }

    public function unreadCount(UserAccount $me): int
    {
        return UserMessage::query()
            ->where('Recipient_ID', $me->User_ID)
            ->where('Is_Read', 0)
            ->count();
    }

    public function send(UserAccount $sender, UserAccount $recipient, string $text): UserMessage
    {
        $message = new UserMessage([
            'Sender_ID' => $sender->User_ID,
            'Recipient_ID' => $recipient->User_ID,
            'Message_Text' => $text,
            'Is_Read' => false,
        ]);
        // Sent_At keeps the database default, as in the legacy insert.
        $message->timestamps = false;
        $message->save();

        return $message;
    }

    private function usersWithCourse(): Builder
    {
        return UserAccount::query()
            ->leftJoin('Graduate_Profiles', 'Graduate_Profiles.User_ID', '=', 'User_Accounts.User_ID')
            ->select([...self::USER_COLUMNS, 'Graduate_Profiles.Course']);
    }
}
