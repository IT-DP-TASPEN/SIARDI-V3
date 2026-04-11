<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveBusinessReference extends Model
{
    protected $fillable = [
        'archive_id',
        'category_reference_field_id',
        'reference_type',
        'raw_value',
        'normalized_value',
        'source_system',
        'source_table',
        'source_key_name',
        'branch_code',
        'matched_table',
        'matched_source_key',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function categoryReferenceField(): BelongsTo
    {
        return $this->belongsTo(CategoryReferenceField::class);
    }
}
