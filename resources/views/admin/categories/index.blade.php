<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin — Catalog</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="max-w-6xl mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold mb-6 text-rice-800">Catalog Administration</h1>
  <nav class="flex gap-4 mb-8 text-sm font-medium text-slate-600">
    <a href="{{ route('admin.catalog.categories') }}" class="hover:text-rice-700">Categories</a>
    <a href="{{ route('admin.catalog.products') }}" class="hover:text-rice-700">Products</a>
    <a href="{{ route('admin.catalog.variants') }}" class="hover:text-rice-700">Variants & Stock</a>
  </nav>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h2 class="text-xl font-semibold mb-4">Categories</h2>
    <p class="text-sm text-slate-500">Active categories with slug, sort order and Bangla/English labels.</p>
    <table class="w-full text-sm mt-4 border-collapse">
      <thead class="bg-slate-100"><tr><th class="text-left px-3 py-2">Name (BN)</th><th class="text-left px-3 py-2">Slug</th><th class="text-left px-3 py-2">Active</th></tr></thead>
      <tbody>
      @foreach($categories as $c)
        <tr class="border-t"><td class="px-3 py-2">{{ $c->name_bn ?? '-' }}</td><td class="px-3 py-2 font-mono text-xs">{{ $c->slug }}</td><td class="px-3 py-2">{{ $c->is_active ? 'Yes' : 'No' }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
