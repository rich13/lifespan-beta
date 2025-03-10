<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserSwitcherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin users can access the user switcher API.
     */
    public function test_admin_users_can_access_user_switcher_api(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // Act as the admin user
        $response = $this->actingAs($admin)->get('/admin/user-switcher/users');

        // Assert that the admin can access the user switcher API
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'email',
            ]
        ]);
    }

    /**
     * Test that non-admin users cannot access the user switcher API.
     */
    public function test_non_admin_users_cannot_access_user_switcher_api(): void
    {
        // Create a regular user
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Act as the regular user
        $response = $this->actingAs($user)->get('/admin/user-switcher/users');

        // Assert that the regular user cannot access the user switcher API
        $response->assertStatus(403);
    }

    /**
     * Test that admin users can switch to another user.
     */
    public function test_admin_users_can_switch_to_another_user(): void
    {
        // Create an admin user and a regular user
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Act as the admin user and try to switch to the regular user
        $response = $this->actingAs($admin)
            ->post('/admin/user-switcher/switch/' . $user->id);

        // Assert that the switch was successful
        $response->assertRedirect();
        $response->assertSessionHas('admin_user_id', $admin->id);
        
        // Assert that we're now logged in as the regular user
        $this->assertEquals($user->id, auth()->id());
    }

    /**
     * Test that non-admin users cannot switch to another user.
     */
    public function test_non_admin_users_cannot_switch_to_another_user(): void
    {
        // Create two regular users
        $user1 = User::factory()->create([
            'is_admin' => false,
        ]);
        
        $user2 = User::factory()->create([
            'is_admin' => false,
        ]);

        // Act as the first regular user and try to switch to the second regular user
        $response = $this->actingAs($user1)
            ->post('/admin/user-switcher/switch/' . $user2->id);

        // Assert that the switch was not successful
        $response->assertStatus(403);
        $response->assertSessionMissing('admin_user_id');
        
        // Assert that we're still logged in as the first regular user
        $this->assertEquals($user1->id, auth()->id());
    }

    /**
     * Test that a switched user can switch back to the admin.
     */
    public function test_switched_user_can_switch_back_to_admin(): void
    {
        // Create an admin user and a regular user
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Set up the session as if we've already switched from admin to regular user
        $this->actingAs($user);
        session(['admin_user_id' => $admin->id]);

        // Try to switch back to the admin
        $response = $this->post('/admin/user-switcher/switch-back');

        // Assert that the switch back was successful
        $response->assertRedirect();
        $response->assertSessionMissing('admin_user_id');
        
        // Assert that we're now logged in as the admin
        $this->assertEquals($admin->id, auth()->id());
    }

    /**
     * Test that the user switcher UI is present for admin users.
     */
    public function test_user_switcher_ui_is_present_for_admin_users(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // Act as the admin user and visit the home page
        $response = $this->actingAs($admin)->get('/');

        // Assert that the user switcher UI is present
        $response->assertSee('SWITCH TO USER');
        $response->assertSee('userSwitcherList');
    }

    /**
     * Test that the user switcher UI is not present for non-admin users.
     */
    public function test_user_switcher_ui_is_not_present_for_non_admin_users(): void
    {
        // Create a regular user
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Act as the regular user and visit the home page
        $response = $this->actingAs($user)->get('/');

        // Assert that the user switcher UI is not present
        $response->assertDontSee('SWITCH TO USER');
        $response->assertDontSee('userSwitcherList');
    }
}
