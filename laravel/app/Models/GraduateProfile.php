<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduateProfile extends Model
{
    public const STRANDS = ['HUMMS', 'STEMS', 'ABM'];

    public const EMPLOYMENT_STATUSES = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];

    public const CREATED_AT = null;
    public const UPDATED_AT = 'Last_Updated';

    protected $table = 'Graduate_Profiles';

    protected $primaryKey = 'Profile_ID';

    protected $fillable = [
        'User_ID',
        'Full_Name',
        'Course',
        'Batch_Year',
        'Employment_Status',
        'Company_School_Name',
        'Further_Studies_Program',
        'Contact_Info',
        'Profile_Picture',
        'Is_Verified',
        'Verified_By',
        'Verified_At',
    ];

    protected function casts(): array
    {
        return [
            'Batch_Year' => 'integer',
            'Is_Verified' => 'boolean',
            'Verified_At' => 'datetime',
            'Last_Updated' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'User_ID', 'User_ID');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'Verified_By', 'User_ID');
    }
}
