<?php

namespace Tests\Feature;

use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SanitiseSpanNamesCommandTest extends TestCase
{
    public function test_command_replaces_line_breaks_in_existing_names(): void
    {
        $span = Span::factory()->create(['name' => 'Placeholder']);

        DB::table('spans')->where('id', $span->id)->update([
            'name' => "Middleton Hall Lane\nBrentwood, Essex, CM15 8EE\nEngland",
        ]);

        $this->artisan('spans:sanitise-names')
            ->assertSuccessful();

        $this->assertSame(
            'Middleton Hall Lane Brentwood, Essex, CM15 8EE England',
            $span->fresh()->name
        );
    }

    public function test_dry_run_does_not_change_names(): void
    {
        $span = Span::factory()->create(['name' => 'Placeholder']);
        $polluted = "Middleton Hall Lane\nEngland";

        DB::table('spans')->where('id', $span->id)->update([
            'name' => $polluted,
        ]);

        $this->artisan('spans:sanitise-names', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($polluted, $span->fresh()->getAttributes()['name']);
    }
}
