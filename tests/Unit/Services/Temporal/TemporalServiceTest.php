<?php

namespace Tests\Unit\Services\Temporal;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use Tests\TestCase;

class TemporalServiceTest extends TestCase
{

    private TemporalService $service;
    private PrecisionValidator $precisionValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->precisionValidator = new PrecisionValidator();
        $this->service = new TemporalService($this->precisionValidator);
    }

    public function test_detects_overlap_with_existing_connections(): void
    {
        // Create parent and child spans
        $user = User::factory()->create();
        $parent = Span::create([
            'name' => 'Parent',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1950,
            'access_level' => 'public'
        ]);
        $child = Span::create([
            'name' => 'Child',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1980,
            'access_level' => 'public'
        ]);

        // Create an existing connection span
        $existingSpan = Span::create([
            'name' => 'Existing Connection',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2005,
            'access_level' => 'public'
        ]);

        // Create the existing connection
        Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'employment',
            'connection_span_id' => $existingSpan->id
        ]);

        // Test overlapping span
        $overlappingSpan = Span::create([
            'name' => 'Overlapping Connection',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2004,
            'end_year' => 2006,
            'access_level' => 'public'
        ]);

        $this->assertTrue(
            $this->service->wouldOverlap($parent, $child, 'employment', $overlappingSpan)
        );

        // Test non-overlapping span
        $nonOverlappingSpan = Span::create([
            'name' => 'Non-Overlapping Connection',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2006,
            'end_year' => 2007,
            'access_level' => 'public'
        ]);

        $this->assertFalse(
            $this->service->wouldOverlap($parent, $child, 'employment', $nonOverlappingSpan)
        );
    }

    public function test_detects_adjacent_spans(): void
    {
        $user = User::factory()->create();
        $span1 = Span::create([
            'name' => 'Span 1',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day',
            'access_level' => 'public'
        ]);

        $span2 = Span::create([
            'name' => 'Span 2',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2006,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2006,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day',
            'access_level' => 'public'
        ]);

        $this->assertTrue($this->service->areAdjacent($span1, $span2));

        $user = User::factory()->create();
        $span3 = Span::create([
            'name' => 'Test Span 3',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2007,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2007,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day',
            'access_level' => 'public'
        ]);

        $this->assertFalse($this->service->areAdjacent($span1, $span3));
    }

    public function test_validates_span_dates(): void
    {
        $user = User::factory()->create();
        
        // Valid dates
        $span1 = Span::create([
            'name' => 'Valid Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);
        $this->assertTrue($this->service->validateSpanDates($span1));

        // Test invalid dates by testing the validation logic directly without creating invalid data
        $invalidSpan = new Span([
            'start_year' => 2001,
            'end_year' => 2000
        ]);
        $this->assertFalse($this->service->validateSpanDates($invalidSpan));

        // Open-ended span
        $span3 = Span::create([
            'name' => 'Open Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => null,
            'access_level' => 'public'
        ]);
        $this->assertTrue($this->service->validateSpanDates($span3));

        // Valid dates with different precisions (start year, end month)
        // Start: 1978 (year precision) normalizes to 1978-01-01
        // End: 1994-09 (month precision) normalizes to 1994-09-30
        // This should be valid because 1994-09-30 > 1978-01-01
        $span4 = new Span([
            'start_year' => 1978,
            'start_month' => null,
            'end_year' => 1994,
            'end_month' => 9,
        ]);
        $this->assertTrue($this->service->validateSpanDates($span4));

        // Invalid dates with different precisions (end before start)
        // Start: 1994-09 (month precision) normalizes to 1994-09-01
        // End: 1978 (year precision) normalizes to 1978-12-31
        // This should be invalid because 1978-12-31 < 1994-09-01
        $span5 = new Span([
            'start_year' => 1994,
            'start_month' => 9,
            'end_year' => 1978,
            'end_month' => null,
        ]);
        $this->assertFalse($this->service->validateSpanDates($span5));
    }

    public function test_normalizes_dates_based_on_precision(): void
    {
        $user = User::factory()->create();
        // Year precision
        $span1 = Span::create([
            'name' => 'Year Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'start_month' => 0,
            'start_day' => 0,
            'end_year' => 2000,
            'end_month' => 0,
            'end_day' => 0,
            'access_level' => 'public'
        ]);

        $this->assertEquals(
            '2000-01-01',
            $this->service->getNormalizedStartDate($span1)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-12-31',
            $this->service->getNormalizedEndDate($span1)->format('Y-m-d')
        );

        // Month precision
        $span2 = Span::create([
            'name' => 'Month Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 0,
            'end_year' => 2000,
            'end_month' => 6,
            'end_day' => 0,
            'access_level' => 'public'
        ]);

        $this->assertEquals(
            '2000-06-01',
            $this->service->getNormalizedStartDate($span2)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-06-30',
            $this->service->getNormalizedEndDate($span2)->format('Y-m-d')
        );

        // Day precision
        $span3 = Span::create([
            'name' => 'Day Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2000,
            'end_month' => 6,
            'end_day' => 15,
            'access_level' => 'public'
        ]);

        $this->assertEquals(
            '2000-06-15',
            $this->service->getNormalizedStartDate($span3)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-06-15',
            $this->service->getNormalizedEndDate($span3)->format('Y-m-d')
        );
    }
} 