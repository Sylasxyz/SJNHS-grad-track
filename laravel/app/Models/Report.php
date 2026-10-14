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

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return is_array($this->Summary_JSON) ? $this->Summary_JSON : [];
    }

    /** Generated_Date exactly as stored, the way the legacy page prints it. */
    public function generatedDate(): string
    {
        return (string) $this->getRawOriginal('Generated_Date');
    }
}
