<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportMember extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_report_id',
        'member_name',
        'note',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }
}
