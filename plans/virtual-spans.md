# Virtual Spans System

## Overview

Virtual spans are dynamically created span objects that don't exist in the database but can be used throughout the system as if they were real spans. This allows for temporal exploration, period-based analysis, and other scenarios where we need span-like objects with specific temporal boundaries.

## Use Cases

1. **Date Exploration** - Virtual spans representing time periods (e.g., "2001", "February 2001", "14 February 2001")
2. **Time Period Comparisons** - Comparing activity across different time periods
3. **Historical Analysis** - Creating spans for historical eras or periods
4. **Temporal Queries** - Any scenario where we need a span-like object with specific start/end dates

## Design

### VirtualSpan Class

```php
class VirtualSpan
{
    // Core properties that match Span model
    public string $id;
    public string $name;
    public string $type_id;
    public ?int $start_year;
    public ?int $start_month;
    public ?int $start_day;
    public ?int $end_year;
    public ?int $end_month;
    public ?int $end_day;
    public string $start_precision;
    public string $end_precision;
    public array $metadata;
    
    // Virtual span specific properties
    public bool $is_virtual = true;
    public string $virtual_type; // e.g., 'date_period', 'historical_era'
    
    // Methods that match Span model interface
    public function connectionsAsSubject(): Collection
    public function connectionsAsObject(): Collection
    public function connections(): Collection
    public function isPublic(): bool
    public function hasPermission(User $user, string $permission): bool
    // ... other required methods
}
```

### VirtualSpanFactory

```php
class VirtualSpanFactory
{
    public static function createDatePeriod(int $year, ?int $month = null, ?int $day = null): VirtualSpan
    {
        $span = new VirtualSpan();
        $span->id = self::generateDatePeriodId($year, $month, $day);
        $span->name = self::generateDatePeriodName($year, $month, $day);
        $span->type_id = 'event';
        $span->virtual_type = 'date_period';
        
        // Set temporal boundaries
        $span->start_year = $year;
        $span->start_month = $month ?? 1;
        $span->start_day = $day ?? 1;
        $span->end_year = $year;
        $span->end_month = $month ?? 12;
        $span->end_day = $day ?? 31;
        
        // Set precision
        $span->start_precision = $day ? 'day' : ($month ? 'month' : 'year');
        $span->end_precision = $span->start_precision;
        
        return $span;
    }
    
    private static function generateDatePeriodId(int $year, ?int $month, ?int $day): string
    {
        $parts = ['date-period', $year];
        if ($month) $parts[] = str_pad($month, 2, '0', STR_PAD_LEFT);
        if ($day) $parts[] = str_pad($day, 2, '0', STR_PAD_LEFT);
        return implode('-', $parts);
    }
    
    private static function generateDatePeriodName(int $year, ?int $month, ?int $day): string
    {
        if ($day && $month) {
            return Carbon::createFromDate($year, $month, $day)->format('j F Y');
        } elseif ($month) {
            return Carbon::createFromDate($year, $month, 1)->format('F Y');
        } else {
            return $year . ' Timeline';
        }
    }
}
```

## Implementation Steps

### 1. Create VirtualSpan Class

- Create `app/Models/VirtualSpan.php`
- Implement all required methods from Span interface
- Handle connections by querying the database for spans that overlap with the virtual span's temporal boundaries

### 2. Update Route Model Binding

- Modify `Span::resolveRouteBinding()` to detect virtual span IDs
- Return VirtualSpan instances for virtual span patterns
- Handle both UUID and virtual span ID patterns

### 3. Update Timeline Controller

- Modify `SpanSearchController::timeline()` to handle VirtualSpan instances
- Query for spans that overlap with the virtual span's temporal boundaries
- Return appropriate timeline data

### 4. Create Virtual Span Factory

- Create `app/Services/VirtualSpanFactory.php`
- Implement factory methods for different virtual span types
- Handle ID generation and naming conventions

### 5. Update Components

- Ensure timeline component works with VirtualSpan instances
- Update any components that expect real Span instances

## Database Queries for Virtual Spans

### Finding Spans in a Date Period

```php
// For a year period
$spans = Span::where(function($query) use ($year) {
    $query->where('start_year', '<=', $year)
          ->where(function($q) use ($year) {
              $q->whereNull('end_year')
                ->orWhere('end_year', '>=', $year);
          });
})->get();

// For a month period
$spans = Span::where(function($query) use ($year, $month) {
    $query->where(function($q) use ($year, $month) {
        $q->where('start_year', '<', $year)
          ->orWhere(function($q2) use ($year, $month) {
              $q2->where('start_year', '=', $year)
                 ->where('start_month', '<=', $month);
          });
    })->where(function($q) use ($year, $month) {
        $q->whereNull('end_year')
          ->orWhere('end_year', '>', $year)
          ->orWhere(function($q2) use ($year, $month) {
              $q2->where('end_year', '=', $year)
                 ->where('end_month', '>=', $month);
          });
    });
})->get();
```

## Benefits

1. **Reusable** - Virtual spans can be used anywhere in the system
2. **Consistent** - Same interface as real spans
3. **Extensible** - Easy to add new types of virtual spans
4. **Clean** - No special case handling in components
5. **Testable** - Virtual spans can be unit tested independently

## Future Enhancements

1. **Caching** - Cache virtual span instances to avoid recreation
2. **More Virtual Types** - Historical eras, seasons, etc.
3. **Virtual Connections** - Allow virtual spans to have virtual connections
4. **Temporal Queries** - Advanced temporal querying capabilities

## Migration Strategy

1. Start with date period virtual spans for the date explorer
2. Gradually expand to other use cases
3. Update components incrementally
4. Add comprehensive tests
5. Document usage patterns 