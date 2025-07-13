<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use App\Models\Span;

class ProfileRoutesTest extends TestCase
{

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'password' => Hash::make('password')
        ]);
    }

    public function test_profile_edit_requires_auth(): void
    {
        $response = $this->get('/profile');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_profile_edit_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/profile');

        $response->assertStatus(200);
        $response->assertViewIs('profile.edit');
    }

    public function test_profile_update_requires_auth(): void
    {
        $response = $this->patch('/profile', [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_profile_update_works_when_authenticated(): void
    {
        // Create a personal span for the user
        $personalSpan = Span::factory()->personal($this->user)->create();
        $this->user->personal_span_id = $personalSpan->id;
        $this->user->save();

        $response = $this->actingAs($this->user)
            ->patch('/profile', [
                'name' => 'Updated Name',
                'email' => 'newemail@example.com'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'newemail@example.com'
        ]);
        $this->assertDatabaseHas('spans', [
            'id' => $this->user->personal_span_id,
            'name' => 'Updated Name'
        ]);
    }

    public function test_password_update_requires_auth(): void
    {
        $response = $this->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_password_update_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword'
            ]);

        $response->assertStatus(302);
        $this->assertTrue(Hash::check('newpassword', $this->user->fresh()->password));
    }

    public function test_profile_delete_requires_auth(): void
    {
        $response = $this->delete('/profile');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_profile_delete_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->delete('/profile', [
                'password' => 'password'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('users', [
            'id' => $this->user->id
        ]);
    }

    public function test_logout_requires_auth(): void
    {
        $response = $this->post('/logout');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_logout_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/logout');

        $response->assertStatus(302);
        $response->assertRedirect('/');
    }
} 