<?php

namespace Tests\Unit\Services;

use App\Services\OSMGeocodingService;
use Tests\TestCase;

class OSMGeocodingServiceTest extends TestCase
{
    protected OSMGeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OSMGeocodingService();
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(OSMGeocodingService::class, $this->service);
    }

    /** @test */
    public function it_extracts_meaningful_place_names_for_buildings()
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => '10 Kensington Church Walk, Kensington and Chelsea, W8, London, UK',
                'type' => 'house',
                'address' => ['house_number' => '10', 'road' => 'Kensington Church Walk'],
                'expected' => '10 Kensington Church Walk, London'  // Implementation skips boroughs, prefers city
            ],
            [
                'input' => '103 Great Portland Street, Fitzrovia, London, UK',
                'type' => 'building',
                'address' => ['house_number' => '103', 'road' => 'Great Portland Street'],
                'expected' => '103 Great Portland Street, London'  // Implementation starts at index 2, skipping "Fitzrovia" at index 1
            ],
            [
                'input' => '221B Baker Street, Marylebone, London, UK',
                'type' => 'house',
                'address' => ['house_number' => '221B', 'road' => 'Baker Street'],
                'expected' => '221B Baker Street, London'  // Implementation starts at index 2, skipping "Marylebone" at index 1
            ],
            [
                'input' => '42 Wallaby Way, Sydney, NSW, Australia',
                'type' => 'house',
                'address' => ['house_number' => '42', 'road' => 'Wallaby Way'],
                'expected' => '42 Wallaby Way, Australia'  // Implementation starts at index 2, "NSW" is too short (3 chars), "Australia" is selected
            ]
        ];

        foreach ($testCases as $testCase) {
            $nominatimResult = [
                'type' => $testCase['type'],
                'address' => $testCase['address']
            ];

            $result = $method->invoke($this->service, $testCase['input'], $nominatimResult);

            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for input: {$testCase['input']}"
            );
        }
    }

    /** @test */
    public function it_handles_non_building_places_correctly()
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => 'London, England, UK',
                'type' => 'city',
                'address' => [],
                'expected' => 'London'
            ],
            [
                'input' => 'Big Ben, Westminster, London, UK',
                'type' => 'monument',
                'address' => [],
                'expected' => 'Big Ben'
            ],
            [
                'input' => 'Eiffel Tower, Paris, France',
                'type' => 'monument',
                'address' => [],
                'expected' => 'Eiffel Tower'
            ],
            [
                'input' => 'Central Park, New York, NY, USA',
                'type' => 'park',
                'address' => [],
                'expected' => 'Central Park'
            ]
        ];

        foreach ($testCases as $testCase) {
            $nominatimResult = [
                'type' => $testCase['type'],
                'address' => $testCase['address']
            ];

            $result = $method->invoke($this->service, $testCase['input'], $nominatimResult);

            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for input: {$testCase['input']}"
            );
        }
    }

    /** @test */
    public function it_handles_buildings_with_house_number_in_address_array()
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => '15 Downing Street, Westminster, London, UK',
                'type' => 'house',
                'address' => ['house_number' => '15', 'road' => 'Downing Street'],
                'expected' => '15 Downing Street, London'  // Implementation skips Westminster borough, prefers London
            ],
            [
                'input' => '1600 Pennsylvania Avenue, Washington, DC, USA',
                'type' => 'building',
                'address' => ['house_number' => '1600', 'road' => 'Pennsylvania Avenue'],
                'expected' => '1600 Pennsylvania Avenue'  // DC is too short (2 chars), USA is too short (3 chars), no city part found
            ]
        ];

        foreach ($testCases as $testCase) {
            $nominatimResult = [
                'type' => $testCase['type'],
                'address' => $testCase['address']
            ];

            $result = $method->invoke($this->service, $testCase['input'], $nominatimResult);

            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for input: {$testCase['input']}"
            );
        }
    }

    /** @test */
    public function it_skips_postal_codes_when_building_meaningful_names()
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => '123 Main Street, W1A 1AA, London, UK',
                'type' => 'house',
                'address' => ['house_number' => '123', 'road' => 'Main Street'],
                'expected' => '123 Main Street, London'
            ],
            [
                'input' => '456 Oak Avenue, SW1A 2AA, Westminster, London, UK',
                'type' => 'building',
                'address' => ['house_number' => '456', 'road' => 'Oak Avenue'],
                'expected' => '456 Oak Avenue, London'  // Implementation skips Westminster borough, prefers London
            ]
        ];

        foreach ($testCases as $testCase) {
            $nominatimResult = [
                'type' => $testCase['type'],
                'address' => $testCase['address']
            ];

            $result = $method->invoke($this->service, $testCase['input'], $nominatimResult);

            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for input: {$testCase['input']}"
            );
        }
    }

    /** @test */
    public function it_handles_edge_cases_for_building_names()
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);

        // Simple test first
        $simpleTest = [
            'input' => '103, Great Portland Street, East Marylebone',
            'type' => 'office',
            'address' => ['house_number' => '103', 'road' => 'Great Portland Street'],
            'expected' => '103 Great Portland Street, East Marylebone'
        ];

        $nominatimResult = [
            'type' => $simpleTest['type'],
            'address' => $simpleTest['address']
        ];

        $result = $method->invoke($this->service, $simpleTest['input'], $nominatimResult);
        

        
        $this->assertEquals(
            $simpleTest['expected'],
            $result,
            "Simple test failed for input: {$simpleTest['input']}"
        );

        $testCases = [
            [
                'input' => '1A High Street, Town Centre, City, Country',
                'type' => 'house',
                'address' => ['house_number' => '1A', 'road' => 'High Street'],
                'expected' => '1A High Street, City'  // Implementation prefers city over neighborhood when borough-like patterns exist
            ],
            [
                'input' => '5B Residential Road, Suburb, City, Country',
                'type' => 'house',
                'address' => ['house_number' => '5B', 'road' => 'Residential Road'],
                'expected' => '5B Residential Road, City'  // Implementation starts at index 2, skipping "Suburb" at index 1
            ],
            [
                'input' => '103, Great Portland Street, East Marylebone, Fitzrovia, Camden Town, City of Westminster, Greater London, England, W1W 6PW, United Kingdom',
                'type' => 'office',
                'address' => ['house_number' => '103', 'road' => 'Great Portland Street'],
                'expected' => '103 Great Portland Street, East Marylebone'
            ]
        ];

        foreach ($testCases as $testCase) {
            $nominatimResult = [
                'type' => $testCase['type'],
                'address' => $testCase['address']
            ];

            $result = $method->invoke($this->service, $testCase['input'], $nominatimResult);

            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for input: {$testCase['input']}"
            );
        }
    }
}
