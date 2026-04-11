<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'branch_office_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class, 'archive_user');
    }

    public function permittedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class, 'branch_office_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->withPivotValue('model_type', self::class)
            ->wherePivot('model_type', self::class);
    }

    public function hasRole(string | array $roleNames): bool
    {
        return $this->roles->pluck('name')->intersect((array) $roleNames)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(config('siardi.admin_roles', ['super_admin']));
    }

    public function primaryRoleName(): string
    {
        return (string) ($this->roles->pluck('name')->first() ?? 'user');
    }

    public function scopeRole(Builder $query, string | array $roleNames): Builder
    {
        return $query->whereHas('roles', function (Builder $roleQuery) use ($roleNames): void {
            $roleQuery->whereIn('name', (array) $roleNames);
        });
    }
}
