<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use App\Services\WhatsApp\OrderConfirmationLink;
use App\Support\Bangla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppConfirmationTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    private OrderConfirmationLink $link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = $this->app->make(OrderService::class);
        $this->link = $this->app->make(OrderConfirmationLink::class);

        config()->set('whatsapp.enabled', true);
        config()->set('whatsapp.business_number', '8801712345678');
        config()->set('whatsapp.business_name', 'Chal Bazar Pro');
    }

    private function place(User $customer): Order
    {
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);

        return $this->orders->place(
            customer: $customer,
            items: [['variant_id' => $variant->id, 'quantity' => 2]],
            deliveryAddress: '৪৫ গোপন লেন, ঢাকা',
            lat: DeliveryFeeService::SHOP_LAT,
            lng: DeliveryFeeService::SHOP_LNG,
        );
    }

    private function messageFrom(?string $url): string
    {
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://wa.me/8801712345678?text=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('text', $query);

        return (string) $query['text'];
    }

    public function test_configured_destination_produces_a_click_to_chat_link(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $url = $this->link->for($order, $customer);

        $this->assertNotNull($url);
        $this->assertSame('https://wa.me/8801712345678', strtok((string) $url, '?'));

        $message = $this->messageFrom($url);

        $this->assertStringContainsString($order->code, $message);
        $this->assertStringContainsString(Bangla::money($order->total), $message);
        $this->assertStringContainsString('ক্যাশ অন ডেলিভারি', $message);
    }

    public function test_missing_destination_hides_the_feature(): void
    {
        config()->set('whatsapp.business_number', null);

        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $this->assertFalse($this->link->isConfigured());
        $this->assertNull($this->link->for($order, $customer));

        $this->actingAs($customer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('wa.me', false);
    }

    public function test_local_format_number_is_rejected_instead_of_guessed(): void
    {
        config()->set('whatsapp.business_number', '01712345678');

        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $this->assertNull($this->link->businessNumber());
        $this->assertNull($this->link->for($order, $customer));
    }

    public function test_business_number_normalisation(): void
    {
        $this->assertSame('8801712345678', OrderConfirmationLink::normalize('+880 1712-345678'));
        $this->assertSame('8801712345678', OrderConfirmationLink::normalize('8801712345678'));
        $this->assertSame('8801712345678', OrderConfirmationLink::normalize('008801712345678'));
        $this->assertSame('8801712345678', OrderConfirmationLink::normalize('(880) 1712 345 678'));

        $this->assertNull(OrderConfirmationLink::normalize(''));
        $this->assertNull(OrderConfirmationLink::normalize('01712345678'));
        $this->assertNull(OrderConfirmationLink::normalize('not-a-number'));
    }

    public function test_disabled_flag_hides_the_link(): void
    {
        config()->set('whatsapp.enabled', false);

        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $this->assertNull($this->link->for($order, $customer));
    }

    public function test_message_is_url_encoded(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $url = (string) $this->link->for($order, $customer);

        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringNotContainsString('৳', $url);
        $this->assertStringContainsString('%20', $url);
        $this->assertStringContainsString('%E0%A6', $url); // percent-encoded Bengali

        $this->assertStringContainsString($order->code, $this->messageFrom($url));
    }

    public function test_only_the_owning_customer_receives_a_link(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $otherCustomer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();

        $this->assertNotNull($this->link->for($order, $customer));
        $this->assertNull($this->link->for($order, $otherCustomer));
        $this->assertNull($this->link->for($order, $admin));
        $this->assertNull($this->link->for($order, null));

        $this->actingAs($otherCustomer)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_message_never_contains_the_delivery_address_or_secrets(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $message = $this->messageFrom($this->link->for($order, $customer));

        $this->assertStringNotContainsString('গোপন লেন', $message);
        $this->assertStringNotContainsString((string) $customer->email, $message);
        $this->assertStringNotContainsString((string) $customer->password, $message);
        $this->assertStringNotContainsString((string) config('app.key'), $message);
        $this->assertStringNotContainsString('token', strtolower($message));
    }

    public function test_client_supplied_totals_are_ignored(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $response = $this->actingAs($customer)
            ->get(route('orders.show', $order).'?total=1&amount=1&order_total=1&text=forged');

        $response->assertOk();

        $this->assertStringContainsString(
            'https://wa.me/8801712345678?text=',
            (string) $response->getContent(),
        );

        $message = $this->messageFrom($this->link->for($order, $customer));

        $this->assertStringContainsString(Bangla::money($order->total), $message);
        $this->assertStringNotContainsString('forged', $message);
        $this->assertSame((string) $order->total, (string) $order->fresh()->total);
    }

    public function test_page_does_not_claim_the_message_was_sent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $this->actingAs($customer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('WhatsApp-এ কনফার্ম করুন')
            ->assertSee('শুধু একটি বার্তা প্রস্তুত করে WhatsApp অ্যাপ খুলবে')
            ->assertDontSee('পাঠানো হয়েছে')
            ->assertDontSee('পাঠানো সম্পন্ন');
    }

    public function test_cancelled_orders_do_not_offer_a_confirmation_link(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->place($customer);

        $order->forceFill(['status' => Order::STATUS_CANCELLED])->save();

        $this->assertNull($this->link->for($order->fresh(), $customer));
    }
}
