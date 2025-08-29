# Icon Component Usage Guide

## Overview

The `<x-icon>` component provides a centralized way to display icons throughout the application. It supports both manual type/category specification and automatic detection from span/connection objects.

## Usage Patterns

### 1. **Automatic Detection (Recommended)**

#### For Spans:
```blade
<!-- Automatically shows subtype icon for things (e.g., album → disc, track → music-note-beamed) -->
<x-icon :span="$span" />

<!-- Force span type icon even for things -->
<x-icon :span="$span" :preferSubtype="false" />
```

#### For Connections:
```blade
<!-- Automatically shows connection type icon -->
<x-icon :connection="$connection" />

<!-- Show specific spans from connections -->
<x-icon :parent="$connection->parent" /> <!-- Shows parent span icon -->
<x-icon :child="$connection->child" />   <!-- Shows child span icon -->
```

### 2. **Manual Specification (Legacy)**

#### Span Types:
```blade
<x-icon type="person" category="span" />
<x-icon type="organisation" category="span" />
<x-icon type="place" category="span" />
<x-icon type="event" category="span" />
<x-icon type="band" category="span" />
<x-icon type="thing" category="span" />
```

#### Thing Subtypes:
```blade
<x-icon type="track" category="subtype" />
<x-icon type="album" category="subtype" />
<x-icon type="photo" category="subtype" />
<x-icon type="film" category="subtype" />
<x-icon type="book" category="subtype" />
<x-icon type="sculpture" category="subtype" />
<x-icon type="painting" category="subtype" />
<x-icon type="performance" category="subtype" />
<x-icon type="video" category="subtype" />
<x-icon type="article" category="subtype" />
<x-icon type="paper" category="subtype" />
<x-icon type="product" category="subtype" />
<x-icon type="vehicle" category="subtype" />
<x-icon type="tool" category="subtype" />
<x-icon type="device" category="subtype" />
<x-icon type="artifact" category="subtype" />
<x-icon type="plaque" category="subtype" />
<x-icon type="other" category="subtype" />
```

#### Connection Types:
```blade
<x-icon type="education" category="connection" />
<x-icon type="employment" category="connection" />
<x-icon type="member_of" category="connection" />
<x-icon type="residence" category="connection" />
<x-icon type="family" category="connection" />
<x-icon type="friend" category="connection" />
<x-icon type="relationship" category="connection" />
<x-icon type="created" category="connection" />
<x-icon type="contains" category="connection" />
<x-icon type="travel" category="connection" />
<x-icon type="participation" category="connection" />
<x-icon type="ownership" category="connection" />
<x-icon type="has_role" category="connection" />
<x-icon type="at_organisation" category="connection" />
<x-icon type="features" category="connection" />
<x-icon type="located" category="connection" />
```

#### Status Icons:
```blade
<x-icon type="public" category="status" />
<x-icon type="private" category="status" />
<x-icon type="shared" category="status" />
<x-icon type="placeholder" category="status" />
<x-icon type="draft" category="status" />
<x-icon type="complete" category="status" />
```

#### Action Icons:
```blade
<x-icon type="add" category="action" />
<x-icon type="edit" category="action" />
<x-icon type="delete" category="action" />
<x-icon type="view" category="action" />
<x-icon type="search" category="action" />
<x-icon type="import" category="action" />
<x-icon type="export" category="action" />
```

### 3. **Additional Options**

#### Size Classes:
```blade
<x-icon :span="$span" size="fs-4" />
<x-icon :span="$span" size="fs-5" />
```

#### Custom Classes:
```blade
<x-icon :span="$span" class="me-1" />
<x-icon :span="$span" class="text-primary" />
```

## Migration Guide

### Before (Manual):
```blade
<x-icon type="{{ $span->type_id }}" category="span" />
```

### After (Automatic):
```blade
<x-icon :span="$span" />
```

### Before (Manual with connection spans):
```blade
<x-icon type="{{ $connection->parent->type_id }}" category="span" />
<x-icon type="{{ $connection->child->type_id }}" category="span" />
```

### After (Automatic):
```blade
<x-icon :parent="$connection->parent" />
<x-icon :child="$connection->child" />
```

### Before (Manual with subtype):
```blade
@if($span->type_id === 'thing' && isset($span->metadata['subtype']))
    <x-icon type="{{ $span->metadata['subtype'] }}" category="subtype" />
@else
    <x-icon type="{{ $span->type_id }}" category="span" />
@endif
```

### After (Automatic):
```blade
<x-icon :span="$span" />
```

## Benefits

1. **Centralized Logic**: All icon selection logic is in one place
2. **Automatic Subtype Detection**: Thing spans automatically show subtype icons
3. **Consistent Icons**: Same icons used everywhere
4. **Easy Maintenance**: Adding new icons only requires updating the component
5. **Cleaner Templates**: Less conditional logic in templates
6. **Type Safety**: IDE can provide better autocomplete and error detection

## Examples in Context

### Interactive Cards:
```blade
<button type="button" class="btn btn-outline-{{ $span->type_id }} disabled">
    <x-icon :span="$span" />
</button>
```

### Lists:
```blade
@foreach($spans as $span)
    <div class="d-flex align-items-center">
        <x-icon :span="$span" class="me-2" />
        <span>{{ $span->name }}</span>
    </div>
@endforeach
```

### Search Results:
```blade
@foreach($results as $result)
    <div class="search-result">
        <x-icon :span="$result" class="me-2" />
        <a href="{{ route('spans.show', $result) }}">{{ $result->name }}</a>
    </div>
@endforeach
```

### Connection Details:
```blade
<!-- Show connection type icon -->
<x-icon :connection="$connection" />

<!-- Show parent span icon (e.g., person) -->
<x-icon :parent="$connection->parent" />

<!-- Show child span icon (e.g., organisation) -->
<x-icon :child="$connection->child" />
```
