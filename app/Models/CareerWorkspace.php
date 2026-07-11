<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerWorkspace extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resume_versions' => 'array',
            'applications' => 'array',
            'cover_letters' => 'array',
            'suggestions' => 'array',
            'interview_kits' => 'array',
            'linkedin_reviews' => 'array',
            'usage' => 'array',
            'last_exported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
