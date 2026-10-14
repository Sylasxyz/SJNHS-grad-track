<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public const CREATED_AT = 'Generated_Date';
    public const UPDATED_AT = null;

    protected $table = 'Reports';

    protected $primaryKey = 'Report_ID';

    protected $fillable = [
        'Report_Name',
        'Summary_JSON',
    ];

    protected function casts(): array
    {
        return [
            'Generated_Date' => 'datetime',
            'Summary_JSON' => 'array',
        ];
    }
}
