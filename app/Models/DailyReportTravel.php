<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportTravel extends Model
{
    use HasFactory;

    protected $table = 'daily_report_travels';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_report_id',
        'direction',
        'depart_time',
        'arrive_time',
        'distance_km',
        'use_highway',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depart_time' => 'datetime:H:i:s',
            'arrive_time' => 'datetime:H:i:s',
            'distance_km' => 'decimal:2',
            'use_highway' => 'boolean',
        ];
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }
}
