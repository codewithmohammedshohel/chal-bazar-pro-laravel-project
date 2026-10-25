<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;

/**
 * Small session-backed cart for authenticated customers.
 *
 * Only variant ids and integer quantities are stored; money is always
 * recomputed from the database by PricingService at checkout time.
 */
class CartService
{
    public const SESSION_KEY = 'cart.items';

    public const MAX_QUANTITY = PricingService::MAX_QUANTITY_PER_LINE;

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * @return array<int, int>
     */
    public function raw(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        return array_filter(
            array_map('intval', $items),
            static fn (int $quantity): bool => $quantity > 0,
        );
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    public function add(int $variantId, int $quantity = 1): void
    {
        $quantity = max(1, min($quantity, self::MAX_QUANTITY));

        $items = $this->raw();
        $items[$variantId] = min(($items[$variantId] ?? 0) + $quantity, self::MAX_QUANTITY);

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function update(int $variantId, int $quantity): void
    {
        $items = $this->raw();

        if ($quantity <= 0) {
            unset($items[$variantId]);
        } else {
            $items[$variantId] = min($quantity, self::MAX_QUANTITY);
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(int $variantId): void
    {
        $this->update($variantId, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Hydrate the raw cart with live variant data.
     *
     * @return array<int, array{variant: ProductVariant, quantity: int, unit_price: float, line_total: float}>
     */
    public function items(): array
    {
        $raw = $this->raw();

        if ($raw === []) {
            return [];
        }

        $variants = ProductVariant::query()
            ->with(['product.category'])
            ->whereIn('id', array_keys($raw))
            ->get();

        $lines = [];

        foreach ($variants as $variant) {
            $product = $variant->product;

            if (! $variant->is_active
                || $product === null
                || ! $product->is_active
                || $product->category === null
                || ! $product->category->is_active) {
                continue;
            }

            $quantity = $raw[$variant->id] ?? 0;
            $unitPrice = (float) $variant->retail_price;

            $lines[] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, array{variant_id:int, quantity:int}>
     */
    public function toPricingItems(): array
    {
        $items = [];

        foreach ($this->raw() as $variantId => $quantity) {
            $items[] = ['variant_id' => (int) $variantId, 'quantity' => (int) $quantity];
        }

        return $items;
    }
}
