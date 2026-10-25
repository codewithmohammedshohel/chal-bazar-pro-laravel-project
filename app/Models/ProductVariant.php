<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'product_id',
    'unit',
    'weight_kg',
    'retail_price',
    'wholesale_price',
    'purchase_cost',
    'label_bn',
    'label_en',
    'is_active',
    'sort_order',
])]
#[Hidden(['wholesale_price', 'purchase_cost'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    public const UNIT_KG = 'kg';

    public const UNIT_SACK_25KG = 'sack_25kg';

    public const UNIT_SACK_50KG = 'sack_50kg';

    /** @var list<string> */
    public const UNITS = [self::UNIT_KG, self::UNIT_SACK_25KG, self::UNIT_SACK_50KG];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'purchase_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function displayLabel(): string
    {
        if (app()->getLocale() === 'bn' && $this->label_bn !== null && $this->label_bn !== '') {
            return $this->label_bn;
        }

        return $this->label_en ?: $this->unit;
    }

    public function secondaryLabel(): ?string
    {
        $primary = $this->displayLabel();

        foreach ([$this->label_bn, $this->label_en] as $label) {
            if ($label !== null && $label !== '' && $label !== $primary) {
                return $label;
            }
        }

        return null;
    }

    public function weightLabel(): string
    {
        return rtrim(rtrim((string) $this->weight_kg, '0'), '.').' kg';
    }

    public function stockLevel(): HasOne
    {
        return $this->hasOne(StockLevel::class, 'product_variant_id');
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_variant_id');
    }

    /**
     * Convert a quantity of this selling unit into canonical kilograms.
     */
    public function kilogramsFor(int $quantity): float
    {
        return round(((float) $this->weight_kg) * max(0, $quantity), 4);
    }
}
