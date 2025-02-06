<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class SimpleTest extends TestCase
{
    public function test_basic_math(): void
    {
        ray()->green('Starting test');
        
        $result = 1 + 1;
        ray()->blue()->text('Test calculation result:')->send($result);
        
        $this->assertTrue($result === 2, 'Basic math should work');
        
        ray()->purple('Test completed successfully')
            ->notify('Test Complete');  // Should show a system notification
    }
} 