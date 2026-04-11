<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BranchOffice extends Model
{
    protected $table = 'branch_offices';

    protected $fillable = [
        'branch_code',
        'branch_name',
        'branch_description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_office_id');
    }

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class, 'archive_branch_office', 'id');
    }

    public function dwhMapping(): HasOne
    {
        return $this->hasOne(DwhBranchMapping::class);
    }
}
