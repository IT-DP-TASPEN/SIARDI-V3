<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Archive extends Model
{
    protected $table = 'archives';

    protected $fillable = [
        'archive_category',
        'archive_user',
        'archive_name',
        'archive_code',
        'archive_description',
        'archive_path',
        'archive_type',
        'archive_branch_office',
        'archive_date',
    ];

    public function businessReferences(): HasMany
    {
        return $this->hasMany(ArchiveBusinessReference::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'archive_category');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_user');
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class, 'archive_branch_office');
    }

    public function legacyInactiveMarker(): HasOne
    {
        return $this->hasOne(LegacyArchiveInactive::class);
    }
}
