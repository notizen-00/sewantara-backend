<?php

namespace App\Modules\Customers\Application;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ManageCustomers
{
    public function paginate(?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return Customer::query()
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->when($search, fn ($query, string $value) => $query->where('name', 'ilike', "%{$value}%"))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): Customer
    {
        $attributes['status'] ??= 'active';

        return Customer::create($attributes);
    }

    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer->refresh();
    }
}
