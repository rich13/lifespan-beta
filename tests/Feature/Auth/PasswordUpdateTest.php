<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Note: Password updates are handled through the email-first auth flow
 * These tests have been removed as they were for the standard Laravel auth system
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;
}
