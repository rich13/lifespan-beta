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