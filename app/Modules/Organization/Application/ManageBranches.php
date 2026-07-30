<?php

namespace App\Modules\Organization\Application;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ManageBranches
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Branch::query()->latest()->paginate($perPage);
    }

    public function create(array $attributes, ?int $userId = null): Branch
    {
        $attributes['is_active'] ??= true;

        $branch = Branch::create($attributes);

        if ($userId !== null) {
            $branch->users()->syncWithoutDetaching([
                $userId => ['is_primary' => false],
            ]);
        }

        return $branch;
    }
}
