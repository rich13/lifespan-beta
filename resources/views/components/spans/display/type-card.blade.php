@props(['spanType', 'subtypeStats', 'noSubtypeKey'])

@php
    $totalCount = $subtypeStats->sum('count');
@endphp

<div class="card h-100">
    <div class="card-header d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-{{ $spanType->type_id }} disabled" style="min-width: 40px;">
            <x-icon type="{{ $spanType->type_id }}" category="span" />
        </button>
        <h5 class="card-title mb-0">
            <a href="{{ route('spans.types.show', $spanType->type_id) }}" class="text-decoration-none">
                {{ $spanType->name }}
            </a>
        </h5>
    </div>
    
    <div class="card-body">
        @if($spanType->description)
            <p class="card-text text-muted small mb-3">{{ $spanType->description }}</p>
        @endif
        
        @if($totalCount > 0)
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="text-muted small fw-normal">Subtype</th>
                            <th scope="col" class="text-muted small fw-normal text-end">Spans</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subtypeStats as $row)
                            <tr>
                                <td class="align-middle">
                                    @if($row->subtype_key === $noSubtypeKey)
                                        <span class="text-muted">No subtype</span>
                                    @else
                                        <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $row->subtype_key]) }}" class="text-decoration-none">
                                            {{ ucwords(str_replace('_', ' ', $row->subtype_key)) }}
                                        </a>
                                    @endif
                                </td>
                                <td class="align-middle text-end">
                                    @if($row->subtype_key === $noSubtypeKey)
                                        <span class="badge bg-secondary">{{ number_format($row->count) }}</span>
                                    @else
                                        <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $row->subtype_key]) }}" class="text-decoration-none">
                                            <span class="badge bg-{{ $spanType->type_id }}">{{ number_format($row->count) }}</span>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" class="small">Total</th>
                            <td class="text-end"><strong>{{ number_format($totalCount) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <p class="text-muted text-center my-3">
                <x-icon type="view" category="action" />
                No {{ strtolower($spanType->name) }} spans found
            </p>
        @endif
    </div>
</div>
