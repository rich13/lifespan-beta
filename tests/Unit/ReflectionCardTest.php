<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Span;
use App\Models\User;
use Carbon\Carbon;
use App\Helpers\DateDurationCalculator;

class ReflectionCardTest extends TestCase
{
    private function createSpan($startYear, $startMonth = null, $startDay = null, $endYear = null, $endMonth = null, $endDay = null)
    {
        return Span::factory()->create([
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'end_day' => $endDay,
        ]);
    }

    private function createUserWithBirthDate($year, $month = null, $day = null)
    {
        $user = User::factory()->create();
        $span = $this->createSpan($year, $month, $day);
        $user->personalSpan()->associate($span);
        return $user;
    }

    private function calculateReflectionPoint($personBirthDate, $viewerAge)
    {
        return $personBirthDate->copy()
            ->addYears($viewerAge['years'])
            ->addMonths($viewerAge['months'])
            ->addDays($viewerAge['days']);
    }

    public function test_person_alive_reflection_point_before_viewer_birth()
    {
        // Person born 1900, viewer born 1990, current year 2024
        // When person was viewer's current age (34), it was 1934
        $personBirthDate = Carbon::create(1900, 1, 1);
        $viewerBirthDate = Carbon::create(1990, 1, 1);
        $currentDate = Carbon::create(2024, 1, 1);
        
        // Calculate viewer's current age (34)
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => 1990, 'month' => 1, 'day' => 1],
            (object)['year' => 2024, 'month' => 1, 'day' => 1]
        );

        // Calculate when person would be viewer's age
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        $this->assertEquals(1934, $reflectionDate->year);
        $this->assertEquals(1, $reflectionDate->month);
        $this->assertEquals(1, $reflectionDate->day);

        // Verify reflection point is before viewer's birth
        $this->assertTrue($reflectionDate->lt($viewerBirthDate));
    }

    public function test_person_alive_reflection_point_after_viewer_birth()
    {
        // Person born 2000, viewer born 1990, current year 2024
        // When person will be viewer's current age (34), it will be 2034
        $personBirthDate = Carbon::create(2000, 1, 1);
        $viewerBirthDate = Carbon::create(1990, 1, 1);
        $currentDate = Carbon::create(2024, 1, 1);
        
        // Calculate viewer's current age (34)
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => 1990, 'month' => 1, 'day' => 1],
            (object)['year' => 2024, 'month' => 1, 'day' => 1]
        );

        // Calculate when person would be viewer's age
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        $this->assertEquals(2034, $reflectionDate->year);
        $this->assertEquals(1, $reflectionDate->month);
        $this->assertEquals(1, $reflectionDate->day);

        // Verify reflection point is after viewer's birth
        $this->assertTrue($reflectionDate->gt($viewerBirthDate));
    }

    public function test_person_dead_reflection_point_before_viewer_birth()
    {
        // Person born 1900, died 1950, viewer born 1990, current year 2024
        // When person would have been viewer's current age (34), it would have been 1934
        $personBirthDate = Carbon::create(1900, 1, 1);
        $personDeathDate = Carbon::create(1950, 1, 1);
        $viewerBirthDate = Carbon::create(1990, 1, 1);
        $currentDate = Carbon::create(2024, 1, 1);
        
        // Calculate viewer's current age (34)
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => 1990, 'month' => 1, 'day' => 1],
            (object)['year' => 2024, 'month' => 1, 'day' => 1]
        );

        // Calculate when person would have been viewer's age
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        $this->assertEquals(1934, $reflectionDate->year);
        $this->assertEquals(1, $reflectionDate->month);
        $this->assertEquals(1, $reflectionDate->day);

        // Verify reflection point is before person's death
        $this->assertTrue($reflectionDate->lt($personDeathDate));
        
        // Verify reflection point is before viewer's birth
        $this->assertTrue($reflectionDate->lt($viewerBirthDate));

        // Calculate time before death
        $timeBeforeDeath = DateDurationCalculator::calculateDuration(
            (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day],
            (object)['year' => $personDeathDate->year, 'month' => $personDeathDate->month, 'day' => $personDeathDate->day]
        );

        $this->assertEquals(16, $timeBeforeDeath['years']);
        $this->assertEquals(0, $timeBeforeDeath['months']);
        $this->assertEquals(0, $timeBeforeDeath['days']);
    }

    public function test_person_dead_reflection_point_after_death()
    {
        // Person born 1942-11-27, died 1970-09-18, viewer born 1976-03-13, current year 2024
        // When person would have been viewer's current age (47), it would have been 1990
        $personBirthDate = Carbon::create(1942, 11, 27);
        $personDeathDate = Carbon::create(1970, 9, 18);
        $viewerBirthDate = Carbon::create(1976, 3, 13);
        $currentDate = Carbon::create(2024, 1, 1);
        
        // Calculate viewer's current age
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => 1976, 'month' => 3, 'day' => 13],
            (object)['year' => 2024, 'month' => 1, 'day' => 1]
        );

        // Calculate when person would have been viewer's age
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        // Verify reflection point is after person's death
        $this->assertTrue($reflectionDate->gt($personDeathDate));
        
        // Calculate time after death
        $timeAfterDeath = DateDurationCalculator::calculateDuration(
            (object)['year' => $personDeathDate->year, 'month' => $personDeathDate->month, 'day' => $personDeathDate->day],
            (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day]
        );

        // Verify time calculations are correct
        $this->assertGreaterThan(0, $timeAfterDeath['years']);
    }

    public function test_reflection_point_at_death()
    {
        // Create a person who died at exactly 50 years old
        $personBirthDate = Carbon::createFromDate(1900, 1, 1);
        $personDeathDate = Carbon::createFromDate(1950, 1, 1);
        
        // Create span with properly formatted dates
        $person = $this->createSpan(
            $personBirthDate->year,
            $personBirthDate->month,
            $personBirthDate->day,
            $personDeathDate->year,
            $personDeathDate->month,
            $personDeathDate->day
        );

        // Create a viewer who is 50 years old
        $viewerBirthDate = Carbon::createFromDate(1970, 1, 1);
        $viewer = $this->createUserWithBirthDate($viewerBirthDate->year, $viewerBirthDate->month, $viewerBirthDate->day);

        // Calculate viewer's current age (50)
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day],
            (object)['year' => 2020, 'month' => 1, 'day' => 1]  // Setting current date to make viewer exactly 50
        );

        // Calculate reflection point
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        // Verify reflection point matches death date
        $this->assertEquals($personDeathDate->year, $reflectionDate->year);
        $this->assertEquals($personDeathDate->month, $reflectionDate->month);
        $this->assertEquals($personDeathDate->day, $reflectionDate->day);
    }

    public function test_reflection_point_at_viewer_birth()
    {
        // Person born 1960, viewer born 1990, set viewer's age to match the difference
        $personBirthDate = Carbon::create(1960, 1, 1);
        $viewerBirthDate = Carbon::create(1990, 1, 1);
        
        // Calculate age that would make reflection point match viewer's birth
        $viewerAge = DateDurationCalculator::calculateDuration(
            (object)['year' => 1960, 'month' => 1, 'day' => 1],
            (object)['year' => 1990, 'month' => 1, 'day' => 1]
        );

        // Calculate reflection point
        $reflectionDate = $this->calculateReflectionPoint($personBirthDate, $viewerAge);

        // Verify reflection point matches viewer's birth
        $this->assertTrue($reflectionDate->eq($viewerBirthDate));
    }
} 