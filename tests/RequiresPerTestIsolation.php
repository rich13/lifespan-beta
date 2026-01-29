<?php

namespace Tests;

/**
 * Trait for tests that require complete database isolation between test methods.
 * 
 * Use this trait when a test class needs a clean database state for each test method,
 * rather than sharing state within the test class. This comes with a performance cost
 * as it triggers full table truncation and seeding before each test.
 * 
 * Example:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use RequiresPerTestIsolation;
 *     
 *     // Each test method will get a fresh database
 * }
 * ```
 */
trait RequiresPerTestIsolation
{
    /**
     * Flag to indicate this test class requires per-test isolation.
     * 
     * @var bool
     */
    protected bool $requiresPerTestIsolation = true;
}
