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
            'start_year' => 1947,
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
}
