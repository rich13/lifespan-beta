<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class SimpleTest extends TestCase
{
    public function test_example(): void
    {
        Log::channel('testing')->info('Starting simple test');
        $result = 1 + 1;
        Log::channel('testing')->info('Test calculation result', ['result' => $result]);
        $this->assertEquals(2, $result);
        Log::channel('testing')->info('Test completed successfully');
    }
} 