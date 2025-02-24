@props(['span'])

<div class="span-actions d-flex gap-2">
    <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary">View</a>
    @can('update', $span)
        <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
    @endcan
    @can('delete', $span)
        <form action="{{ route('spans.destroy', $span) }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" 
                    onclick="return confirm('Are you sure you want to delete this span?')">
                Delete
            </button>
        </form>
    @endcan
</div> 