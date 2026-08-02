<?php

namespace Tests\Support\Testing;

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse as BaseTestResponse;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\Support\Concerns\TruncatesResponseBodies;
use Tests\Support\Constraints\SeeInOrderWithTruncatedFailure;

/**
 * Test response that avoids dumping entire HTML pages in assertion failures.
 */
class TestResponse extends BaseTestResponse
{
    use TruncatesResponseBodies;

    public function assertSee($value, $escape = true)
    {
        $value = Arr::wrap($value);
        $values = $escape ? array_map('e', $value) : $value;
        $content = $this->getContent();

        foreach ($values as $needle) {
            PHPUnit::assertTrue(
                str_contains($content, (string) $needle),
                sprintf(
                    'Failed asserting that response body (length %d) contains "%s". Body excerpt: %s',
                    strlen($content),
                    $needle,
                    $this->truncateForTestOutput($content),
                )
            );
        }

        return $this;
    }

    public function assertDontSee($value, $escape = true)
    {
        $value = Arr::wrap($value);
        $values = $escape ? array_map('e', $value) : $value;
        $content = $this->getContent();

        foreach ($values as $needle) {
            PHPUnit::assertTrue(
                ! str_contains($content, (string) $needle),
                sprintf(
                    'Failed asserting that response body (length %d) does not contain "%s". Body excerpt: %s',
                    strlen($content),
                    $needle,
                    $this->truncateForTestOutput($content),
                )
            );
        }

        return $this;
    }

    public function assertSeeText($value, $escape = true)
    {
        $value = Arr::wrap($value);
        $values = $escape ? array_map('e', $value) : $value;
        $content = strip_tags($this->getContent());

        foreach ($values as $needle) {
            PHPUnit::assertTrue(
                str_contains($content, (string) $needle),
                sprintf(
                    'Failed asserting that response text (length %d) contains "%s". Body excerpt: %s',
                    strlen($content),
                    $needle,
                    $this->truncateForTestOutput($content),
                )
            );
        }

        return $this;
    }

    public function assertDontSeeText($value, $escape = true)
    {
        $value = Arr::wrap($value);
        $values = $escape ? array_map('e', $value) : $value;
        $content = strip_tags($this->getContent());

        foreach ($values as $needle) {
            PHPUnit::assertTrue(
                ! str_contains($content, (string) $needle),
                sprintf(
                    'Failed asserting that response text (length %d) does not contain "%s". Body excerpt: %s',
                    strlen($content),
                    $needle,
                    $this->truncateForTestOutput($content),
                )
            );
        }

        return $this;
    }

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
