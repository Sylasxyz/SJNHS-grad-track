<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    public const AUDIENCE_EVERYONE = 'Everyone';
    public const AUDIENCE_SPECIFIC = 'Specific';

    public const CREATED_AT = 'Date_Posted';
    public const UPDATED_AT = null;

    protected $table = 'Announcements';

    protected $primaryKey = 'Announcement_ID';

    protected $fillable = [
        'Author_ID',
        'Title',
        'Message',
        'Image_Path',
        'Audience',
        'Is_Archived',
    ];

    protected function casts(): array
    {
        return [
            'Date_Posted' => 'datetime',
            'Is_Archived' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'Author_ID', 'User_ID');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(UserAccount::class, 'Announcement_Recipients', 'Announcement_ID', 'User_ID');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('Is_Archived', 0);
    }

    public function scopeVisibleTo(Builder $query, UserAccount $user): Builder
    {
        if ($user->isStaff()) {
            return $query;
        }

        return $query->active()->where(function (Builder $q) use ($user) {
            $q->where('Audience', self::AUDIENCE_EVERYONE)
                ->orWhereHas('recipients', fn (Builder $r) => $r->where('User_Accounts.User_ID', $user->User_ID));
        });
    }
}
