<?php

namespace Tests\Support\Constraints;

use Illuminate\Testing\Constraints\SeeInOrder;
use Tests\Support\Concerns\TruncatesResponseBodies;

/**
 * Like Laravel's SeeInOrder, but failure messages include a short body excerpt
 * instead of the entire HTML response.
 */
class SeeInOrderWithTruncatedFailure extends SeeInOrder
{
    use TruncatesResponseBodies;

    public function failureDescription($values): string
    {
        $content = $this->extractContentForTruncation();

        return sprintf(
            'Failed asserting that response body (length %d) contains "%s" in specified order. Body excerpt: %s',
            strlen($content),
            $this->extractFailedValue($values),
            $this->truncateForTestOutput($content),
        );
    }

    /**
     * SeeInOrder stores content in a protected property without an accessor.
     */
    private function extractContentForTruncation(): string
    {
        $reflection = new \ReflectionClass(SeeInOrder::class);
        $property = $reflection->getProperty('content');
        $property->setAccessible(true);

        return (string) $property->getValue($this);
    }

    private function extractFailedValue($values): string
    {
        $reflection = new \ReflectionClass(SeeInOrder::class);
        $property = $reflection->getProperty('failedValue');
        $property->setAccessible(true);

        return (string) $property->getValue($this);
    }
}
