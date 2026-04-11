<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'category_name',
        'category_description',
    ];

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class, 'archive_category');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function referenceFields(): HasMany
    {
        return $this->hasMany(CategoryReferenceField::class)->orderBy('sort_order');
    }
}
