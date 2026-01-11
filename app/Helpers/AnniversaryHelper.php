<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Span;

class AnniversaryHelper
{
    /**
     * Calculate significance score for an anniversary
     * Higher scores indicate more significant milestones
     * Milestones: 1st, then 5th, 10th, 20th, 30th, 40th, 50th, etc.
     */
    public static function calculateSignificance(int $years): int
    {
        // 1st anniversary is significant
        if ($years === 1) return 100;
        
        // 5th anniversary is significant
        if ($years === 5) return 100;
        
        // Every 10 years starting from 10th (10, 20, 30, 40, 50, etc.)
        if ($years >= 10 && $years % 10 === 0) {
            // Higher scores for major milestones
            if ($years % 100 === 0) return 1000; // 100th, 200th, etc.
            if ($years % 50 === 0) return 500;   // 50th, 150th, etc.
            return 100; // 10th, 20th, 30th, 40th, 60th, 70th, 80th, 90th, etc.
        }
        
        return 10; // Regular anniversaries
    }
    
    /**
     * Get upcoming anniversaries within a date range
     * 
     * @param Carbon|null $targetDate The target date (defaults to current date)
     * @param int $daysAhead Number of days ahead to look (default 30)
     * @param array $options Options to filter results
     * @return array Array of anniversary data
     */
    public static function getUpcomingAnniversaries(
        ?Carbon $targetDate = null,
        int $daysAhead = 30,
        array $options = []
    ): array {
        $targetDate = $targetDate ?? DateHelper::getCurrentDate();
        $startDateForQuery = $targetDate->copy();
        $endDateForQuery = $targetDate->copy()->addDays($daysAhead);
        
        $includeBirthdays = $options['include_birthdays'] ?? true;
        $includeDeathAnniversaries = $options['include_death_anniversaries'] ?? true;
        $includeAlbumAnniversaries = $options['include_album_anniversaries'] ?? true;
        $minSignificance = $options['min_significance'] ?? null;
        $maxDaysUntil = $options['max_days_until'] ?? null;
        $requirePhoto = $options['require_photo'] ?? false;
        
        $significantDates = [];
        
        // Get people with birthdays in the next X days (living people only)
        if ($includeBirthdays) {
            $birthdaySpans = Span::where('type_id', 'person')
                ->where(function($query) {
                    $query->where('access_level', 'public')
                        ->orWhere('owner_id', auth()->id());
                })
                ->whereNotNull('start_year')
                ->whereNotNull('start_month')
                ->whereNotNull('start_day')
                ->where('start_year', '<=', $targetDate->year)
                ->whereNull('end_year'); // Only living people
            
            if ($requirePhoto) {
                $birthdaySpans->whereHas('connectionsAsObject', function($query) {
                    $query->where('type_id', 'features')
                          ->whereHas('parent', function($q) {
                              $q->where('type_id', 'thing')
                                ->whereJsonContains('metadata->subtype', 'photo');
                          });
                });
            }
            
            $birthdaySpans = $birthdaySpans->get()
                ->filter(function($span) use ($targetDate, $startDateForQuery, $endDateForQuery) {
                    try {
                        $thisYearsBirthday = Carbon::createFromDate(
                            $targetDate->year,
                            $span->start_month,
                            $span->start_day
                        );
                        
                        $birthdayDate = $thisYearsBirthday->copy();
                        if ($thisYearsBirthday->lt($targetDate)) {
                            $birthdayDate->addYear();
                        }
                        
                        return $birthdayDate->gte($startDateForQuery) && 
                               $birthdayDate->lte($endDateForQuery);
                    } catch (\Exception $e) {
                        return false;
                    }
                });
            
            foreach ($birthdaySpans as $span) {
                try {
                    $thisYearsBirthday = Carbon::createFromDate(
                        $targetDate->year,
                        $span->start_month,
                        $span->start_day
                    );
                    
                    $nextBirthday = $thisYearsBirthday->copy();
                    if ($thisYearsBirthday->lt($targetDate)) {
                        $nextBirthday->addYear();
                    }
                    
                    $ageAtNextBirthday = $nextBirthday->year - $span->start_year;
                    $daysUntilBirthday = $targetDate->diffInDays($nextBirthday);
                    
                    if ($daysUntilBirthday <= $daysAhead && $daysUntilBirthday >= 0) {
                        $significance = self::calculateSignificance($ageAtNextBirthday);
                        
                        if ($minSignificance === null || $significance >= $minSignificance) {
                            if ($maxDaysUntil === null || $daysUntilBirthday <= $maxDaysUntil) {
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'birthday',
                                    'date' => $nextBirthday,
                                    'age' => $ageAtNextBirthday,
                                    'years' => $ageAtNextBirthday,
                                    'days_until' => $daysUntilBirthday,
                                    'significance' => $significance
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // Get people with death anniversaries in the next X days
        if ($includeDeathAnniversaries) {
            $deathAnniversarySpans = Span::where('type_id', 'person')
                ->where(function($query) {
                    $query->where('access_level', 'public')
                        ->orWhere('owner_id', auth()->id());
                })
                ->whereNotNull('end_year')
                ->whereNotNull('end_month')
                ->whereNotNull('end_day')
                ->where('end_year', '<=', $targetDate->year);
            
            if ($requirePhoto) {
                $deathAnniversarySpans->whereHas('connectionsAsObject', function($query) {
                    $query->where('type_id', 'features')
                          ->whereHas('parent', function($q) {
                              $q->where('type_id', 'thing')
                                ->whereJsonContains('metadata->subtype', 'photo');
                          });
                });
            }
            
            $deathAnniversarySpans = $deathAnniversarySpans->get()
                ->filter(function($span) use ($targetDate, $startDateForQuery, $endDateForQuery) {
                    try {
                        $thisYearsAnniversary = Carbon::createFromDate(
                            $targetDate->year,
                            $span->end_month,
                            $span->end_day
                        );
                        
                        $anniversaryDate = $thisYearsAnniversary->copy();
                        if ($thisYearsAnniversary->lt($targetDate)) {
                            $anniversaryDate->addYear();
                        }
                        
                        return $anniversaryDate->gte($startDateForQuery) && 
                               $anniversaryDate->lte($endDateForQuery);
                    } catch (\Exception $e) {
                        return false;
                    }
                });
            
            foreach ($deathAnniversarySpans as $span) {
                try {
                    $thisYearsAnniversary = Carbon::createFromDate(
                        $targetDate->year,
                        $span->end_month,
                        $span->end_day
                    );
                    
                    $nextAnniversary = $thisYearsAnniversary->copy();
                    if ($thisYearsAnniversary->lt($targetDate)) {
                        $nextAnniversary->addYear();
                    }
                    
                    $yearsSinceDeath = $nextAnniversary->year - $span->end_year;
                    $daysUntilAnniversary = $targetDate->diffInDays($nextAnniversary);
                    $significance = self::calculateSignificance($yearsSinceDeath);
                    
                    if ($daysUntilAnniversary <= $daysAhead && $daysUntilAnniversary >= 0) {
                        if ($minSignificance === null || $significance >= $minSignificance) {
                            if ($maxDaysUntil === null || $daysUntilAnniversary <= $maxDaysUntil) {
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'death_anniversary',
                                    'date' => $nextAnniversary,
                                    'years' => $yearsSinceDeath,
                                    'days_until' => $daysUntilAnniversary,
                                    'significance' => $significance
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // Get albums with release anniversaries in the next X days
        if ($includeAlbumAnniversaries) {
            $albumAnniversarySpans = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'album')
                ->where(function($query) {
                    $query->where('access_level', 'public')
                        ->orWhere('owner_id', auth()->id());
                })
                ->whereNotNull('start_year')
                ->whereNotNull('start_month')
                ->whereNotNull('start_day')
                ->where('start_year', '<=', $targetDate->year)
                ->get()
                ->filter(function($span) use ($targetDate, $startDateForQuery, $endDateForQuery) {
                    try {
                        $thisYearsAnniversary = Carbon::createFromDate(
                            $targetDate->year,
                            $span->start_month,
                            $span->start_day
                        );
                        
                        $anniversaryDate = $thisYearsAnniversary->copy();
                        if ($thisYearsAnniversary->lt($targetDate)) {
                            $anniversaryDate->addYear();
                        }
                        
                        return $anniversaryDate->gte($startDateForQuery) && 
                               $anniversaryDate->lte($endDateForQuery);
                    } catch (\Exception $e) {
                        return false;
                    }
                });
            
            foreach ($albumAnniversarySpans as $span) {
                try {
                    $thisYearsAnniversary = Carbon::createFromDate(
                        $targetDate->year,
                        $span->start_month,
                        $span->start_day
                    );
                    
                    $nextAnniversary = $thisYearsAnniversary->copy();
                    if ($thisYearsAnniversary->lt($targetDate)) {
                        $nextAnniversary->addYear();
                    }
                    
                    $yearsSinceRelease = $nextAnniversary->year - $span->start_year;
                    $daysUntilAnniversary = $targetDate->diffInDays($nextAnniversary);
                    $significance = self::calculateSignificance($yearsSinceRelease);
                    
                    if ($daysUntilAnniversary <= $daysAhead && $daysUntilAnniversary >= 0) {
                        if ($minSignificance === null || $significance >= $minSignificance) {
                            if ($maxDaysUntil === null || $daysUntilAnniversary <= $maxDaysUntil) {
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'album_anniversary',
                                    'date' => $nextAnniversary,
                                    'years' => $yearsSinceRelease,
                                    'days_until' => $daysUntilAnniversary,
                                    'significance' => $significance
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // Sort by: 1) Is today (today first), 2) Significance (highest first), 3) Days until (soonest first)
        usort($significantDates, function($a, $b) {
            // First priority: events happening TODAY (days_until === 0) come first
            $aIsToday = $a['days_until'] === 0;
            $bIsToday = $b['days_until'] === 0;
            if ($aIsToday !== $bIsToday) {
                return $bIsToday <=> $aIsToday; // true (1) comes before false (0)
            }
            
            // Second priority: significance (higher is better)
            $significanceCompare = $b['significance'] <=> $a['significance'];
            if ($significanceCompare !== 0) {
                return $significanceCompare;
            }
            
            // Third priority: days until (sooner is better)
            return $a['days_until'] <=> $b['days_until'];
        });
        
        return $significantDates;
    }
    
    /**
     * Get the highest-scoring person from upcoming anniversaries
     * Only returns a person if there's a clear winner
     * Checks for photos after determining significance (so same list as anniversaries component)
     * 
     * @param Carbon|null $targetDate The target date
     * @param int $maxDaysUntil Maximum days until anniversary (default 7)
     * @param int $minSignificance Minimum significance score (default 50)
     * @return Span|null The highest-scoring person span with photo, or null if no clear winner found
     */
    public static function getHighestScoringPerson(
        ?Carbon $targetDate = null,
        int $maxDaysUntil = 7,
        int $minSignificance = 50
    ): ?Span {
        // Get anniversaries WITHOUT photo requirement first (same as anniversaries list)
        $anniversaries = self::getUpcomingAnniversaries(
            $targetDate,
            30, // Look ahead 30 days
            [
                'include_birthdays' => false, // Only death anniversaries for featured person
                'include_death_anniversaries' => true,
                'include_album_anniversaries' => false,
                'min_significance' => $minSignificance,
                'max_days_until' => $maxDaysUntil,
                'require_photo' => false // Don't filter by photo yet - check later
            ]
        );
        
        // Filter to only death anniversaries
        $deathAnniversaries = array_filter($anniversaries, function($event) {
            return $event['type'] === 'death_anniversary';
        });
        
        if (empty($deathAnniversaries)) {
            return null;
        }
        
        // Reset array keys
        $deathAnniversaries = array_values($deathAnniversaries);
        
        // Check if there's a clear winner based on the top person
        // A clear winner means:
        // 1. Top anniversary is happening TODAY (days_until === 0) with significance >= 50, OR
        // 2. Top anniversary has significantly higher significance than second (at least 2x), OR
        // 3. Top anniversary has high significance (>= 100) and second has low (<= 10)
        
        $topAnniversary = $deathAnniversaries[0];
        $hasClearWinner = false;
        
        if (count($deathAnniversaries) === 1) {
            // Only one candidate - automatically a clear winner
            $hasClearWinner = true;
        } else {
            $secondAnniversary = $deathAnniversaries[1];
            $topSignificance = $topAnniversary['significance'];
            $secondSignificance = $secondAnniversary['significance'];
            $topIsToday = $topAnniversary['days_until'] === 0;
            $secondIsToday = $secondAnniversary['days_until'] === 0;
            
            // Clear winner case 1: Top one is happening today with high significance
            if ($topIsToday && $topSignificance >= 50) {
                // Only if second is not also today with similar significance
                if (!$secondIsToday || ($secondIsToday && $topSignificance > $secondSignificance * 1.5)) {
                    $hasClearWinner = true;
                }
            }
            
            // Clear winner case 2: Top one is significantly higher (at least 2x the second)
            if (!$hasClearWinner && $secondSignificance > 0 && ($topSignificance / $secondSignificance) >= 2.0) {
                $hasClearWinner = true;
            }
            
            // Clear winner case 3: Top one has high significance (>= 100) and second is low (<= 10)
            if (!$hasClearWinner && $topSignificance >= 100 && $secondSignificance <= 10) {
                $hasClearWinner = true;
            }
        }
        
        // If there's a clear winner, find the highest-scoring person WITH a photo
        if ($hasClearWinner) {
            // Check candidates in order of significance until we find one with a photo
            foreach ($deathAnniversaries as $anniversary) {
                $span = $anniversary['span'];
                
                // Check if this person has a photo
                $hasPhoto = \App\Models\Connection::where('type_id', 'features')
                    ->where('child_id', $span->id)
                    ->whereHas('parent', function($q) {
                        $q->where('type_id', 'thing')
                          ->whereJsonContains('metadata->subtype', 'photo');
                    })
                    ->exists();
                
                if ($hasPhoto) {
                    return $span;
                }
            }
            
            // Clear winner found but none have photos - return null (fall back to random)
            return null;
        }
        
        // No clear winner found
        return null;
    }
}
