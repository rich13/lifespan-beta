<?php

namespace Tests\Hygiene;

use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;

class RouteHealthTest extends \Tests\TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $regularUser;
    protected $testSpan;
    protected $testConnection;
    protected $testConnectionType;
    protected $testSpanType;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
        
        // Create test span
        $this->testSpan = Span::factory()->create([
            'name' => 'Test Span',
            'type_id' => 'thing',
            'owner_id' => $this->regularUser->id,
            'access_level' => 'public'
        ]);
        
        // Create test connection type
        $this->testConnectionType = ConnectionType::firstOrCreate([
            'type' => 'test_connection',
        ], [
            'name' => 'Test Connection',
            'forward_predicate' => 'connects to',
            'forward_description' => 'A test connection',
            'inverse_predicate' => 'connected by',
            'inverse_description' => 'A test inverse connection'
        ]);
        
        // Create test span type
        $this->testSpanType = SpanType::firstOrCreate([
            'type_id' => 'test_type',
        ], [
            'name' => 'Test Type',
            'description' => 'A test span type'
        ]);
        
        // Create test connection
        $this->testConnection = Connection::factory()->create([
            'parent_id' => $this->testSpan->id,
            'child_id' => Span::factory()->create()->id,
            'type_id' => $this->testConnectionType->type
        ]);
    }

    /**
     * Test all public routes return successful responses
     */
    public function test_public_routes_return_successful_responses()
    {
        $publicRoutes = [
            '/',
            '/health',
            '/login',
            '/register',
            '/email/verify',
            '/auth/email',
            '/auth/password',
            '/spans',
            '/spans/search',
            '/spans/types',
            '/spans/types/' . $this->testSpanType->type_id,
            '/spans/types/' . $this->testSpanType->type_id . '/subtypes',
            '/spans/' . $this->testSpan->id,
            '/spans/' . $this->testSpan->id . '/story',
            '/spans/' . $this->testSpan->id . '/history',
            '/sets',
            '/sets/' . $this->testSpan->id,
            '/family',
            '/family/data',
            '/friends',
            '/friends/data',
            '/desert-island-discs',
            '/date/2024-01-01',
            '/debug',
            '/error',
            '/api/user',
            '/api/spans/search',
            '/api/spans/' . $this->testSpan->id,
            '/api/spans/' . $this->testSpan->id . '/during-connections',
            '/api/spans/' . $this->testSpan->id . '/object-connections',

            '/api/sets/containing/' . $this->testSpan->id,
            '/api/sets/' . $this->testSpan->id . '/membership/' . $this->testSpan->id,
            '/api/wikipedia/on-this-day/1/1',
        ];

        foreach ($publicRoutes as $route) {
            $response = $this->get($route);
            
            // Accept 200 (OK), 302 (Redirect), 401 (Unauthorized), 403 (Forbidden), 404 (Not Found)
            // But fail on 500 (Server Error) or other error codes
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "Route {$route} returned 500 error");
            $this->assertNotEquals(0, $response->getStatusCode(), 
                "Route {$route} returned 0 status code");
            
            // Check for common error strings in response content
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringNotContainsString('Call to undefined method', $content,
                    "Route {$route} contains 'Call to undefined method' error");
                $this->assertStringNotContainsString('Fatal error', $content,
                    "Route {$route} contains fatal error");
                $this->assertStringNotContainsString('Parse error', $content,
                    "Route {$route} contains parse error");
                $this->assertStringNotContainsString('Class not found', $content,
                    "Route {$route} contains 'Class not found' error");
            }
        }
    }

    /**
     * Test all admin routes return successful responses when authenticated as admin
     */
    public function test_admin_routes_return_successful_responses()
    {
        $this->actingAs($this->adminUser);

        $adminRoutes = [
            '/admin',
            '/admin/spans',
            '/admin/spans/' . $this->testSpan->id,
            '/admin/spans/' . $this->testSpan->id . '/edit',
            '/admin/spans/' . $this->testSpan->id . '/access',
            '/admin/spans/' . $this->testSpan->id . '/permissions',
            '/admin/span-types',
            '/admin/span-types/' . $this->testSpanType->type_id,
            '/admin/span-types/' . $this->testSpanType->type_id . '/edit',
            '/admin/connection-types',
            '/admin/connection-types/' . $this->testConnectionType->type,
            '/admin/connection-types/' . $this->testConnectionType->type . '/edit',
            '/admin/admin-connections',
            '/admin/admin-connections/' . $this->testConnection->id,
            '/admin/admin-connections/' . $this->testConnection->id . '/edit',
            '/admin/users',
            '/admin/users/' . $this->regularUser->id,
            '/admin/users/' . $this->regularUser->id . '/edit',
            '/admin/import',
            '/admin/import/' . $this->testSpan->id,
            '/admin/import/desert-island-discs',
            '/admin/import/desert-island-discs/step-import',
            '/admin/import/musicbrainz',
            '/admin/import/parliament',
            '/admin/import/prime-ministers',
            '/admin/import/prime-ministers/recent',
            '/admin/import/simple-desert-island-discs',
            '/admin/import/simple-desert-island-discs/info',
            '/admin/data-export',
            '/admin/data-export/export-all',
            '/admin/data-export/stats',
            '/admin/data-import',
            '/admin/span-access',
            '/admin/system-history',
            '/admin/system-history/stats',
            '/admin/tools',
            '/admin/tools/find-similar-spans',
            '/admin/tools/make-things-public',
            '/admin/tools/prewarm-wikipedia-cache',
            '/admin/tools/span-details',
            '/admin/user-switcher/users',
            '/admin/visualizer',
            '/admin/visualizer/temporal',
            '/admin/ai-yaml-generator',
            '/admin/ai-yaml-generator/placeholders',
            '/admin/dev/components',
        ];

        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            
            // Accept 200 (OK), 302 (Redirect), 404 (Not Found)
            // But fail on 500 (Server Error) or other error codes
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "Admin route {$route} returned 500 error");
            $this->assertNotEquals(0, $response->getStatusCode(), 
                "Admin route {$route} returned 0 status code");
            
            // Check for common error strings in response content
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringNotContainsString('Call to undefined method', $content,
                    "Admin route {$route} contains 'Call to undefined method' error");
                $this->assertStringNotContainsString('Fatal error', $content,
                    "Admin route {$route} contains fatal error");
                $this->assertStringNotContainsString('Parse error', $content,
                    "Admin route {$route} contains parse error");
                $this->assertStringNotContainsString('Class not found', $content,
                    "Admin route {$route} contains 'Class not found' error");
            }
        }
    }

    /**
     * Test that admin routes redirect to login when not authenticated
     */
    public function test_admin_routes_redirect_when_not_authenticated()
    {
        $adminRoutes = [
            '/admin',
            '/admin/spans',
            '/admin/users',
        ];

        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            
            // Should redirect to login (302) or return 403 Forbidden
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403]),
                "Admin route {$route} should redirect (302) or forbid (403) when not authenticated, got {$response->getStatusCode()}"
            );
        }
    }

    /**
     * Test that admin routes forbid access when authenticated as non-admin
     */
    public function test_admin_routes_forbid_non_admin_users()
    {
        $this->actingAs($this->regularUser);

        $adminRoutes = [
            '/admin',
            '/admin/spans',
            '/admin/users',
        ];

        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            
            // Should return 403 Forbidden for non-admin users
            $this->assertEquals(403, $response->getStatusCode(),
                "Admin route {$route} should return 403 for non-admin users, got {$response->getStatusCode()}");
        }
    }

    /**
     * Test specific routes that might have the getCreator() error
     */
    public function test_spans_types_route_does_not_have_getcreator_error()
    {
        $response = $this->get('/spans/types');
        
        $this->assertNotEquals(500, $response->getStatusCode(), 
            "Route /spans/types returned 500 error");
        
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();
            $this->assertStringNotContainsString('getCreator', $content,
                "Route /spans/types contains 'getCreator' error");
            $this->assertStringNotContainsString('Call to undefined method', $content,
                "Route /spans/types contains 'Call to undefined method' error");
        }
    }

    /**
     * Test /spans/{span} with a valid and invalid span
     */
    public function test_spans_show_route_handles_valid_and_invalid_ids()
    {
        // Valid span
        $response = $this->get('/spans/' . $this->testSpan->id);
        $this->assertNotEquals(500, $response->getStatusCode(),
            "/spans/{id} returned 500 error for valid span");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 301, 302, 404]),
            "/spans/{id} should return 200, 301, 302, or 404, got {$response->getStatusCode()}");

        // Invalid span (random string)
        $response = $this->get('/spans/something');
        $this->assertNotEquals(500, $response->getStatusCode(),
            "/spans/something returned 500 error");
        $this->assertEquals(404, $response->getStatusCode(),
            "/spans/something should return 404, got {$response->getStatusCode()}");
    }
} 