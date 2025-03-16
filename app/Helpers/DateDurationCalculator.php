<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateDurationCalculator
{
    public static function calculateDuration($start, $end)
    {
        $start = Carbon::create($start->year, $start->month ?: 1, $start->day ?: 1);
        $end = Carbon::create($end->year, $end->month ?: 1, $end->day ?: 1);
        
        $years = $end->diffInYears($start);
        $months = $end->copy()->subYears($years)->diffInMonths($start);
        $days = $end->copy()->subYears($years)->subMonths($months)->diffInDays($start);
        
        return compact('years', 'months', 'days');
    }
} 