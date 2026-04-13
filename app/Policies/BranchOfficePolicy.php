<?php

namespace App\Policies;

use App\Models\BranchOffice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchOfficePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_branch::office');
    }

    public function view(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('view_branch::office');
    }

    public function create(User $user): bool
    {
        return $user->can('create_branch::office');
    }

    public function update(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('update_branch::office');
    }

    public function delete(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('delete_branch::office');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_branch::office');
    }

    public function restore(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('restore_branch::office');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_branch::office');
    }

    public function replicate(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('replicate_branch::office');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_branch::office');
    }

    public function forceDelete(User $user, BranchOffice $branchOffice): bool
    {
        return $user->can('force_delete_branch::office');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_branch::office');
    }
}
