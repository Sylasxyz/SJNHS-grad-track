<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public const CREATED_AT = 'Attempted_At';
    public const UPDATED_AT = null;

    protected $table = 'Login_Attempts';

    protected $primaryKey = 'Attempt_ID';

    protected $fillable = [
        'Username',
        'IP_Address',
        'Was_Successful',
    ];

    protected function casts(): array
    {
        return [
            'Was_Successful' => 'boolean',
            'Attempted_At' => 'datetime',
        ];
    }
}
