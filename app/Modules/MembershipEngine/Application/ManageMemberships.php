<?php

namespace App\Modules\MembershipEngine\Application;

use App\Models\Membership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ManageMemberships
{
    public function paginate(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return Membership::query()
            ->with(['product', 'customer'])
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $attributes): Membership
    {
        $attributes['membership_number'] ??= 'MBR-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        $attributes['status'] ??= 'pending';

        return Membership::create($attributes)->load(['product', 'customer']);
    }

    public function detail(Membership $membership): Membership
    {
        return $membership->load(['product', 'customer']);
    }

    public function update(Membership $membership, array $attributes): Membership
    {
        $membership->update($attributes);

        return $membership->refresh()->load(['product', 'customer']);
    }
}
