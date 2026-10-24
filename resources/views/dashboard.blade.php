@extends('layouts.app')

@section('content')
    <div class="bg-white p-6 rounded shadow">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="mt-2 text-gray-600">Welcome, {{ auth()->user()->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full bg-blue-50 px-3 py-1 font-medium text-blue-700">
                    Role: {{ auth()->user()->role }}
                </span>
                <span class="rounded-full bg-green-50 px-3 py-1 font-medium text-green-700">
                    Status: {{ auth()->user()->status }}
                </span>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Effective permissions</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse (\App\Support\Permissions::all() as $permission)
                    @if (auth()->user()->hasPermission($permission))
                        <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ $permission }}</span>
                    @endif
                @empty
                    <span class="text-sm text-gray-500">No permissions assigned.</span>
                @endforelse
            </div>
        </div>
    </div>
@endsection
