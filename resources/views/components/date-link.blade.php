@props([
    'year',
    'month' => null,
    'day' => null,
    'date' => null, // Alternative: pass a Y-m-d format string
    'class' => 'text-muted text-dotted-underline'
])

@php
    // If date string is provided, parse it
    if ($date) {
        $dateParts = explode('-', $date);
        $year = (int) $dateParts[0];
        $month = isset($dateParts[1]) ? (int) $dateParts[1] : null;
        $day = isset($dateParts[2]) ? (int) $dateParts[2] : null;
    }
    
    // Format the date for display
    $formattedDate = \App\Helpers\DateHelper::formatDate($year, $month, $day);
    
    // Build the date link
    if ($day && $month) {
        $dateLink = sprintf('%04d-%02d-%02d', $year, $month, $day);
    } elseif ($month) {
        $dateLink = sprintf('%04d-%02d', $year, $month);
    } else {
        $dateLink = (string) $year;
    }
    
    // Check if date is in the future
    $now = \Carbon\Carbon::now();
    $isFuture = false;
    
    if ($day && $month) {
        $dateCarbon = \Carbon\Carbon::createFromDate($year, $month, $day);
        $isFuture = $dateCarbon->gt($now);
    } elseif ($month) {
        $dateCarbon = \Carbon\Carbon::createFromDate($year, $month, 1);
        $isFuture = $dateCarbon->gt($now);
    } else {
        $isFuture = $year > $now->year;
    }
    
    // Build class string - remove text-muted for future dates and add text-success
    $linkClass = $class;
    if ($isFuture) {
        // Remove text-muted if present
        $linkClass = str_replace('text-muted', '', $linkClass);
        // Add text-success
        $linkClass = trim($linkClass) . ' text-success';
        // Clean up any double spaces
        $linkClass = preg_replace('/\s+/', ' ', trim($linkClass));
    }
@endphp

<a href="{{ route('date.explore', ['date' => $dateLink]) }}" class="{{ $linkClass }}">
    {{ $formattedDate }}
</a>

