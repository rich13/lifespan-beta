<?php

namespace Tests\Unit;

use App\Models\Span;
use App\Models\User;
use App\Services\PersonalSpanCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonalSpanCacheTest extends TestCase
{
    public function test_second_lookup_does_not_query_the_personal_span(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->personal_span_id);

        $cache = app(PersonalSpanCache::class);
        $cache->forgetForUser($user->id);

        $first = $cache->rememberFor($user->fresh());
        $this->assertNotNull($first);
        $this->assertEquals($user->personal_span_id, $first->id);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $secondUser = $user->fresh();
        $second = $cache->rememberFor($secondUser);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($first->name, $second->name);

        $joined = strtolower(implode("\n", $sql));
        $this->assertStringNotContainsString('from "spans"', $joined);
        $this->assertStringNotContainsString('from spans', $joined);
    }

    public function test_saving_a_personal_span_clears_the_cache(): void
    {
        $user = User::factory()->create();
        $cache = app(PersonalSpanCache::class);
        $cache->forgetForUser($user->id);
        $cache->rememberFor($user->fresh());

        $this->assertTrue(Cache::has(PersonalSpanCache::key($user->id)));

        $personalSpan = Span::find($user->personal_span_id);
        $personalSpan->name = 'Cached Name';
        $personalSpan->save();

        $this->assertFalse(Cache::has(PersonalSpanCache::key($user->id)));
        $this->assertEquals('Cached Name', $cache->rememberFor($user->fresh())->name);
    }
}
