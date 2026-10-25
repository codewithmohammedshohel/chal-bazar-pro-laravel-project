<?php

namespace App\Models;

use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'kind',
    'url',
    'cloudinary_public_id',
    'alt_bn',
    'alt_en',
    'sort_order',
])]
class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    /** @var list<string> */
    public const KINDS = [self::KIND_IMAGE, self::KIND_VIDEO];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    public function altText(string $fallback = ''): string
    {
        if (app()->getLocale() === 'bn' && $this->alt_bn !== null && $this->alt_bn !== '') {
            return $this->alt_bn;
        }

        return $this->alt_en ?: $fallback;
    }
}
