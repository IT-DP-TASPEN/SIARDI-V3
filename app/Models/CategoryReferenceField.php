<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryReferenceField extends Model
{
    protected $fillable = [
        'category_id',
        'reference_type',
        'label',
        'help_text',
        'input_type',
        'sort_order',
        'is_required',
        'is_primary_match_key',
        'normalizer',
        'dwh_entity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'bool',
            'is_primary_match_key' => 'bool',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function archiveBusinessReferences(): HasMany
    {
        return $this->hasMany(ArchiveBusinessReference::class);
    }
}
