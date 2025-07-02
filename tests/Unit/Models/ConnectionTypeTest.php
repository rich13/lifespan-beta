<?php

namespace Tests\Unit\Models;

use App\Models\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionTypeTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = ConnectionType::factory()->create([
            'type' => 'test_employment_type',
            'forward_predicate' => 'worked at',
            'inverse_predicate' => 'employed',
            'allowed_span_types' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ]
        ]);
    }

    /** @test */
    public function it_provides_subject_object_span_types()
    {
        // Test new subject/object methods
        $this->assertEquals(['person'], $this->type->getAllowedSubjectTypes());
        $this->assertEquals(['organisation'], $this->type->getAllowedObjectTypes());
    }

    /** @test */
    public function it_maintains_backwards_compatibility_for_span_types()
    {
        // Test that old parent/child method still works
        $this->assertEquals(['person'], $this->type->getAllowedSpanTypes('parent'));
        $this->assertEquals(['organisation'], $this->type->getAllowedSpanTypes('child'));
    }

    /** @test */
    public function it_formats_example_using_subject_object()
    {
        // TODO: Fix test isolation issue - factory creates duplicate primary keys
        // Error: SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "connection_types_pkey"
        // This appears to be a database transaction rollback issue in the test suite
        
        $this->assertEquals(
            'Subject worked at Object',
            $this->type->getExample(false)
        );

        $this->assertEquals(
            'Object employed Subject',
            $this->type->getExample(true)
        );
    }
} 