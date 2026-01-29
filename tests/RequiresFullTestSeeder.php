<?php

namespace Tests;

/**
 * Trait for tests that require the full TestDatabaseSeeder.
 * 
 * Use this trait when a test class needs production-like test data including:
 * - Test user with personal span
 * - User-span relationships
 * - Full span type metadata
 * 
 * Most tests only need MinimalTestSeeder (just span types), so this should
 * be used sparingly to keep test performance optimal.
 * 
 * Example:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use RequiresFullTestSeeder;
 *     
 *     // Test will use full seeder with test user and personal span
 * }
 * ```
 */
trait RequiresFullTestSeeder
{
    /**
     * Flag to indicate this test class requires the full test seeder.
     * 
     * @var bool
     */
    protected bool $useFullTestSeeder = true;
}
