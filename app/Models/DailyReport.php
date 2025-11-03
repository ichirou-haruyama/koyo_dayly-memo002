<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'report_date',
        'user_id',
        'project_id',
        'project_code',
        'project_name',
        'work_start_time',
        'work_end_time',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'work_start_time' => 'datetime:H:i:s',
            'work_end_time' => 'datetime:H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function travels(): HasMany
    {
        return $this->hasMany(DailyReportTravel::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(DailyReportMember::class);
    }
}
