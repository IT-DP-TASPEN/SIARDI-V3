<?php

namespace App\Policies;

use App\Models\Archive;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArchivePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_archive');
    }

    public function view(User $user, Archive $archive): bool
    {
        return $user->can('view_archive');
    }

    public function create(User $user): bool
    {
        return $user->can('create_archive');
    }

    public function update(User $user, Archive $archive): bool
    {
        return $user->can('update_archive');
    }

    public function delete(User $user, Archive $archive): bool
    {
        return $user->can('delete_archive');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_archive');
    }

    public function restore(User $user, Archive $archive): bool
    {
        return $user->can('restore_archive');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_archive');
    }

    public function replicate(User $user, Archive $archive): bool
    {
        return $user->can('replicate_archive');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_archive');
    }

    public function forceDelete(User $user, Archive $archive): bool
    {
        return $user->can('force_delete_archive');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_archive');
    }
}
