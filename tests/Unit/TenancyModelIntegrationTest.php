<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingUnitAllocation;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\InventoryStock;
use App\Models\InventoryStockMovement;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\RentalConfiguration;
use App\Models\Tenant;
use App\Models\TenantBusinessProfile;
use App\Models\TenantOnboarding;
use App\Models\TenantPaymentMethod;
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
        InventoryStockMovement::class,
        Booking::class,
        BookingItem::class,
        BookingUnitAllocation::class,
        Payment::class,
        Invoice::class,
        MaintenanceRecord::class,
        ProductMovement::class,
        ProductPrice::class,
        RentalConfiguration::class,
        TenantBusinessProfile::class,
        TenantOnboarding::class,
        TenantPaymentMethod::class,
    ];

    foreach ($models as $model) {
        expect(class_uses_recursive($model))
            ->toContain(BelongsToTenant::class);
    }
});

test('tenant models use incrementing bigint identifiers', function () {
    $tenantModels = [
        Branch::class,
        Customer::class,
        Category::class,
        Product::class,
        ProductUnit::class,
        InventoryStock::class,
    ];

    foreach ($tenantModels as $modelClass) {
        $model = new $modelClass;

        expect(class_uses_recursive($modelClass))
            ->not->toContain(HasUuids::class)
            ->and($model->getKeyType())->toBe('int')
            ->and($model->getIncrementing())->toBeTrue();
    }
});

test('tenant migrations do not declare uuid identifiers', function () {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/tenant/*.php';

    foreach (glob($migrationPath) as $migration) {
        expect(file_get_contents($migration))
            ->not->toContain('->uuid(')
            ->not->toContain('->foreignUuid(')
            ->not->toContain('->uuidMorphs(');
    }
});
