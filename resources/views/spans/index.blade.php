@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Spans</h1>
        @auth
            <a href="{{ route('spans.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Create New Span
            </a>
        @endauth
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($spans as $span)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('spans.show', $span) }}" class="text-blue-600 hover:text-blue-900">
                                {{ $span->name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100">
                                {{ $span->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($span->start_year)
                                {{ $span->start_year }}
                                @if ($span->start_month)
                                    -{{ str_pad($span->start_month, 2, '0', STR_PAD_LEFT) }}
                                    @if ($span->start_day)
                                        -{{ str_pad($span->start_day, 2, '0', STR_PAD_LEFT) }}
                                    @endif
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($span->end_year)
                                {{ $span->end_year }}
                                @if ($span->end_month)
                                    -{{ str_pad($span->end_month, 2, '0', STR_PAD_LEFT) }}
                                    @if ($span->end_day)
                                        -{{ str_pad($span->end_day, 2, '0', STR_PAD_LEFT) }}
                                    @endif
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('spans.show', $span) }}" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                            @auth
                                <a href="{{ route('spans.edit', $span) }}" class="text-green-600 hover:text-green-900">Edit</a>
                            @endauth
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            No spans found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $spans->links() }}
    </div>
</div>
@endsection 