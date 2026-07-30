<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantBillingSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $bookingId = $this->tenantRowId(
                'bookings',
                $tenantId,
                'booking_number',
                TenantBookingSeeder::BOOKING_NUMBER,
            );
            $ownerId = (int) DB::table('users')
                ->where('email', DemoTenantRegistrationSeeder::OWNER_EMAIL)
                ->value('id');

            $paymentId = $this->upsertTenantRow(
                table: 'payments',
                tenantId: $tenantId,
                identity: ['payment_number' => 'PAY-DEMO-0001'],
                attributes: [
                    'booking_id' => $bookingId,
                    'type' => 'down_payment',
                    'method' => 'bank_transfer',
                    'amount' => 600000,
                    'status' => 'paid',
                    'gateway' => null,
                    'gateway_reference' => null,
                    'proof_path' => 'demo/payments/transfer-proof.jpg',
                    'paid_at' => now(),
                    'expired_at' => null,
                    'notes' => 'Pembayaran muka data demo.',
                    'created_by' => $ownerId,
                ],
            );

            $invoiceId = $this->upsertTenantRow(
                table: 'invoices',
                tenantId: $tenantId,
                identity: ['invoice_number' => 'INV-DEMO-0001'],
                attributes: [
                    'booking_id' => $bookingId,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(2)->toDateString(),
                    'subtotal' => 1200000,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 1200000,
                    'paid_amount' => 600000,
                    'remaining_amount' => 600000,
                    'status' => 'partial',
                    'pdf_path' => null,
                ],
            );

            foreach ([
                [
                    'description' => 'Sony Alpha A7 IV - 2 hari',
                    'quantity' => 1,
                    'unit_amount' => 900000,
                    'total_amount' => 900000,
                ],
                [
                    'description' => 'Tripod Video Heavy Duty - 2 unit x 2 hari',
                    'quantity' => 2,
                    'unit_amount' => 150000,
                    'total_amount' => 300000,
                ],
            ] as $item) {
                $this->upsertTenantRow(
                    table: 'invoice_items',
                    tenantId: $tenantId,
                    identity: [
                        'invoice_id' => $invoiceId,
                        'description' => $item['description'],
                    ],
                    attributes: [
                        'quantity' => $item['quantity'],
                        'unit_amount' => $item['unit_amount'],
                        'total_amount' => $item['total_amount'],
                    ],
                );
            }

            $depositId = $this->upsertTenantRow(
                table: 'deposits',
                tenantId: $tenantId,
                identity: ['booking_id' => $bookingId],
                attributes: [
                    'amount' => 500000,
                    'deducted_amount' => 0,
                    'refunded_amount' => 0,
                    'remaining_amount' => 500000,
                    'status' => 'held',
                    'held_at' => now(),
                    'refunded_at' => null,
                ],
            );

            if (! DB::table('deposit_transactions')
                ->where('deposit_id', $depositId)
                ->where('type', 'hold')
                ->exists()) {
                DB::table('deposit_transactions')->insert([
                    'tenant_id' => $tenantId,
                    'deposit_id' => $depositId,
                    'type' => 'hold',
                    'amount' => 500000,
                    'reason' => 'Uang jaminan pemesanan contoh.',
                    'reference_type' => 'payment',
                    'reference_id' => $paymentId,
                    'processed_by' => $ownerId,
                    'created_at' => now(),
                ]);
            }
        });
    }
}
