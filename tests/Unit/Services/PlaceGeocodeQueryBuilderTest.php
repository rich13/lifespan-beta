<?php

namespace Tests\Unit\Services;

use App\Services\PlaceGeocodeQueryBuilder;
use Tests\TestCase;

class PlaceGeocodeQueryBuilderTest extends TestCase
{
    private PlaceGeocodeQueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PlaceGeocodeQueryBuilder();
    }

    public function test_it_cleans_uk_address_with_outward_postcode(): void
    {
        $query = $this->builder->fromName('1 Avondale Road, N13 Enfield', subtype: 'address');

        $this->assertSame('1', $query->houseNumber);
        $this->assertSame('Avondale Road', $query->street);
        $this->assertSame('Enfield', $query->locality);
        $this->assertSame('N13', $query->outwardCode);
        $this->assertSame('United Kingdom', $query->country);
        $this->assertSame('gb', $query->countryCode);
        $this->assertContains('1 Avondale Road, Enfield', $query->searchStrings());
        $this->assertSame('1 Avondale Road', $query->structuredParams()['street'] ?? null);
        $this->assertSame('Enfield', $query->structuredParams()['city'] ?? null);
        $this->assertTrue($query->looksLikeAddress());
        $this->assertTrue($query->looksLikeLondon());
    }

    public function test_it_strips_formerly_clause_and_quoted_house_name(): void
    {
        $query = $this->builder->fromName(
            '"Lord\'s Meade" formerly partly on the site of 1 Lordsmead Road, N17',
            subtype: 'address'
        );

        $this->assertSame("Lord's Meade", $query->houseName);
        $this->assertSame('1', $query->houseNumber);
        $this->assertSame('Lordsmead Road', $query->street);
        $this->assertSame('N17', $query->outwardCode);
        $this->assertSame('United Kingdom', $query->country);
    }

    public function test_it_parses_quoted_villa_and_street(): void
    {
        $query = $this->builder->fromName("'Fossil Villa', 22 Belvedere Road, Anerley", subtype: 'address');

        $this->assertSame('Fossil Villa', $query->houseName);
        $this->assertSame('22', $query->houseNumber);
        $this->assertSame('Belvedere Road', $query->street);
        $this->assertSame('Anerley', $query->locality);
        $this->assertContains('22 Belvedere Road, Anerley', $query->searchStrings());
        $this->assertContains('22 Belvedere Road, London', $query->searchStrings());
        $this->assertContains('Fossil Villa, Anerley', $query->searchStrings());
        $this->assertTrue($query->looksLikeLondon());
    }

    public function test_it_parses_city_with_uk_nation(): void
    {
        $query = $this->builder->fromName('Aberdeen, Scotland');

        $this->assertSame('Aberdeen', $query->primaryName);
        $this->assertSame('Scotland', $query->region);
        $this->assertSame('United Kingdom', $query->country);
        $this->assertContains('Aberdeen, Scotland, United Kingdom', $query->searchStrings());
        $this->assertFalse($query->looksLikeAddress());
    }

    public function test_it_parses_us_city_state_country(): void
    {
        $query = $this->builder->fromName('Chicago, Illinois, USA');

        $this->assertSame('Chicago', $query->primaryName);
        $this->assertSame('Illinois', $query->region);
        $this->assertSame('United States', $query->country);
        $this->assertSame('us', $query->countryCode);
        $this->assertContains('Chicago, Illinois, United States', $query->searchStrings());
    }

    public function test_it_parses_leeds_uk(): void
    {
        $query = $this->builder->fromName('Leeds, UK');

        $this->assertSame('Leeds', $query->primaryName);
        $this->assertSame('United Kingdom', $query->country);
        $this->assertContains('Leeds, United Kingdom', $query->searchStrings());
    }

    public function test_it_parses_full_uk_postcode(): void
    {
        $query = $this->builder->fromName("'The Glebe', Oakley Road, Bromley Common, BR2 8HQ", subtype: 'address');

        $this->assertSame('The Glebe', $query->houseName);
        $this->assertSame('Oakley Road', $query->street);
        $this->assertSame('Bromley Common', $query->locality);
        $this->assertSame('BR2 8HQ', $query->postalCode);
        $this->assertSame('BR2', $query->outwardCode);
        $this->assertContains('The Glebe, Oakley Road, Bromley Common', $query->searchStrings());
    }

    public function test_it_parses_house_number_comma_street(): void
    {
        $query = $this->builder->fromName('1, Eaton Square, SW1', subtype: 'address');

        $this->assertSame('1', $query->houseNumber);
        $this->assertSame('Eaton Square', $query->street);
        $this->assertSame('SW1', $query->outwardCode);
        $this->assertNull($query->locality);
        $this->assertContains('1 Eaton Square, United Kingdom', $query->searchStrings());
    }

    public function test_it_skips_continents(): void
    {
        $query = $this->builder->fromName('Europe');

        $this->assertTrue($query->shouldSkip);
    }

    public function test_it_uses_existing_coordinates_as_london_hint(): void
    {
        $query = $this->builder->fromName('Somewhere', latitude: 51.5074, longitude: -0.1278);

        $this->assertTrue($query->looksLikeLondon());
    }
}
