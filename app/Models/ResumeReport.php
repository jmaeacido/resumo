<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResumeReport extends Model
{
    protected $fillable = [
        'user_id',
        'access_token',
        'mode',
        'score',
        'job_title',
        'analysis_json',
    ];

    protected $hidden = [
        'access_token',
        'analysis_json',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResumeReport $report) {
            if (empty($report->access_token)) {
                $report->access_token = Str::random(48);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'analysis_json' => 'array',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysis(): array
    {
        return $this->analysis_json ?? [];
    }
}
