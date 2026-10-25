<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin — Products</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<div class="max-w-6xl mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold mb-6 text-rice-800">Catalog Administration</h1>
  <nav class="flex gap-4 mb-8 text-sm font-medium text-slate-600">
    <a href="{{ route('admin.catalog.categories') }}" class="hover:text-rice-700">Categories</a>
    <a href="{{ route('admin.catalog.products') }}" class="hover:text-rice-700">Products</a>
    <a href="{{ route('admin.catalog.variants') }}" class="hover:text-rice-700">Variants & Stock</a>
  </nav>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold">Products</h2>
      <form method="get" class="flex gap-2"><input name="q" value="{{ request('q') }}" class="border rounded px-3 py-1 text-sm" placeholder="Search title..."><button class="bg-rice-800 text-white text-sm px-3 py-1 rounded">Search</button></form>
    </div>
    <table class="w-full text-sm mt-4 border-collapse">
      <thead class="bg-slate-100"><tr><th class="text-left px-3 py-2">Title</th><th class="text-left px-3 py-2">Category</th><th class="text-left px-3 py-2">Type</th><th class="text-left px-3 py-2">Active</th></tr></thead>
      <tbody>
      @foreach($products as $p)
        <tr class="border-t"><td class="px-3 py-2">{{ $p->title }}</td><td class="px-3 py-2">{{ $p->category?->name_bn ?? '-' }}</td><td class="px-3 py-2">{{ $p->type }}</td><td class="px-3 py-2">{{ $p->is_active ? 'Yes' : 'No' }}</td></tr>
      @endforeach
      </tbody>
    </table>
    <div class="mt-4">{{ $products->links() }}</div>
  </div>
</div>
</body>
</html>
