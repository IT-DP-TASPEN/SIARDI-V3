<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyArchiveInactive extends Model
{
    protected $fillable = [
        'archive_id',
        'marked_inactive_by',
        'marked_inactive_at',
    ];

    protected function casts(): array
    {
        return [
            'marked_inactive_at' => 'datetime',
        ];
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_inactive_by');
    }
}
