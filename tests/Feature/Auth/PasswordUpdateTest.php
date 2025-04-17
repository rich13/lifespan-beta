<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Note: Password updates are handled through the email-first auth flow
 * These tests have been removed as they were for the standard Laravel auth system
 */
class PasswordUpdateTest extends TestCase
{
    /**
     * Test password update
     */
    public function test_password_update_feature(): void
    {
        $this->markTestSkipped(
            'Password update feature is not implemented.'
        );
    }
}
