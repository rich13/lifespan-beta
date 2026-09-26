<?php

namespace Tests\Feature;

use App\Helpers\AnniversaryHelper;
use App\Models\Span;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnniversaryHelperTest extends TestCase
{
    public function test_non_milestone_birthday_today_sorts_before_milestone_death_anniversary_today(): void
    {
        if (! DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Unusual calendar day so seeded DB rows are unlikely to appear ahead in the sorted list
        $target = Carbon::create(2090, 9, 15, 12, 0, 0, config('app.timezone'));

        $owner = User::factory()->create();

        $birthdayPerson = Span::create([
            'name' => 'Birthday Today Person',
            'slug' => 'birthday-today-person-' . uniqid(),
            'type_id' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 2047,
            'start_month' => 9,
            'start_day' => 15,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $deathPerson = Span::create([
            'name' => 'Death Anniversary Today Person',
            'slug' => 'death-anniversary-today-' . uniqid(),
            'type_id' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1900,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 1946,
            'end_month' => 9,
            'end_day' => 15,
        ]);

        $events = AnniversaryHelper::getUpcomingAnniversaries($target, 60);

        $birthdayIndex = null;
        $deathIndex = null;
        foreach ($events as $i => $event) {
            if (($event['span']->id ?? null) === $birthdayPerson->id) {
                $birthdayIndex = $i;
            }
            if (($event['span']->id ?? null) === $deathPerson->id) {
                $deathIndex = $i;
            }
        }

        $this->assertNotNull($birthdayIndex, 'Birthday person should appear in upcoming anniversaries');
        $this->assertNotNull($deathIndex, 'Death anniversary person should appear in upcoming anniversaries');
        $this->assertLessThan($deathIndex, $birthdayIndex, 'A non-milestone birthday today should rank above a decade death anniversary today');
    }

    public function test_get_highest_scoring_person_matches_first_birthday_or_death_in_anniversary_list(): void
    {
        $target = Carbon::create(2090, 9, 15, 12, 0, 0, config('app.timezone'));

        $anniversaries = AnniversaryHelper::getUpcomingAnniversaries($target, 60);
        $featured = AnniversaryHelper::getHighestScoringPerson($target);

        $expectedId = null;
        foreach ($anniversaries as $event) {
            if (($event['type'] ?? null) === 'birthday' || ($event['type'] ?? null) === 'death_anniversary') {
                $expectedId = $event['span']->id ?? null;
                break;
            }
        }

        $this->assertSame($expectedId, $featured?->id);
    }

    public function test_film_release_anniversary_appears_in_upcoming_anniversaries(): void
    {
        if (! DB::table('span_types')->where('type_id', 'thing')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'thing',
                'name' => 'Thing',
                'description' => 'A thing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $target = Carbon::create(2090, 9, 15, 12, 0, 0, config('app.timezone'));
        $owner = User::factory()->create();

        $film = Span::create([
            'name' => 'Film Anniversary Today',
            'slug' => 'film-anniversary-today-' . uniqid(),
            'type_id' => 'thing',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => ['subtype' => 'film'],
            'start_year' => 1990,
            'start_month' => 9,
            'start_day' => 15,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $events = AnniversaryHelper::getUpcomingAnniversaries($target, 60);

        $filmEvent = collect($events)->first(fn ($event) => ($event['span']->id ?? null) === $film->id);

        $this->assertNotNull($filmEvent, 'Film should appear in upcoming anniversaries');
        $this->assertSame('film_anniversary', $filmEvent['type']);
        $this->assertSame(0, $filmEvent['days_until']);
        $this->assertSame(100, $filmEvent['years']);
    }

    public function test_living_person_older_than_plausible_human_lifespan_is_excluded_from_birthdays(): void
    {
        if (! DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $target = Carbon::create(2026, 9, 25, 12, 0, 0, config('app.timezone'));
        $owner = User::factory()->create();

        $implausible = Span::create([
            'name' => 'Implausibly Old Person',
            'slug' => 'implausibly-old-person-' . uniqid(),
            'type_id' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1843,
            'start_month' => 9,
            'start_day' => 25,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $recordAge = Span::create([
            'name' => 'Record Age Person',
            'slug' => 'record-age-person-' . uniqid(),
            'type_id' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1904,
            'start_month' => 9,
            'start_day' => 25,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $plausible = Span::create([
            'name' => 'Plausibly Living Person',
            'slug' => 'plausibly-living-person-' . uniqid(),
            'type_id' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1920,
            'start_month' => 9,
            'start_day' => 25,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $events = AnniversaryHelper::getUpcomingAnniversaries($target, 60);
        $ids = collect($events)->map(fn ($event) => $event['span']->id ?? null);

        $this->assertFalse($ids->contains($implausible->id), 'A person with no death date who would be older than 122 should be omitted');
        $this->assertTrue($ids->contains($recordAge->id), 'A person turning 122, the oldest verified human age, should still appear');
        $this->assertTrue($ids->contains($plausible->id), 'A person within a plausible human lifespan should still appear');
    }
}
