<?php

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\InvitationCode;
use App\Models\User;
use Tests\TestCase;

class DomainGroupAssignmentTest extends TestCase
{
    public function test_user_with_unthinkable_email_is_added_to_group(): void
    {
        // Clean up any existing Unthinkable groups from previous tests
        Group::where('name', 'Unthinkable')->delete();
        
        // Create the Unthinkable group
        $adminUser = User::factory()->create(['is_admin' => true]);
        $group = Group::create([
            'name' => 'Unthinkable',
            'description' => 'Unthinkable Digital team members',
            'owner_id' => $adminUser->id,
        ]);

        // Create a valid invitation code
        $code = InvitationCode::create([
            'code' => 'test-code',
            'used' => false,
        ]);

        // Register a user with unthinkabledigital.co.uk email
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@unthinkabledigital.co.uk',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code',
            'birth_year' => 1990,
            'birth_month' => 6,
            'birth_day' => 15,
        ]);

        $response->assertRedirect('/');
        
        // Find the newly created user
        $user = User::where('email', 'test@unthinkabledigital.co.uk')->first();
        $this->assertNotNull($user);

        // Verify the user was added to the Unthinkable group
        $this->assertTrue($group->hasMember($user));
        $this->assertTrue($user->isMemberOf($group));
    }

    public function test_user_with_different_email_is_not_added_to_unthinkable_group(): void
    {
        // Clean up any existing Unthinkable groups from previous tests
        Group::where('name', 'Unthinkable')->delete();
        
        // Create the Unthinkable group
        $adminUser = User::factory()->create(['is_admin' => true]);
        $group = Group::create([
            'name' => 'Unthinkable',
            'description' => 'Unthinkable Digital team members',
            'owner_id' => $adminUser->id,
        ]);

        // Create a valid invitation code
        $code = InvitationCode::create([
            'code' => 'test-code-2',
            'used' => false,
        ]);

        // Register a user with a different email domain
        $response = $this->post('/register', [
            'name' => 'External User',
            'email' => 'external@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-2',
            'birth_year' => 1985,
            'birth_month' => 3,
            'birth_day' => 20,
        ]);

        $response->assertRedirect('/');
        
        // Find the newly created user
        $user = User::where('email', 'external@example.com')->first();
        $this->assertNotNull($user);

        // Verify the user was NOT added to the Unthinkable group
        $this->assertFalse($group->hasMember($user));
        $this->assertFalse($user->isMemberOf($group));
    }

    public function test_registration_succeeds_even_if_unthinkable_group_does_not_exist(): void
    {
        // Ensure the Unthinkable group doesn't exist (delete any that might exist from other tests)
        Group::where('name', 'Unthinkable')->delete();

        // Create a valid invitation code
        $code = InvitationCode::create([
            'code' => 'test-code-3',
            'used' => false,
        ]);

        // Register a user with unthinkabledigital.co.uk email
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'another@unthinkabledigital.co.uk',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-3',
            'birth_year' => 1995,
            'birth_month' => 12,
            'birth_day' => 25,
        ]);

        // Registration should still succeed
        $response->assertRedirect('/');
        
        // Verify the user was created
        $user = User::where('email', 'another@unthinkabledigital.co.uk')->first();
        $this->assertNotNull($user);
    }

    public function test_email_matching_is_case_insensitive(): void
    {
        // Clean up any existing Unthinkable groups from previous tests
        Group::where('name', 'Unthinkable')->delete();
        
        // Create the Unthinkable group
        $adminUser = User::factory()->create(['is_admin' => true]);
        $group = Group::create([
            'name' => 'Unthinkable',
            'description' => 'Unthinkable Digital team members',
            'owner_id' => $adminUser->id,
        ]);

        // Create a valid invitation code
        $code = InvitationCode::create([
            'code' => 'test-code-4',
            'used' => false,
        ]);

        // Register a user with mixed case email
        $response = $this->post('/register', [
            'name' => 'Mixed Case User',
            'email' => 'MixedCase@UnthinkableDigital.Co.Uk',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-4',
            'birth_year' => 1988,
            'birth_month' => 9,
            'birth_day' => 10,
        ]);

        $response->assertRedirect('/');
        
        // Find the newly created user (email is stored as entered)
        $user = User::where('email', 'MixedCase@UnthinkableDigital.Co.Uk')->first();
        $this->assertNotNull($user);

        // Verify the user was added to the Unthinkable group (case-insensitive match)
        $this->assertTrue($group->hasMember($user));
    }
}

