<?php

namespace App\Modules\Organization\Application;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManageBranches
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Branch::query()->latest()->paginate($perPage);
    }

    public function create(array $attributes, ?int $userId = null): Branch
    {
        $attributes['is_active'] ??= true;
        $hasPublicColumns = Schema::hasColumns('branches', [
            'is_public',
            'is_primary',
        ]);

        if ($hasPublicColumns) {
            $attributes['is_public'] ??= false;
        }

        return DB::transaction(function () use (
            $attributes,
            $userId,
            $hasPublicColumns,
        ): Branch {
            if ($hasPublicColumns && (($attributes['is_primary'] ?? false)
                || ! Branch::query()->where('is_primary', true)->exists())) {
                $attributes['is_primary'] = true;
                $attributes['is_active'] = true;
                $attributes['is_public'] = true;
                Branch::query()->where('is_primary', true)->update([
                    'is_primary' => false,
                ]);
            }

            $branch = Branch::create($attributes);

            if ($userId !== null) {
                $branch->users()->syncWithoutDetaching([
                    $userId => ['is_primary' => false],
                ]);
            }

            return $branch;
        }, attempts: 3);
    }

    public function update(Branch $branch, array $attributes): Branch
    {
        return DB::transaction(function () use ($branch, $attributes): Branch {
            if (! Schema::hasColumns('branches', ['is_public', 'is_primary'])) {
                $branch->update($attributes);

                return $branch->refresh();
            }

            if (($attributes['is_primary'] ?? false) === true) {
                Branch::query()
                    ->whereKeyNot($branch->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
                $attributes['is_active'] = true;
                $attributes['is_public'] = true;
            }

            $branch->update($attributes);

            if ($branch->is_primary
                && (! $branch->is_active || ! $branch->is_public)) {
                $branch->update(['is_primary' => false]);
            }

            if (! $branch->is_primary
                || ! $branch->is_active
                || ! $branch->is_public) {
                $replacement = Branch::query()
                    ->whereKeyNot($branch->getKey())
                    ->where('is_active', true)
                    ->where('is_public', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($replacement !== null
                    && ! Branch::query()->where('is_primary', true)->exists()) {
                    $replacement->update(['is_primary' => true]);
                }
            }

            return $branch->refresh();
        }, attempts: 3);
    }
}
