<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateDurationCalculator
{
    public static function calculateDuration($start, $end)
    {
        $start = Carbon::create($start->year, $start->month ?: 1, $start->day ?: 1);
        $end = Carbon::create($end->year, $end->month ?: 1, $end->day ?: 1);
        
        // Use diff() to get complete years (rounded down), not diffInYears() which might round
        $diff = $end->diff($start);
        $years = $diff->y; // Complete years (always rounded down)
        $months = $diff->m; // Complete months after subtracting years
        $days = $diff->d; // Complete days after subtracting years and months
        
        return compact('years', 'months', 'days');
    }
} 