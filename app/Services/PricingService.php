<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative order pricing.
 *
 * The browser may only send variant ids and quantities. Every price, the
 * subtotal, the delivery fee and the total are recomputed here from the
 * database so client-submitted money values can never become authoritative.
 */
class PricingService
{
    public const MAX_QUANTITY_PER_LINE = 100;

    public function __construct(private readonly DeliveryFeeService $delivery)
    {
    }

    /**
     * @param  array<int, array{variant_id: int|string, quantity: int|string}>  $items
     * @return array{
     *     lines: array<int, array{variant_id:int, product_id:int, product_title:string, variant_label:string,
     *         unit:string, weight_kg:string, unit_price:string, quantity:int, line_total:string, requested_kg:float}>,
     *     subtotal: float, delivery_fee: float, distance_km: float, total: float
     * }
     */
    public function quote(array $items, float $lat, float $lng): array
    {
        $normalized = $this->normalizeItems($items);

        $variantIds = collect($normalized)->pluck('variant_id')->unique()->values();

        $variants = ProductVariant::query()
            ->with(['product.category'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($normalized as $line) {
            $variant = $variants->get($line['variant_id']);

            $this->assertPurchasable($variant, $line['variant_id']);

            $unitPrice = (float) $variant->retail_price;
            $quantity = $line['quantity'];
            $lineTotal = round($unitPrice * $quantity, 2);

            $lines[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_title' => $variant->product->displayTitle(),
                'variant_label' => $variant->displayLabel(),
                'unit' => $variant->unit,
                'weight_kg' => (string) $variant->weight_kg,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'quantity' => $quantity,
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'requested_kg' => $variant->kilogramsFor($quantity),
            ];
        }

        $subtotal = round(array_sum(array_map(
            static fn (array $line): float => (float) $line['line_total'],
            $lines,
        )), 2);

        $delivery = $this->delivery->quote($lat, $lng);
        $total = round($subtotal + $delivery['delivery_fee'], 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery['delivery_fee'],
            'distance_km' => $delivery['distance_km'],
            'total' => $total,
        ];
    }

    /**
     * Merge duplicate variant rows and validate the raw shape.
     *
     * @param  array<int, array{variant_id: int|string, quantity: int|string}>  $items
     * @return array<int, array{variant_id:int, quantity:int}>
     */
    public function normalizeItems(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'একটি বৈধ পণ্য ভ্যারিয়েন্ট নির্বাচন করুন।',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => 'পরিমাণ অন্তত ১ হতে হবে।',
                ]);
            }

            if ($quantity > self::MAX_QUANTITY_PER_LINE) {
                throw ValidationException::withMessages([
                    'items' => 'প্রতি পণ্যে সর্বোচ্চ '.self::MAX_QUANTITY_PER_LINE.' ইউনিট।',
                ]);
            }

            $merged[$variantId] = ($merged[$variantId] ?? 0) + $quantity;

            if ($merged[$variantId] > self::MAX_QUANTITY_PER_LINE) {
                throw ValidationException::withMessages([
                    'items' => 'প্রতি পণ্যে সর্বোচ্চ '.self::MAX_QUANTITY_PER_LINE.' ইউনিট।',
                ]);
            }
        }

        if ($merged === []) {
            throw ValidationException::withMessages([
                'items' => 'কার্ট খালি।',
            ]);
        }

        $normalized = [];

        foreach ($merged as $variantId => $quantity) {
            $normalized[] = ['variant_id' => $variantId, 'quantity' => $quantity];
        }

        return $normalized;
    }

    private function assertPurchasable(?ProductVariant $variant, int $variantId): void
    {
        if ($variant === null) {
            throw ValidationException::withMessages([
                'items' => "নির্বাচিত ভ্যারিয়েন্ট (#{$variantId}) পাওয়া যায়নি।",
            ]);
        }

        $product = $variant->product;

        if (! $variant->is_active
            || $product === null
            || ! $product->is_active
            || $product->category === null
            || ! $product->category->is_active) {
            throw ValidationException::withMessages([
                'items' => 'নির্বাচিত পণ্যটি বর্তমানে বিক্রয়ের জন্য উপলব্ধ নয়।',
            ]);
        }
    }
}
