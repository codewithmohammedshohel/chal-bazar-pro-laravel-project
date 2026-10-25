<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'type',
    'slug',
    'title',
    'name_bn',
    'name_en',
    'description',
    'mill_name',
    'supplier',
    'grain_type',
    'processing_method',
    'is_featured',
    'is_active',
    'meta_title',
    'meta_description',
    'legacy_firebase_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_RICE = 'rice';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [self::TYPE_RICE, self::TYPE_OTHER];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('title', 'like', $like)
                ->orWhere('name_bn', 'like', $like)
                ->orWhere('name_en', 'like', $like)
                ->orWhere('grain_type', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function displayTitle(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn !== null && $this->name_bn !== ''
            ? $this->name_bn
            : $this->title;
    }

    public function secondaryTitle(): ?string
    {
        return app()->getLocale() === 'bn' ? $this->name_en : $this->name_bn;
    }

    public function primaryImage(): ?ProductMedia
    {
        return $this->media->firstWhere('kind', 'image') ?? $this->media->first();
    }

    public function lowestRetailPrice(): ?float
    {
        $prices = $this->variants
            ->where('is_active', true)
            ->map(fn (ProductVariant $variant): float => (float) $variant->retail_price);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }
}
