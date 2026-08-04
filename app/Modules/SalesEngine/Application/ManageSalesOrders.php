<?php

namespace App\Modules\SalesEngine\Application;

use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageSalesOrders
{
    public function paginate(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return SalesOrder::query()
            ->with(['customer', 'items'])
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(string $tenantId, array $attributes): SalesOrder
    {
        return DB::transaction(function () use ($tenantId, $attributes): SalesOrder {
            $totalAmount = 0;
            $preparedItems = [];

            foreach ($attributes['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $unitPrice = (float) $item['unit_price'];
                $quantity = (int) $item['quantity'];
                $subtotal = $unitPrice * $quantity;
                $totalAmount += $subtotal;

                $preparedItems[] = [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $order = SalesOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $attributes['customer_id'] ?? null,
                'branch_id' => $attributes['branch_id'] ?? null,
                'order_number' => 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $order->items()->createMany($preparedItems);

            return $order->load(['customer', 'items']);
        });
    }

    public function detail(SalesOrder $order): SalesOrder
    {
        return $order->load(['customer', 'items']);
    }

    public function update(SalesOrder $order, array $attributes): SalesOrder
    {
        $order->update($attributes);

        return $order->refresh()->load(['customer', 'items']);
    }
}
