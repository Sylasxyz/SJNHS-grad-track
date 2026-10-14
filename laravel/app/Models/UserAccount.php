<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserAccount extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN = 'Admin';
    public const ROLE_INSTRUCTOR = 'Instructor';
    public const ROLE_STUDENT = 'Student';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';
    public const STATUS_ARCHIVED = 'Archived';

    public const CREATED_AT = 'Created_At';
    public const UPDATED_AT = null;

    protected $table = 'User_Accounts';

    protected $primaryKey = 'User_ID';

    protected $fillable = [
        'First_Name',
        'Last_Name',
        'Middle_Initial',
        'Name_Suffix',
        'Username',
        'Email',
        'Student_No',
        'Employee_ID',
        'Password',
        'Role',
        'Status',
    ];

    protected $hidden = [
        'Password',
    ];

    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'Created_At' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'Password';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->Email;
    }

    public function routeNotificationForMail(): string
    {
        return $this->Email;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(GraduateProfile::class, 'User_ID', 'User_ID');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(UserMessage::class, 'Sender_ID', 'User_ID');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(UserMessage::class, 'Recipient_ID', 'User_ID');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'Author_ID', 'User_ID');
    }

    public function fullName(): string
    {
        $parts = [$this->First_Name];
        if ($this->Middle_Initial) {
            $parts[] = rtrim($this->Middle_Initial, '.') . '.';
        }
        $parts[] = $this->Last_Name;
        if ($this->Name_Suffix) {
            $parts[] = $this->Name_Suffix;
        }

        return trim(implode(' ', $parts));
    }

    public function isAdmin(): bool
    {
        return $this->Role === self::ROLE_ADMIN;
    }

    public function isInstructor(): bool
    {
        return $this->Role === self::ROLE_INSTRUCTOR;
    }

    public function isStudent(): bool
    {
        return $this->Role === self::ROLE_STUDENT;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isInstructor();
    }

    public function isActive(): bool
    {
        return $this->Status === self::STATUS_ACTIVE;
    }
}
