<?php

namespace Tests\Support\Concerns;

use Illuminate\Testing\TestResponse;

trait TruncatesResponseBodies
{
    protected function truncateForTestOutput(string $body, int $maxLength = 500): string
    {
        $normalised = preg_replace('/\s+/', ' ', trim($body)) ?? '';

        if ($normalised === '') {
            return '(empty body)';
        }

        if (mb_strlen($normalised) <= $maxLength) {
            return $normalised;
        }

        $head = mb_substr($normalised, 0, (int) floor($maxLength * 0.7));
        $tail = mb_substr($normalised, - (int) floor($maxLength * 0.2));

        return sprintf('%s … [%d bytes total] … %s', $head, strlen($body), $tail);
    }

    protected function assertSameResponseContent(string $expected, string $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->assertTrue(true);

            return;
        }

        $prefix = $message !== '' ? "{$message}\n" : '';

        $this->fail($prefix.sprintf(
            "Response bodies differ (expected %d bytes, actual %d bytes).\nExpected excerpt: %s\nActual excerpt: %s",
            strlen($expected),
            strlen($actual),
            $this->truncateForTestOutput($expected),
            $this->truncateForTestOutput($actual),
        ));
    }

    /**
     * @param  list<string>  $errorMarkers
     */
    protected function assertResponseBodyHasNoErrorMarkers(
        TestResponse $response,
        array $errorMarkers,
        string $context,
    ): void {
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $content = $response->getContent();

        foreach ($errorMarkers as $marker) {
            if (str_contains($content, $marker)) {
                $this->fail(sprintf(
                    '%s: body contains forbidden substring "%s". Excerpt: %s',
                    $context,
                    $marker,
                    $this->truncateForTestOutput($content),
                ));
            }
        }
    }

    protected function failWithResponseSummary(TestResponse $response, string $message): void
    {
        $this->fail(sprintf(
            '%s (status %d). Body excerpt: %s',
            $message,
            $response->getStatusCode(),
            $this->truncateForTestOutput($response->getContent()),
        ));
    }
}
