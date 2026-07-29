<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingUnitAllocation;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

test('central models integrate with tenancy and subscriptions', function () {
    expect(Tenant::class)
        ->toExtend(BaseTenant::class)
        ->and(class_uses_recursive(Tenant::class))
        ->toContain(HasPlanSubscriptions::class)
        ->and(is_subclass_of(Tenant::class, TenantWithDatabase::class))
        ->toBeTrue()
        ->and(Domain::class)
        ->toExtend(BaseDomain::class);
});

test('operational models are scoped to the active tenant', function () {
    $models = [
        User::class,
        Branch::class,
        Customer::class,
        Category::class,
        Product::class,
        ProductUnit::class,
        InventoryStock::class,
        Booking::class,
        BookingItem::class,
        BookingUnitAllocation::class,
        Payment::class,
        Invoice::class,
    ];

    foreach ($models as $model) {
        expect(class_uses_recursive($model))
            ->toContain(BelongsToTenant::class);
    }
});

test('master data models use incrementing integer identifiers', function () {
    $masterModels = [
        Branch::class,
        Customer::class,
        Category::class,
        Product::class,
        ProductUnit::class,
        InventoryStock::class,
    ];

    foreach ($masterModels as $modelClass) {
        $model = new $modelClass;

        expect(class_uses_recursive($modelClass))
            ->not->toContain(HasUuids::class)
            ->and($model->getKeyType())->toBe('int')
            ->and($model->getIncrementing())->toBeTrue();
    }
});
