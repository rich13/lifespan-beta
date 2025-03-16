<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DateHelper
{
    public static function formatDate($year, $month = null, $day = null, $precision = 'day')
    {
        if ($precision === 'year' || !$month) {
            return $year;
        }
        
        if ($precision === 'month' || !$day) {
            return Carbon::createFromDate($year, $month)->format('F Y');
        }
        
        return Carbon::createFromDate($year, $month, $day)->format('F j, Y');
    }

    public static function calculateAge($startYear, $endYear = null)
    {
        $endYear = $endYear ?? Carbon::now()->year;
        return $endYear - $startYear;
    }

    public static function formatDateRange($startYear, $startMonth = null, $startDay = null, 
                                         $endYear = null, $endMonth = null, $endDay = null,
                                         $startPrecision = 'day', $endPrecision = 'day')
    {
        $start = self::formatDate($startYear, $startMonth, $startDay, $startPrecision);
        
        if (!$endYear) {
            return $start . ' - Present';
        }
        
        $end = self::formatDate($endYear, $endMonth, $endDay, $endPrecision);
        return $start . ' - ' . $end;
    }

    public static function isValidDate($year, $month = null, $day = null)
    {
        if (!is_numeric($year) || $year < 0) {
            return false;
        }
        
        if ($month !== null) {
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                return false;
            }
            
            if ($day !== null) {
                return checkdate($month, $day, $year);
            }
        }
        
        return true;
    }

    public static function currentYear()
    {
        return Carbon::now()->year;
    }

    public static function formatDuration($duration)
    {
        $parts = [];
        if ($duration['years'] > 0) {
            $parts[] = $duration['years'] . ' ' . Str::plural('year', $duration['years']);
        }
        if ($duration['months'] > 0) {
            $parts[] = $duration['months'] . ' ' . Str::plural('month', $duration['months']);
        }
        if ($duration['days'] > 0) {
            $parts[] = $duration['days'] . ' ' . Str::plural('day', $duration['days']);
        }
        return implode(', ', $parts);
    }
}