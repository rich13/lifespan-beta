<?php

namespace Tests\Support\Testing;

use Illuminate\Testing\TestResponse as BaseTestResponse;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\Support\Constraints\SeeInOrderWithTruncatedFailure;

/**
 * Test response that avoids dumping entire HTML pages in assertion failures.
 */
class TestResponse extends BaseTestResponse
{
    public function assertSeeInOrder(array $values, $escape = true)
    {
        $values = $escape ? array_map('e', $values) : $values;

        PHPUnit::assertThat($values, new SeeInOrderWithTruncatedFailure($this->getContent()));

        return $this;
    }

    public function assertSeeTextInOrder(array $values, $escape = true)
    {
        $values = $escape ? array_map('e', $values) : $values;

        PHPUnit::assertThat($values, new SeeInOrderWithTruncatedFailure(strip_tags($this->getContent())));

        return $this;
    }
}
