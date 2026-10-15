<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMessage extends Model
{
    public const CREATED_AT = 'Sent_At';
    public const UPDATED_AT = null;

    protected $table = 'User_Messages';

    protected $primaryKey = 'Message_ID';

    protected $fillable = [
        'Sender_ID',
        'Recipient_ID',
        'Message_Text',
        'Is_Read',
    ];

    protected function casts(): array
    {
        return [
            'Sent_At' => 'datetime',
            'Is_Read' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'Sender_ID', 'User_ID');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'Recipient_ID', 'User_ID');
    }
}
