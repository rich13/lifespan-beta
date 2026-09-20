<?php

namespace Tests\Unit;

use App\Models\SpanType;
use App\Services\SpanTypeCatalogue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpanTypeCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SpanTypeCatalogue::class)->forget();
    }

    public function test_second_lookup_does_not_query_span_types(): void
    {
        $catalogue = app(SpanTypeCatalogue::class);

        $first = $catalogue->forNewSpanModal();
        $this->assertNotEmpty($first);
        $this->assertFalse($first->contains(fn ($type) => $type->type_id === 'connection'));

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $second = $catalogue->forNewSpanModal();
        $timeless = $catalogue->timelessTypeIds();

        $this->assertEquals($first->pluck('type_id')->all(), $second->pluck('type_id')->all());
        $this->assertContains('place', $timeless);

        $joined = strtolower(implode("\n", $sql));
        $this->assertStringNotContainsString('span_types', $joined);
    }

    public function test_saving_a_span_type_clears_the_catalogue_cache(): void
    {
        $catalogue = app(SpanTypeCatalogue::class);
        $catalogue->forNewSpanModal();
        $this->assertTrue(Cache::has(SpanTypeCatalogue::CACHE_KEY));

        $person = SpanType::find('person');
        $person->name = $person->name.' (cached)';
        $person->save();

        $this->assertFalse(Cache::has(SpanTypeCatalogue::CACHE_KEY));
        $this->assertTrue(
            $catalogue->forNewSpanModal()->contains(fn ($type) => $type->name === $person->name)
        );
    }
}
