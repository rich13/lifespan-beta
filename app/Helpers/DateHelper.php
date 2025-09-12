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

    /**
     * Get the current date, respecting time travel mode if active
     * 
     * @return Carbon
     */
    public static function getCurrentDate()
    {
        // First, check if we're on a date exploration route - if so, use that date
        $route = request()->route();
        if ($route && $route->hasParameter('date')) {
            $routeName = $route->getName();
            if (in_array($routeName, ['date.explore', 'spans.at-date'])) {
                try {
                    $dateParam = $route->parameter('date');
                    // Parse the date parameter (could be YYYY, YYYY-MM, or YYYY-MM-DD)
                    $dateParts = explode('-', $dateParam);
                    $year = (int) $dateParts[0];
                    $month = isset($dateParts[1]) ? (int) $dateParts[1] : 1;
                    $day = isset($dateParts[2]) ? (int) $dateParts[2] : 1;
                    
                    return Carbon::createFromDate($year, $month, $day);
                } catch (\Exception $e) {
                    // If parsing fails, fall back to time travel or current date
                }
            }
        }
        
        // Check for time travel cookie
        $timeTravelDate = request()->cookie('time_travel_date');
        
        if ($timeTravelDate) {
            try {
                return Carbon::parse($timeTravelDate);
            } catch (\Exception $e) {
                // If parsing fails, fall back to current date
                return Carbon::now();
            }
        }
        
        return Carbon::now();
    }
}