<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordReset extends Model
{
    public const CREATED_AT = 'Created_At';
    public const UPDATED_AT = null;

    protected $table = 'Password_Resets';

    protected $primaryKey = 'Reset_ID';

    protected $fillable = [
        'User_ID',
        'Token_Hash',
        'Expires_At',
        'Used_At',
    ];

    protected function casts(): array
    {
        return [
            'Expires_At' => 'datetime',
            'Used_At' => 'datetime',
            'Created_At' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'User_ID', 'User_ID');
    }
}
