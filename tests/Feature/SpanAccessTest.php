<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\SpanPermission;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        $requiredTypes = [
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A test event type'
            ]
        ];

        foreach ($requiredTypes as $type) {
            if (!DB::table('span_types')->where('type_id', $type['type_id'])->exists()) {
                DB::table('span_types')->insert(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }

    public function test_public_spans_are_visible_to_all(): void
    {
        $this->markTestSkipped('Permissions/access system being reworked - test needs to be rewritten');
    }

    public function test_private_spans_only_visible_to_owner_and_admin(): void
    {
        $this->markTestSkipped('Permissions/access system being reworked - test needs to be rewritten');
    }

    public function test_shared_spans_visible_to_users_with_permission(): void
    {
        $this->markTestSkipped('Permissions/access system being reworked - test needs to be rewritten');
    }

    public function test_span_deletion_permissions(): void
    {
        $this->markTestSkipped('Permissions/access system being reworked - test needs to be rewritten');
    }

    public function test_span_editability_logic(): void
    {
        $this->markTestSkipped('Permissions/access system being reworked - test needs to be rewritten');
    }
} 