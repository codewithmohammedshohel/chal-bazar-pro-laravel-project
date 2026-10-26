<?php

namespace Tests\Feature\Orders;

use App\Contracts\InvoicePdfRenderer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\Invoices\MpdfInvoicePdfRenderer;
use App\Services\OrderService;
use App\Support\Bangla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = $this->app->make(OrderService::class);
        config()->set('invoice.pdf.enabled', true);
    }

    private function place(User $customer, array $items, float $lat = DeliveryFeeService::SHOP_LAT, float $lng = DeliveryFeeService::SHOP_LNG): Order
    {
        return $this->orders->place(
            customer: $customer,
            items: $items,
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: $lat,
            lng: $lng,
        );
    }

    private function makeOrder(): Order
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);

        return $this->place($customer, [['variant_id' => $variant->id, 'quantity' => 2]]);
    }

    public function test_customer_can_download_their_own_invoice_as_pdf(): void
    {
        $order = $this->makeOrder();
        $customer = $order->user;

        $response = $this->actingAs($customer)->get(route('orders.invoice.pdf', $order));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $pdf = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf), 'The generated PDF is suspiciously small.');
        $this->assertStringContainsString('HindSiliguri', $pdf, 'The Bengali font was not embedded.');
        $this->assertStringNotContainsString(
            'FreeSerif',
            $pdf,
            'mPDF fell back to its built-in font, which does not shape Bengali conjuncts.',
        );
        $this->assertStringContainsString('/Type /Page', $pdf);

        $this->assertStringContainsString(
            'filename="invoice-'.$order->invoice->invoice_number.'.pdf"',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_customer_cannot_download_another_customers_invoice(): void
    {
        $order = $this->makeOrder();
        $other = User::factory()->customer()->create();

        $this->actingAs($other)
            ->get(route('orders.invoice.pdf', $order))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $order = $this->makeOrder();

        $this->get(route('orders.invoice.pdf', $order))->assertRedirect(route('login'));
    }

    public function test_staff_without_invoices_permission_is_denied(): void
    {
        $order = $this->makeOrder();
        $staff = User::factory()->staff()->create();

        $this->assertFalse($staff->hasPermission('invoices'));

        $this->actingAs($staff)
            ->get(route('orders.invoice.pdf', $order))
            ->assertForbidden();
    }

    public function test_admin_with_invoices_permission_can_download_any_invoice(): void
    {
        $order = $this->makeOrder();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasPermission('invoices'));

        $this->actingAs($admin)
            ->get(route('orders.invoice.pdf', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_repeated_downloads_keep_the_invoice_number_and_do_not_issue_a_new_invoice(): void
    {
        $order = $this->makeOrder();
        $number = $order->invoice->invoice_number;

        $this->actingAs($order->user)->get(route('orders.invoice.pdf', $order))->assertOk();
        $this->actingAs($order->user)->get(route('orders.invoice.pdf', $order))->assertOk();

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame($number, $order->fresh()->invoice->invoice_number);
    }

    public function test_pdf_document_uses_historical_order_snapshots(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);
        $order = $this->place(
            $customer,
            [['variant_id' => $variant->id, 'quantity' => 2]],
            lat: 23.8103,
            lng: 90.4125,
        );

        $originalTitle = $order->items->first()->product_title;
        $invoice = $order->invoice;

        // Later catalogue edits must not leak into the issued document.
        $variant->update(['retail_price' => 999.00]);
        $variant->product->update(['title' => 'Renamed Product']);

        $html = $this->app->make(MpdfInvoicePdfRenderer::class)->html($order->fresh());

        $this->assertStringContainsString($originalTitle, $html);
        $this->assertStringNotContainsString('Renamed Product', $html);
        $this->assertStringNotContainsString('999.00', $html);

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString($order->code, $html);
        $this->assertStringContainsString('ইনভয়েস', $html);
        $this->assertStringContainsString(Bangla::money($invoice->subtotal), $html);
        $this->assertStringContainsString(Bangla::money($invoice->delivery_fee), $html);
        $this->assertStringContainsString(Bangla::money($invoice->total), $html);
        $this->assertStringContainsString('সর্বমোট', $html);

        // Bengali digits, not ASCII digits, must be used for amounts.
        $this->assertStringNotContainsString('৳ '.number_format((float) $invoice->total, 2), $html);
    }

    public function test_rendering_a_pdf_does_not_change_order_invoice_or_stock_records(): void
    {
        $order = $this->makeOrder();
        $invoice = $order->invoice;
        $stock = StockLevel::query()->where('product_variant_id', $order->items->first()->product_variant_id)->firstOrFail();

        $orderSnapshot = $order->only(['status', 'subtotal', 'delivery_fee', 'total', 'delivered_at', 'cancelled_at']);
        $stockSnapshot = $stock->only(['on_hand_kg', 'reserved_kg', 'available_kg']);
        $invoiceSnapshot = $invoice->only(['invoice_number', 'subtotal', 'delivery_fee', 'total', 'issued_at']);

        $this->actingAs($order->user)->get(route('orders.invoice.pdf', $order))->assertOk();

        $this->assertSame($orderSnapshot, $order->fresh()->only(array_keys($orderSnapshot)));
        $this->assertSame($stockSnapshot, $stock->fresh()->only(array_keys($stockSnapshot)));
        $this->assertSame(
            array_map('strval', $invoiceSnapshot),
            array_map('strval', $invoice->fresh()->only(array_keys($invoiceSnapshot))),
        );
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_pdf_rendering_failure_is_isolated_and_reported_generically(): void
    {
        $order = $this->makeOrder();
        $invoiceNumber = $order->invoice->invoice_number;
        $total = (string) $order->total;

        $this->app->bind(InvoicePdfRenderer::class, fn () => new class implements InvoicePdfRenderer
        {
            public function render(Order $order): string
            {
                throw new RuntimeException('font engine exploded at C:\\secret\\path');
            }
        });

        $response = $this->actingAs($order->user)->get(route('orders.invoice.pdf', $order));

        $response->assertStatus(503);
        $response->assertSee('PDF ইনভয়েস তৈরি করা যায়নি');
        $response->assertDontSee('font engine exploded');

        $order->refresh();

        $this->assertSame($invoiceNumber, $order->invoice->invoice_number);
        $this->assertSame($total, (string) $order->total);
        $this->assertSame(1, Invoice::query()->count());

        // The printable HTML fallback must still work after a PDF failure.
        $this->actingAs($order->user)->get(route('orders.invoice', $order))->assertOk();
    }

    public function test_pdf_download_is_disabled_when_configuration_turns_it_off(): void
    {
        $order = $this->makeOrder();
        config()->set('invoice.pdf.enabled', false);

        $this->actingAs($order->user)
            ->get(route('orders.invoice.pdf', $order))
            ->assertNotFound();
    }

    public function test_customer_order_page_links_to_the_pdf_invoice(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($order->user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee(route('orders.invoice.pdf', $order), false)
            ->assertSee('ইনভয়েস PDF');
    }
}
