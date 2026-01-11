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
            'code' => 'test-code-' . uniqid(),
            'used' => false,
        ]);

        // Register a user with unthinkabledigital.co.uk email
        $uniqueEmail = 'test-' . uniqid() . '@unthinkabledigital.co.uk';
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code',
        ]);

        $response->assertRedirect(route('register.pending'));
        
        // Find the newly created user
        $user = User::where('email', $uniqueEmail)->first();
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
            'code' => 'test-code-2-' . uniqid(),
            'used' => false,
        ]);

        // Register a user with a different email domain
        $uniqueEmail = 'external-' . uniqid() . '@example.com';
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-2',
        ]);

        $response->assertRedirect(route('register.pending'));
        
        // Find the newly created user
        $user = User::where('email', $uniqueEmail)->first();
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
            'code' => 'test-code-3-' . uniqid(),
            'used' => false,
        ]);

        // Register a user with unthinkabledigital.co.uk email
        $uniqueEmail = 'another-' . uniqid() . '@unthinkabledigital.co.uk';
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-3',
        ]);

        // Registration should still succeed (redirects to pending approval)
        $response->assertRedirect(route('register.pending'));
        
        // Verify the user was created
        $user = User::where('email', $uniqueEmail)->first();
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
            'code' => 'test-code-4-' . uniqid(),
            'used' => false,
        ]);

        // Register a user with mixed case email
        $uniqueId = uniqid();
        $uniqueEmail = 'MixedCase' . $uniqueId . '@UnthinkableDigital.Co.Uk';
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'test-code-4',
        ]);

        $response->assertRedirect(route('register.pending'));
        
        // Find the newly created user (email is stored as entered)
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user);

        // Verify the user was added to the Unthinkable group (case-insensitive match)
        $this->assertTrue($group->hasMember($user));
    }
}

