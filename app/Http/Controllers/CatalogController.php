<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function home(Request $request): View
    {
        return $this->listing($request, Product::TYPE_RICE, 'storefront.home');
    }

    public function otherProducts(Request $request): View
    {
        return $this->listing($request, Product::TYPE_OTHER, 'storefront.other-products');
    }

    public function about(): View
    {
        return view('storefront.about');
    }

    public function show(Product $product): View
    {
        $product->load('category');

        abort_unless($product->is_active && $product->category?->is_active, 404);

        $product->load([
            'category',
            'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'media' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $related = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->active()
            ->ofType($product->type)
            ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->orderByDesc('is_featured')
            ->orderBy('title')
            ->limit(4)
            ->get();

        $title = $product->meta_title ?: $product->displayTitle().' | Chal Bazar Pro';
        $metaDescription = $product->meta_description
            ?: Str::limit(trim(strip_tags((string) $product->description)), 155);

        return view('storefront.product', compact('product', 'related', 'title', 'metaDescription'));
    }

    private function listing(Request $request, string $type, string $view): View
    {
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));

        $categories = Category::query()
            ->active()
            ->ordered()
            ->withCount(['activeProducts as product_count' => fn ($query) => $query->where('type', $type)])
            ->get();

        $products = Product::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->active()
            ->ofType($type)
            ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
            ->search($search)
            ->when($categorySlug !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery
                    ->where('slug', $categorySlug)
                    ->where('is_active', true),
            ))
            ->orderByDesc('is_featured')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $featured = $type === Product::TYPE_RICE
            ? Product::query()
                ->with([
                    'category',
                    'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                    'media' => fn ($query) => $query->orderBy('sort_order'),
                ])
                ->active()
                ->ofType($type)
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
                ->featured()
                ->orderBy('title')
                ->limit(3)
                ->get()
            : collect();

        return view($view, [
            'type' => $type,
            'products' => $products,
            'categories' => $categories,
            'featured' => $featured,
            'search' => $search,
            'categorySlug' => $categorySlug,
            'activeCategory' => $categories->firstWhere('slug', $categorySlug),
            'pageTitle' => $type === Product::TYPE_RICE
                ? 'চাল ও সুগন্ধি চালের সংগ্রহ | Chal Bazar Pro'
                : 'নিত্যপণ্য ও মুদি সামগ্রী | Chal Bazar Pro',
            'metaDescription' => $type === Product::TYPE_RICE
                ? 'চাল বাজার প্রো-এর চাল, সুগন্ধি চাল ও মিনিকেটের ডেমো ক্যাটালগ।'
                : 'চাল বাজার প্রো-এর নিত্যপণ্য, ডাল, তেল ও মসলার ডেমো ক্যাটালগ।',
        ]);
    }
}
