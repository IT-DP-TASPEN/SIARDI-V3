<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\User;

class CategoryObserver
{
    public function created(Category $category): void
    {
        User::query()
            ->role('super_admin')
            ->get()
            ->each(function (User $user) use ($category): void {
                $user->permittedCategories()->syncWithoutDetaching([$category->id]);
            });
    }
}
