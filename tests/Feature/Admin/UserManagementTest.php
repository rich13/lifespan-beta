<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Span;
use App\Models\InvitationCode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function admin_can_view_users_index_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.index');
        $response->assertSee('Users');
    }

    /** @test */
    public function non_admin_cannot_view_users_index_page()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_user_details()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->regularUser));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.show');
        $response->assertSee($this->regularUser->email);
    }

    /** @test */
    public function admin_can_edit_user()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->regularUser));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.edit');
    }

    /** @test */
    public function admin_can_update_user()
    {
        $newEmail = 'updated@example.com';
        
        $response = $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->regularUser), [
                'email' => $newEmail,
                'is_admin' => false
            ]);

        $response->assertRedirect(route('admin.users.show', $this->regularUser));
        $response->assertSessionHas('status', 'User updated successfully');
        
        $this->regularUser->refresh();
        $this->assertEquals($newEmail, $this->regularUser->email);
    }

    /** @test */
    public function admin_can_make_user_admin()
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->regularUser), [
                'is_admin' => true
            ]);

        $response->assertRedirect(route('admin.users.show', $this->regularUser));
        
        $this->regularUser->refresh();
        $this->assertTrue($this->regularUser->is_admin);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->regularUser));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'User and their spans deleted successfully.');
        
        $this->assertDatabaseMissing('users', ['id' => $this->regularUser->id]);
    }

    /** @test */
    public function admin_cannot_delete_another_admin()
    {
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $otherAdmin));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Administrator accounts cannot be deleted.');
        
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    /** @test */
    public function admin_can_generate_invitation_codes()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.generate-invitation-codes'));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'Generated 10 new invitation codes');
        $response->assertSessionHas('new_codes');
        
        $this->assertEquals(10, InvitationCode::count());
    }

    /** @test */
    public function admin_can_delete_all_invitation_codes()
    {
        // Clear any existing invitation codes first
        InvitationCode::truncate();
        
        // Create some invitation codes first
        InvitationCode::factory()->count(5)->create();
        
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.delete-all-invitation-codes'));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'Deleted all 5 invitation codes');
        
        $this->assertEquals(0, InvitationCode::count());
    }

    /** @test */
    public function admin_can_create_user_from_existing_span()
    {
        // Create a span that could be converted to a personal span
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->admin->id, // Admin owns it initially
            'is_personal_span' => false,
            'access_level' => 'public'
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $span->id,
                'email' => 'john.doe@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'User created successfully from span');

        // Check that the user was created
        $newUser = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertFalse($newUser->is_admin);

        // Check that the span was converted to a personal span
        $span->refresh();
        $this->assertTrue($span->is_personal_span);
        $this->assertEquals($newUser->id, $span->owner_id);
        $this->assertEquals($span->id, $newUser->personal_span_id);

        // Check that the span is now private
        $this->assertEquals('private', $span->access_level);
    }

    /** @test */
    public function admin_cannot_create_user_from_span_that_is_already_personal()
    {
        $personalSpan = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->regularUser->id,
            'is_personal_span' => true
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $personalSpan->id,
                'email' => 'john.doe2@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

        $response->assertSessionHasErrors(['span_id']);
    }

    /** @test */
    public function admin_cannot_create_user_from_non_person_span()
    {
        $organisationSpan = Span::factory()->create([
            'type_id' => 'organisation',
            'name' => 'Test Organisation',
            'owner_id' => $this->admin->id,
            'is_personal_span' => false
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $organisationSpan->id,
                'email' => 'john.doe3@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

        $response->assertSessionHasErrors(['span_id']);
    }

    /** @test */
    public function admin_can_view_create_user_from_span_form()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.create-from-span'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.create-from-span');
        $response->assertSee('Create User from Span');
    }

    /** @test */
    public function create_user_form_shows_search_interface()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.create-from-span'));

        $response->assertStatus(200);
        $response->assertSee('Search for a person span');
        $response->assertSee('Search for person spans that are not already personal spans');
        $response->assertSee('span_search');
        $response->assertSee('search_results');
    }

    /** @test */
    public function create_user_form_excludes_personal_spans()
    {
        // Create a personal span
        $personalSpan = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Personal User',
            'owner_id' => $this->regularUser->id,
            'is_personal_span' => true
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.create-from-span'));

        $response->assertStatus(200);
        $response->assertDontSee($personalSpan->name);
    }

    /** @test */
    public function create_user_requires_valid_email()
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->admin->id,
            'is_personal_span' => false
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $span->id,
                'email' => 'invalid-email',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function create_user_requires_unique_email()
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->admin->id,
            'is_personal_span' => false
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $span->id,
                'email' => $this->regularUser->email, // Already exists
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function create_user_requires_matching_passwords()
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->admin->id,
            'is_personal_span' => false
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $span->id,
                'email' => 'john.doe4@example.com',
                'password' => 'password123',
                'password_confirmation' => 'differentpassword'
            ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function create_user_requires_minimum_password_length()
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'owner_id' => $this->admin->id,
            'is_personal_span' => false
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store-from-span'), [
                'span_id' => $span->id,
                'email' => 'john.doe5@example.com',
                'password' => '123',
                'password_confirmation' => '123'
            ]);

        $response->assertSessionHasErrors(['password']);
    }
}
