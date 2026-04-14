<?php

namespace App\Services;

use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ArchiveVisibilityService
{
    public function applyArchiveScope(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        if ($this->canViewAllArchives($user)) {
            return $query;
        }

        $visibleCategoryIds = $this->visibleCategoryIds($user);

        if ($visibleCategoryIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('archive_category', $visibleCategoryIds);

        if (! $this->canViewAllBranches($user)) {
            $query->where('archive_branch_office', $user->branch_office_id);
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    public function visibleCategoryIds(?User $user): array
    {
        if (! $user || $this->canViewAllArchives($user)) {
            return Category::query()->pluck('id')->all();
        }

        return $user->permittedCategories()
            ->orderBy('category_name')
            ->pluck('categories.id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function visibleSupportedCategoryIds(?User $user): array
    {
        $visibleCategoryIds = $this->visibleCategoryIds($user);

        if ($visibleCategoryIds === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $visibleCategoryIds)
            ->whereIn('category_name', array_keys(config('siardi.supported_reconciliation_categories', [])))
            ->orderBy('category_name')
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function visibleBranchOfficeIds(?User $user): array
    {
        if (! $user || $this->canViewAllBranches($user)) {
            return BranchOffice::query()->pluck('id')->all();
        }

        return $user->branch_office_id ? [$user->branch_office_id] : [];
    }

    public function canViewAllBranches(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (! $this->isHeadOfficeUser($user)) {
            return false;
        }

        return $user->isAdmin() || $user->hasRole(['staff', 'kearsipan', 'it']);
    }

    public function canViewAllArchives(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        return $this->isHeadOfficeUser($user) && $user->isAdmin();
    }

    public function isBranchVisible(?User $user, int $branchOfficeId): bool
    {
        return in_array($branchOfficeId, $this->visibleBranchOfficeIds($user), true);
    }

    public function isCategoryVisible(?User $user, int $categoryId, bool $supportedOnly = false): bool
    {
        $visibleCategoryIds = $supportedOnly
            ? $this->visibleSupportedCategoryIds($user)
            : $this->visibleCategoryIds($user);

        return in_array($categoryId, $visibleCategoryIds, true);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function filterCoverageRows(Collection $rows, ?User $user): Collection
    {
        $rows = $rows->whereIn('category_id', $this->visibleSupportedCategoryIds($user));

        if (! $this->canViewAllBranches($user)) {
            $rows = $rows->whereIn('branch_office_id', $this->visibleBranchOfficeIds($user));
        }

        return $rows->values();
    }

    private function isHeadOfficeUser(User $user): bool
    {
        $user->loadMissing('branchOffice');

        return $user->branchOffice?->branch_code === '00';
    }
}
