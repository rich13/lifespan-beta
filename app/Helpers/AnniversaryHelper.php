<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Span;

class AnniversaryHelper
{
    /** Cache family member IDs per request so homepage (upcoming-anniversaries + random-person-card) doesn't recompute multiple times */
    private static ?array $familyMemberIdsBySpanId = null;

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
     * Weight used only for ordering the anniversaries list. Birthdays (especially today
     * and family) should not be buried beneath high milestone scores for unrelated people.
     */
    private static function sortWeight(array $event): int
    {
        $weight = (int) ($event['significance'] ?? 0);
        $type = $event['type'] ?? '';
        $daysUntil = (int) ($event['days_until'] ?? 999);
        $isFamily = (bool) ($event['is_family_member'] ?? false);

        if ($type === 'birthday') {
            if ($isFamily) {
                $weight += 500;
            }
            if ($daysUntil === 0) {
                $weight += 400;
            }
        }

        return $weight;
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
        $targetDate = ($targetDate ?? DateHelper::getCurrentDate())->copy()->startOfDay();
        $startDateForQuery = $targetDate->copy();
        $endDateForQuery = $targetDate->copy()->addDays($daysAhead);
        
        $includeBirthdays = $options['include_birthdays'] ?? true;
        $includeDeathAnniversaries = $options['include_death_anniversaries'] ?? true;
        $includeAlbumAnniversaries = $options['include_album_anniversaries'] ?? true;
        $minSignificance = $options['min_significance'] ?? null;
        $maxDaysUntil = $options['max_days_until'] ?? null;
        $requirePhoto = $options['require_photo'] ?? false;
        
        // Get family member IDs if user is authenticated (cached per request to avoid duplicate work on homepage)
        $familyMemberIds = collect();
        $user = auth()->user();
        if ($user && $user->personalSpan) {
            $personalSpan = $user->personalSpan;
            $spanId = $personalSpan->id;
            if (self::$familyMemberIdsBySpanId === null) {
                self::$familyMemberIdsBySpanId = [];
            }
            if (array_key_exists($spanId, self::$familyMemberIdsBySpanId)) {
                $familyMemberIds = self::$familyMemberIdsBySpanId[$spanId];
            } else {
                // Get all family members using the same methods as the family card
                $ancestors = $personalSpan->ancestors(3);
                $descendants = $personalSpan->descendants(2);
                $siblings = $personalSpan->siblings();
                $unclesAndAunts = $personalSpan->unclesAndAunts();
                $cousins = $personalSpan->cousins();
                $nephewsAndNieces = $personalSpan->nephewsAndNieces();
                $extraNephewsAndNieces = $personalSpan->extraNephewsAndNieces();

                $familyMemberIds = collect()
                    ->push($personalSpan->id)
                    ->concat($ancestors->pluck('span')->pluck('id'))
                    ->concat($descendants->pluck('span')->pluck('id'))
                    ->concat($siblings->pluck('id'))
                    ->concat($unclesAndAunts->pluck('id'))
                    ->concat($cousins->pluck('id'))
                    ->concat($nephewsAndNieces->pluck('id'))
                    ->concat($extraNephewsAndNieces->pluck('id'))
                    ->unique()
                    ->filter();
                self::$familyMemberIdsBySpanId[$spanId] = $familyMemberIds;
            }
        }
        
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
                                $isFamilyMember = $familyMemberIds->contains($span->id);
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'birthday',
                                    'date' => $nextBirthday,
                                    'age' => $ageAtNextBirthday,
                                    'years' => $ageAtNextBirthday,
                                    'days_until' => $daysUntilBirthday,
                                    'significance' => $significance,
                                    'is_family_member' => $isFamilyMember
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
                                $isFamilyMember = $familyMemberIds->contains($span->id);
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'death_anniversary',
                                    'date' => $nextAnniversary,
                                    'years' => $yearsSinceDeath,
                                    'days_until' => $daysUntilAnniversary,
                                    'significance' => $significance,
                                    'is_family_member' => $isFamilyMember
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
                                // Albums are not family members, but we include the flag for consistency
                                $significantDates[] = [
                                    'span' => $span,
                                    'type' => 'album_anniversary',
                                    'date' => $nextAnniversary,
                                    'years' => $yearsSinceRelease,
                                    'days_until' => $daysUntilAnniversary,
                                    'significance' => $significance,
                                    'is_family_member' => false
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // Sort by: 1) Today first, 2) Family first, 3) Sort weight (milestones + birthday boosts), 4) Prefer birthdays on ties, 5) Sooner first
        usort($significantDates, function($a, $b) {
            // First priority: events happening TODAY (days_until === 0) come first
            $aIsToday = $a['days_until'] === 0;
            $bIsToday = $b['days_until'] === 0;
            if ($aIsToday !== $bIsToday) {
                return $bIsToday <=> $aIsToday; // true (1) comes before false (0)
            }
            
            // Second priority: family members come before non-family members
            $aIsFamily = $a['is_family_member'] ?? false;
            $bIsFamily = $b['is_family_member'] ?? false;
            if ($aIsFamily !== $bIsFamily) {
                return $bIsFamily <=> $aIsFamily; // true (1) comes before false (0)
            }
            
            // Third priority: sort weight (significance plus birthday / family boosts)
            $weightCompare = self::sortWeight($b) <=> self::sortWeight($a);
            if ($weightCompare !== 0) {
                return $weightCompare;
            }

            // Fourth: birthdays before other types when still tied
            $typeRank = fn ($type) => match ($type) {
                'birthday' => 0,
                'death_anniversary' => 1,
                'album_anniversary' => 2,
                default => 3,
            };
            $typeCompare = $typeRank($a['type'] ?? '') <=> $typeRank($b['type'] ?? '');
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            
            // Fifth priority: days until (sooner is better)
            return $a['days_until'] <=> $b['days_until'];
        });
        
        return $significantDates;
    }
    
    /**
     * Person to feature on the homepage: first entry in the same ordered list as the
     * Anniversaries card that is a living person's birthday or a death anniversary.
     * Album release anniversaries are skipped (non-person spans).
     */
    public static function getHighestScoringPerson(?Carbon $targetDate = null): ?Span
    {
        $targetDate = $targetDate ?? DateHelper::getCurrentDate();
        $anniversaries = self::getUpcomingAnniversaries($targetDate, 60);

        foreach ($anniversaries as $event) {
            if (($event['type'] ?? null) === 'birthday' || ($event['type'] ?? null) === 'death_anniversary') {
                return $event['span'];
            }
        }

        return null;
    }
}
