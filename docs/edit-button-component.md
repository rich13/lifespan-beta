# Edit Button Component

This document describes the new edit button components that automatically check if the current user can edit a model and display an edit button when appropriate.

## Components Available

### 1. Standalone Edit Button (`<x-edit-button>`)

A simple edit button that can be placed anywhere in your templates.

**Basic Usage:**
```blade
<x-edit-button :model="$span" />
```

**With Custom Options:**
```blade
<x-edit-button 
    :model="$connection"
    route="admin.connections.edit"
    :route-params="['connection' => $connection]"
    size="sm"
    position="absolute"
    position-classes="top-0 end-0"
    icon="bi-pencil-square"
    tooltip="Edit Connection"
    :show-text="true"
    text="Edit"
/>
```

### 2. Interactive Card with Edit Button (`<x-shared.interactive-card>`)

A complete interactive card component that includes an edit button.

**Basic Usage:**
```blade
<x-shared.interactive-card :model="$span">
    <div class="card-body">
        <h5 class="card-title">{{ $span->name }}</h5>
        <p class="card-text">{{ $span->description }}</p>
    </div>
</x-shared.interactive-card>
```

**With Custom Options:**
```blade
<x-shared.interactive-card 
    :model="$connection"
    :route="'admin.connections.edit'"
    :route-params="['connection' => $connection]"
    edit-button-position="top-left"
    edit-button-size="sm"
    edit-button-icon="bi-pencil-square"
    edit-button-tooltip="Edit Connection"
    class="my-custom-class"
    :hover="true"
    :clickable="true"
    click-route="connections.show"
    :click-route-params="['connection' => $connection]"
>
    <div class="card-body">
        <h5 class="card-title">{{ $connection->type->name }}</h5>
        <p class="card-text">{{ $connection->connectionSpan->description }}</p>
    </div>
</x-shared.interactive-card>
```

### 3. Shared Edit Button (`<x-shared.edit-button>`)

A shared component used internally by the interactive card.

## Supported Models

The components automatically detect and handle the following model types:

### Spans
- Uses Laravel's policy system (`auth()->user()->can('update', $span)`)
- Automatically routes to `spans.edit`

### Connections  
- Uses the `isEditableBy()` method from the `HasRelationshipAccess` trait
- Automatically routes to `admin.connections.edit`

### Other Models
- Falls back to checking if user is admin or if model has an `owner_id` field

## Properties

### Edit Button Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `model` | Model | null | The model to check editability for |
| `route` | string | null | Custom route name (auto-detected if not provided) |
| `routeParams` | array | [] | Route parameters |
| `size` | string | 'sm' | Button size (xs, sm, lg) |
| `class` | string | '' | Additional CSS classes |
| `icon` | string | 'bi-pencil' | Bootstrap icon class |
| `tooltip` | string | 'Edit' | Tooltip text |
| `position` | string | 'absolute' | Position type (absolute, relative, static) |
| `positionClasses` | string | 'top-0 end-0' | Bootstrap position classes |
| `showText` | boolean | false | Whether to show text with icon |
| `text` | string | 'Edit' | Text to show when showText is true |

### Interactive Card Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `model` | Model | null | The model to check editability for |
| `route` | string | null | Edit route name |
| `routeParams` | array | [] | Edit route parameters |
| `editButtonPosition` | string | 'top-right' | Edit button position |
| `editButtonSize` | string | 'sm' | Edit button size |
| `editButtonClass` | string | '' | Edit button CSS classes |
| `editButtonIcon` | string | 'bi-pencil' | Edit button icon |
| `editButtonTooltip` | string | 'Edit' | Edit button tooltip |
| `class` | string | '' | Additional card CSS classes |
| `hover` | boolean | true | Enable hover effects |
| `clickable` | boolean | false | Make entire card clickable |
| `clickRoute` | string | null | Route for card click |
| `clickRouteParams` | array | [] | Parameters for card click route |

## Examples

### Example 1: Simple Span Card with Edit Button

```blade
<div class="card position-relative">
    <x-edit-button :model="$span" />
    <div class="card-body">
        <h5 class="card-title">{{ $span->name }}</h5>
        <p class="card-text">{{ $span->description }}</p>
    </div>
</div>
```

### Example 2: Interactive Card for Connection

```blade
<x-shared.interactive-card 
    :model="$connection"
    :clickable="true"
    click-route="connections.show"
    :click-route-params="['connection' => $connection]"
>
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-1">
            <x-connections.micro-card :connection="$connection" />
            <x-spans.partials.date-range :span="$connection->connectionSpan" />
        </div>
        
        @if($connection->connectionSpan->description)
            <p class="card-text">{{ Str::limit($connection->connectionSpan->description, 150) }}</p>
        @endif
    </div>
</x-shared.interactive-card>
```

### Example 3: Custom Edit Button with Text

```blade
<div class="my-custom-container position-relative">
    <x-edit-button 
        :model="$span"
        size="lg"
        icon="bi-pencil-square"
        :show-text="true"
        text="Edit Span"
        tooltip="Click to edit this span"
        position-classes="top-0 start-0"
    />
    
    <!-- Your content here -->
</div>
```

## Integration with Existing Components

### Updating Connection Card

Replace the existing connection card with the new interactive card:

```blade
{{-- Old version --}}
<div class="card mb-3 connection-card">
    <div class="card-body">
        <!-- content -->
    </div>
</div>

{{-- New version with edit button --}}
<x-shared.interactive-card :model="$connection" class="mb-3 connection-card">
    <div class="card-body">
        <!-- same content -->
    </div>
</x-shared.interactive-card>
```

### Updating Span Cards

Add edit buttons to existing span cards:

```blade
{{-- Add to existing span cards --}}
<div class="card mb-2 span-card position-relative">
    <x-edit-button :model="$span" />
    <div class="card-body px-3 py-2">
        <!-- existing content -->
    </div>
</div>
```

## Styling

The components use Bootstrap classes and include:

- Responsive positioning
- Hover effects
- Tooltips
- Z-index management for proper layering
- Smooth transitions

The edit button automatically positions itself and doesn't interfere with card interactions when the card is clickable.

## Security

The components automatically check permissions:

- **Spans**: Uses Laravel policies
- **Connections**: Uses the `isEditableBy()` method
- **Other models**: Falls back to admin check or ownership check

The edit button only appears when the user has permission to edit the model. 