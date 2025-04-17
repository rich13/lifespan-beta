<?php

namespace Tests;

trait TestHelpers
{
    /**
     * Generate a unique string for use in tests.
     *
     * @param string $prefix
     * @return string
     */
    protected function uniqueString(string $prefix = ''): string
    {
        return $prefix . uniqid('test_') . '_' . microtime(true);
    }

    /**
     * Generate a unique email for use in tests.
     *
     * @param string $domain
     * @return string
     */
    protected function uniqueEmail(string $domain = 'example.com'): string
    {
        return 'test_' . uniqid() . '@' . $domain;
    }

    /**
     * Generate a unique slug for use in tests.
     *
     * @param string $base
     * @return string
     */
    protected function uniqueSlug(string $base = 'test'): string
    {
        return strtolower($base . '-' . uniqid() . '-' . substr(md5(microtime()), 0, 6));
    }
} 