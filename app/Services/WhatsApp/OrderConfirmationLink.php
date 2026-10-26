<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use App\Models\User;
use App\Support\Bangla;

/**
 * Builds a WhatsApp click-to-chat link for a customer order.
 *
 * This is intentionally a link builder only: it never contacts WhatsApp, never
 * sends a message and never claims that anything was delivered. The message is
 * composed exclusively from server-side order data, so a browser cannot forge
 * the total or the contents of the message.
 */
final class OrderConfirmationLink
{
    /** Maximum number of order lines quoted in the pre-filled message. */
    private const MAX_ITEM_LINES = 4;

    public function isConfigured(): bool
    {
        return $this->businessNumber() !== null;
    }

    /**
     * The shop's WhatsApp destination in canonical international format
     * (digits only), or null when the feature is disabled or misconfigured.
     */
    public function businessNumber(): ?string
    {
        if (! (bool) config('whatsapp.enabled', true)) {
            return null;
        }

        return self::normalize((string) config('whatsapp.business_number'));
    }

    /**
     * Click-to-chat URL for the given order, or null when the shop is not
     * configured or the viewer is not the customer who placed the order.
     */
    public function for(Order $order, ?User $viewer): ?string
    {
        $number = $this->businessNumber();

        if ($number === null || $viewer === null) {
            return null;
        }

        // Only the owning customer may receive a pre-filled confirmation link.
        // Staff and administrators never get one, even with the invoices
        // permission, because the message is written from the customer's side.
        if ((int) $order->user_id !== (int) $viewer->id) {
            return null;
        }

        if ($order->isCancelled()) {
            return null;
        }

        $query = http_build_query(
            ['text' => $this->message($order)],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return 'https://wa.me/'.$number.'?'.$query;
    }

    /**
     * The pre-filled message: order code, a short item summary and the total.
     *
     * Delivery address, e-mail, phone numbers, credentials and tokens are
     * deliberately excluded from the URL.
     */
    public function message(Order $order): string
    {
        $order->loadMissing('items');

        $lines = $order->items
            ->sortBy('id')
            ->take(self::MAX_ITEM_LINES)
            ->map(static fn ($item): string => $item->product_title.' × '.Bangla::digits((int) $item->quantity))
            ->implode(', ');

        $remaining = max(0, $order->items->count() - self::MAX_ITEM_LINES);

        if ($remaining > 0) {
            $lines .= ' এবং আরও '.Bangla::digits($remaining).'টি';
        }

        $message = "আসসালামু আলাইকুম। আমি আমার অর্ডারটি নিশ্চিত করতে চাই।\n\n"
            ."অর্ডার কোড: {$order->code}\n";

        if ($lines !== '') {
            $message .= "পণ্য: {$lines}\n";
        }

        $message .= 'মোট: '.Bangla::money($order->total).' (ক্যাশ অন ডেলিভারি)';

        $businessName = trim((string) config('whatsapp.business_name'));

        if ($businessName !== '') {
            $message .= "\n\n{$businessName}";
        }

        return $message;
    }

    /**
     * Normalise a configured number to E.164 digits.
     *
     * Accepts "+880 1712-345678", "8801712345678" or "008801712345678" and
     * rejects local-format values such as "01712345678" so that a wrong
     * destination can never silently receive customer messages.
     */
    public static function normalize(?string $number): ?string
    {
        $value = trim((string) $number);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/[\s\-().]+/', '', $value) ?? '';
        $digits = ltrim($digits, '+');
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (! preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
