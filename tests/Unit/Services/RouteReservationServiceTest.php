<?php

namespace Tests\Unit\Services;

use App\Services\RouteReservationService;
use Tests\TestCase;

class RouteReservationServiceTest extends TestCase
{

    protected RouteReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RouteReservationService();
    }

    /** @test */
    public function it_extracts_reserved_route_names_from_spans_routes()
    {
        $reservedNames = $this->service->getReservedRouteNames();
        
        // Should include known spans routes that we know exist
        $this->assertContains('shared-with-me', $reservedNames);
        $this->assertContains('create', $reservedNames);
        $this->assertContains('search', $reservedNames);
        $this->assertContains('types', $reservedNames);
        $this->assertContains('editor', $reservedNames);
        $this->assertContains('yaml-create', $reservedNames);
        
        // Should include additional reserved names
        $this->assertContains('api', $reservedNames);
        
        // Should not include parameter segments
        $this->assertNotContains('{span}', $reservedNames);
        $this->assertNotContains('{subject}', $reservedNames);
        $this->assertNotContains('{predicate}', $reservedNames);
        $this->assertNotContains('{object}', $reservedNames);
        
        // Should return an array
        $this->assertIsArray($reservedNames);
        $this->assertNotEmpty($reservedNames);
    }

    /** @test */
    public function it_correctly_identifies_reserved_slugs()
    {
        $this->assertTrue($this->service->isReserved('shared-with-me'));
        $this->assertTrue($this->service->isReserved('create'));
        $this->assertTrue($this->service->isReserved('search'));
        $this->assertTrue($this->service->isReserved('api'));
        
        // Case insensitive
        $this->assertTrue($this->service->isReserved('SHARED-WITH-ME'));
        $this->assertTrue($this->service->isReserved('Create'));
        $this->assertTrue($this->service->isReserved('SEARCH'));
    }

    /** @test */
    public function it_allows_non_reserved_slugs()
    {
        $this->assertFalse($this->service->isReserved('john-doe'));
        $this->assertFalse($this->service->isReserved('my-event'));
        $this->assertFalse($this->service->isReserved('test-person'));
        $this->assertFalse($this->service->isReserved('random-slug'));
    }

    /** @test */
    public function it_caches_reserved_names()
    {
        // First call should cache the result
        $names1 = $this->service->getReservedRouteNames();
        
        // Second call should use cache
        $names2 = $this->service->getReservedRouteNames();
        
        $this->assertEquals($names1, $names2);
    }

    /** @test */
    public function it_can_clear_cache()
    {
        // Get names to populate cache
        $this->service->getReservedRouteNames();
        
        // Clear cache
        $this->service->clearCache();
        
        // Should still work after cache clear
        $names = $this->service->getReservedRouteNames();
        $this->assertNotEmpty($names);
        $this->assertContains('shared-with-me', $names);
    }

    /** @test */
    public function it_returns_unique_names()
    {
        $names = $this->service->getReservedRouteNames();
        $uniqueNames = array_unique($names);
        
        $this->assertEquals($names, $uniqueNames, 'Reserved names should be unique');
    }
} 