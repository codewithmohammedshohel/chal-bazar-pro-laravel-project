<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogAdminController extends Controller
{
    public function categories()
    {
        Gate::authorize('catalog.categories');

        $categories = Category::ordered()->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function products(Request $request)
    {
        Gate::authorize('catalog.products');

        $search = $request->get('q');
        $query = Product::query()->with('category');
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }
        $products = $query->orderBy('title')->paginate(20);
        return view('admin.products.index', compact('products', 'search'));
    }

    public function variants(Request $request)
    {
        Gate::authorize('inventory');

        $variants = ProductVariant::with('product.category')->paginate(30);
        return view('admin.variants.index', compact('variants'));
    }
}
