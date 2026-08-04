<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\Branch;

final class PublicBranchResolver
{
    private bool $resolved = false;

    private ?Branch $branch = null;

    public function resolve(): ?Branch
    {
        if ($this->resolved) {
            return $this->branch;
        }

        $this->resolved = true;
        $this->branch = Branch::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('is_primary', true)
            ->orderBy('id')
            ->first([
                'id',
                'tenant_id',
                'name',
                'code',
                'email',
                'phone',
                'address',
                'latitude',
                'longitude',
                'is_primary',
            ]);

        return $this->branch;
    }
}
