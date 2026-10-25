<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin — Variants & Stock</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="max-w-6xl mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold mb-6 text-rice-800">Catalog Administration</h1>
  <nav class="flex gap-4 mb-8 text-sm font-medium text-slate-600">
    <a href="{{ route('admin.catalog.categories') }}" class="hover:text-rice-700">Categories</a>
    <a href="{{ route('admin.catalog.products') }}" class="hover:text-rice-700">Products</a>
    <a href="{{ route('admin.catalog.variants') }}" class="hover:text-rice-700">Variants & Stock</a>
  </nav>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h2 class="text-xl font-semibold mb-4">Product Variants & Stock Ledger</h2>
    <p class="text-sm text-slate-500 mb-4">Foundation schema: stock_levels, stock_reservations, stock_movements.</p>
    <table class="w-full text-sm mt-4 border-collapse">
      <thead class="bg-slate-100"><tr><th class="text-left px-3 py-2">Product</th><th class="text-left px-3 py-2">Unit</th><th class="text-left px-3 py-2">Retail Price</th><th class="text-left px-3 py-2">Weight (kg)</th></tr></thead>
      <tbody>
      @foreach($variants as $v)
        <tr class="border-t"><td class="px-3 py-2">{{ $v->product?->title ?? '-' }}</td><td class="px-3 py-2">{{ $v->unit }}</td><td class="px-3 py-2">৳{{ number_format($v->retail_price, 2) }}</td><td class="px-3 py-2">{{ $v->weight_kg }}</td></tr>
      @endforeach
      </tbody>
    </table>
    <div class="mt-4">{{ $variants->links() }}</div>
  </div>
</div>
</body>
</html>
