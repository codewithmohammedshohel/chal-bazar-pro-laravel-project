<?php

namespace Tests\Feature\Orders;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = $this->app->make(OrderService::class);
    }

    private function place(User $customer, array $items, ?string $key = null, float $lat = DeliveryFeeService::SHOP_LAT, float $lng = DeliveryFeeService::SHOP_LNG): Order
    {
        return $this->orders->place(
            customer: $customer,
            items: $items,
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: $lat,
            lng: $lng,
            idempotencyKey: $key,
        );
    }

    public function test_invoice_numbers_are_unique_across_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 100, onHandKg: 100);

        $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-1');
        $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-2');
        $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-3');

        $numbers = Invoice::query()->orderBy('id')->pluck('invoice_number')->all();

        $this->assertCount(3, $numbers);
        $this->assertCount(3, array_unique($numbers));

        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $number);
        }
    }

    public function test_invoice_sequence_is_database_protected_per_period(): void
    {
        $first = InvoiceSequence::nextNumber('209901');
        $second = InvoiceSequence::nextNumber('209901');
        $otherPeriod = InvoiceSequence::nextNumber('209902');

        $this->assertSame('INV-209901-000001', $first);
        $this->assertSame('INV-209901-000002', $second);
        $this->assertSame('INV-209902-000001', $otherPeriod);
    }

    public function test_invoice_keeps_the_amounts_charged_at_placement_time(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);

        $order = $this->place(
            $customer,
            [['variant_id' => $variant->id, 'quantity' => 2]],
            'snapshot-1',
            lat: 23.8103, // Dhaka, far enough to incur a delivery fee
            lng: 90.4125,
        );

        $invoice = $order->invoice;

        $this->assertNotNull($invoice);
        $this->assertSame((string) $order->subtotal, (string) $invoice->subtotal);
        $this->assertSame((string) $order->delivery_fee, (string) $invoice->delivery_fee);
        $this->assertSame((string) $order->total, (string) $invoice->total);
        $this->assertGreaterThan(0.0, (float) $invoice->delivery_fee);

        $invoiceNumber = $invoice->invoice_number;

        // Later catalogue changes must not rewrite historical invoice amounts.
        $variant->update(['retail_price' => 999.00]);
        $variant->product->update(['title' => 'Renamed Product']);

        $invoice->refresh();

        $this->assertSame($invoiceNumber, $invoice->invoice_number);
        $this->assertSame('145.00', $invoice->subtotal);
        $this->assertSame((string) $order->total, (string) $invoice->total);
    }

    public function test_owner_can_open_a_printable_invoice(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);
        $order = $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]]);

        $this->actingAs($customer)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee($order->invoice->invoice_number)
            ->assertSee($order->code);
    }

    public function test_admin_with_invoices_permission_can_open_any_invoice(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]]);

        $this->assertTrue($admin->hasPermission('invoices'));

        $this->actingAs($admin)
            ->get(route('orders.invoice', $order))
            ->assertOk();
    }

    public function test_staff_without_invoices_permission_cannot_open_a_customer_invoice(): void
    {
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 1]]);

        $this->assertFalse($staff->hasPermission('invoices'));

        $this->actingAs($staff)
            ->get(route('orders.invoice', $order))
            ->assertForbidden();
    }
}
