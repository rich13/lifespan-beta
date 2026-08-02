<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\TestHelpers;

class SpanFilterSearchTest extends TestCase
{
    use WithFaker, TestHelpers;

    protected User $user;

    protected Span $personSpan;

    protected Span $organisationSpan;

    protected Span $placeSpan;

    protected Span $eventSpan;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $types = ['person', 'organisation', 'place', 'event'];
        foreach ($types as $type) {
            if (! DB::table('span_types')->where('type_id', $type)->exists()) {
                DB::table('span_types')->insert([
                    'type_id' => $type,
                    'name' => ucfirst($type),
                    'description' => 'A test '.$type.' type',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Unique token so assertions stay reliable even when other suite data shares the DB
        // (PostgresRefreshDatabase per-class cleaning is not currently wired into TestCase).
        $this->token = 'Flt'.uniqid('', false);

        $this->user = User::factory()->create();

        // Early start years keep these on page 1 of the index when the DB is polluted.
        $this->personSpan = Span::create([
            'name' => "Person {$this->token} Northover",
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 101,
            'slug' => $this->uniqueSlug('person-'.$this->token),
            'access_level' => 'public',
            'description' => "Unique person description {$this->token}",
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
            'is_personal_span' => false,
        ]);

        $this->organisationSpan = Span::create([
            'name' => "Org {$this->token} Corporation",
            'type_id' => 'organisation',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 102,
            'slug' => $this->uniqueSlug('org-'.$this->token),
            'access_level' => 'public',
            'description' => "Organisation where Person {$this->token} Northover works",
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        $this->placeSpan = Span::create([
            'name' => "Place {$this->token} Bridge",
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 100,
            'slug' => $this->uniqueSlug('place-'.$this->token),
            'access_level' => 'public',
            'description' => "A bridge near {$this->token}",
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        $this->eventSpan = Span::create([
            'name' => "Event {$this->token} Picnic",
            'type_id' => 'event',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 103,
            'slug' => $this->uniqueSlug('event-'.$this->token),
            'access_level' => 'public',
            'description' => "Picnic at Place {$this->token} Bridge",
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);
    }

    public function test_type_filters(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/spans/?types=person');
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);
        $response->assertDontSee($this->organisationSpan->name);
        $response->assertDontSee($this->placeSpan->name);

        $response = $this->get('/spans/?types=person,organisation');
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);
        $response->assertSee($this->organisationSpan->name);
        $response->assertDontSee($this->placeSpan->name);
    }

    public function test_search_functionality(): void
    {
        $this->actingAs($this->user);

        $this->assertDatabaseHas('spans', ['name' => $this->personSpan->name]);
        $this->assertDatabaseHas('spans', ['name' => $this->organisationSpan->name]);

        $response = $this->get(route('spans.index', ['search' => $this->token]));
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);
        $response->assertSee($this->organisationSpan->name);

        $response = $this->get(route('spans.index', ['search' => strtolower($this->token)]));
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);
        $response->assertSee($this->organisationSpan->name);

        $response = $this->get('/spans/?search='.urlencode($this->personSpan->name));
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);

        $parts = explode(' ', $this->personSpan->name);
        $response = $this->get('/spans/?search='.urlencode($parts[2].' '.$parts[0].' '.$parts[1]));
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);

        $response = $this->get('/spans/?search='.urlencode($this->organisationSpan->name));
        $response->assertStatus(200);
        $response->assertSee($this->organisationSpan->name);
    }

    public function test_combined_filtering(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/spans/?search='.urlencode($this->token).'&types=person');
        $response->assertStatus(200);
        $response->assertSee($this->personSpan->name);
        $response->assertDontSee($this->organisationSpan->name);

        $response = $this->get('/spans/?search='.urlencode($this->token).'&types=place');
        $response->assertStatus(200);
        $response->assertSee($this->placeSpan->name);
        $response->assertDontSee($this->personSpan->name);
    }

    public function test_search_edge_cases(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/spans/?search=NonexistentTerm'.uniqid());
        $response->assertStatus(200);
        $response->assertSee('No spans found');

        $response = $this->get('/spans/?search='.urlencode($this->token."'s"));
        $response->assertStatus(200);
    }

    public function test_search_and_filter_ui_elements(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/spans/');
        $response->assertStatus(200);
        $response->assertSee('filter_person', false);
        $response->assertSee('filter_organisation', false);
        $response->assertSee('filter_place', false);
        $response->assertSee('filter_event', false);

        $response = $this->get('/spans?types=person');
        $response->assertStatus(200);
        $response->assertSee('btn-primary', false);
    }
}
