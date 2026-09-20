<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\SpanType;
use App\Models\User;
use Tests\TestCase;

class ReconcileWikimediaPhotoDatesTest extends TestCase
{
    public function test_command_replaces_upload_timestamp_with_title_year(): void
    {
        $user = User::factory()->create();
        SpanType::firstOrCreate(['type_id' => 'thing'], ['name' => 'Thing', 'description' => 'A thing']);

        $photo = Span::create([
            'name' => 'Thom Yorke 1998',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 2019,
            'start_month' => 6,
            'start_day' => 29,
            'end_year' => 2019,
            'end_month' => 6,
            'end_day' => 29,
            'metadata' => [
                'subtype' => 'photo',
                'source' => 'Wikimedia Commons',
                'title' => 'File:Thom Yorke 1998.jpg',
                'description' => 'Wash D.C. 1998',
                'date' => '2019-06-29 20:27',
            ],
        ]);

        $this->artisan('wikimedia:reconcile-photo-dates')
            ->assertSuccessful();

        $photo->refresh();
        $this->assertSame(1998, $photo->start_year);
        $this->assertNull($photo->start_month);
        $this->assertNull($photo->start_day);
        $this->assertSame(1998, $photo->end_year);
        $this->assertNull($photo->end_month);
        $this->assertNull($photo->end_day);
    }

    public function test_dry_run_does_not_change_dates(): void
    {
        $user = User::factory()->create();
        SpanType::firstOrCreate(['type_id' => 'thing'], ['name' => 'Thing', 'description' => 'A thing']);

        $photo = Span::create([
            'name' => 'Thom Yorke 1998',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 2019,
            'start_month' => 6,
            'start_day' => 29,
            'end_year' => 2019,
            'end_month' => 6,
            'end_day' => 29,
            'metadata' => [
                'subtype' => 'photo',
                'source' => 'Wikimedia Commons',
                'title' => 'File:Thom Yorke 1998.jpg',
                'description' => 'Wash D.C. 1998',
                'date' => '2019-06-29 20:27',
            ],
        ]);

        $this->artisan('wikimedia:reconcile-photo-dates', ['--dry-run' => true])
            ->assertSuccessful();

        $photo->refresh();
        $this->assertSame(2019, $photo->start_year);
        $this->assertSame(6, $photo->start_month);
        $this->assertSame(29, $photo->start_day);
    }

    public function test_command_keeps_a_year_only_commons_date(): void
    {
        $user = User::factory()->create();
        SpanType::firstOrCreate(['type_id' => 'thing'], ['name' => 'Thing', 'description' => 'A thing']);

        $photo = Span::create([
            'name' => 'Charles Darwin 1880',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1880,
            'start_month' => null,
            'start_day' => null,
            'end_year' => 1880,
            'end_month' => null,
            'end_day' => null,
            'metadata' => [
                'subtype' => 'photo',
                'source' => 'Wikimedia Commons',
                'title' => 'File:Charles Darwin 1880.jpg',
                'description' => 'Charles Darwin in 1881, cropped version.',
                'date' => '1881',
                'categories' => ['1881 portrait photographs'],
            ],
        ]);

        $this->artisan('wikimedia:reconcile-photo-dates')
            ->assertSuccessful();

        $photo->refresh();
        $this->assertSame(1881, $photo->start_year);
        $this->assertNull($photo->start_month);
        $this->assertNull($photo->start_day);
    }
}
