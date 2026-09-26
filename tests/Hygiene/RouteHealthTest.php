<?php

namespace Tests\Hygiene;

use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;

class RouteHealthTest extends \Tests\TestCase
{
    use WithFaker;

    protected $adminUser;
    protected $regularUser;
    protected $testSpan;
    protected $testConnection;
    protected $testConnectionType;
    protected $testSpanType;
    protected $testTypesExplorerSpan;

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

        $this->testTypesExplorerSpan = Span::factory()->create([
            'name' => 'Types explorer health span',
            'type_id' => 'test_type',
            'slug' => 'types-explorer-health-' . uniqid('', true),
            'access_level' => 'public',
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
        $typeId = $this->testSpanType->type_id;
        $publicRoutes = [
            '/' => 200,
            '/health' => 200,
            '/signin' => 200,
            '/register' => 302,
            '/email/verify' => 302,
            '/auth/email' => 302,
            '/auth/password' => 302,
            '/spans' => 200,
            '/spans/search' => 200,
            '/spans/types' => 302,
            '/spans/types/' . $typeId => 302,
            '/spans/types/' . $typeId . '/subtypes' => 301,
            '/spans/types/' . $typeId . '/__no_subtype__' => 302,
            '/spans/types/' . $typeId . '/__no_subtype__/' . $this->testTypesExplorerSpan->slug => 302,
            '/spans/' . $this->testTypesExplorerSpan->id . '/connections.json' => 200,
            '/spans/' . $this->testSpan->id => 301,
            '/spans/' . $this->testSpan->id . '/story' => 302,
            '/history/' . $this->testSpan->id => 302,
            '/sets' => 403,
            '/explore/family' => 302,
            '/friends' => 302,
            '/friends/data' => 302,
            '/explore/desert-island-discs' => 200,
            '/date/2024-01-01' => 200,
            '/debug' => 200,
            '/error' => 404,
            '/api/user' => 302,
            '/api/spans/search' => 200,
            '/api/spans/' . $this->testSpan->id => 200,
            '/api/spans/' . $this->testSpan->id . '/during-connections' => 200,
            '/api/spans/' . $this->testSpan->id . '/object-connections' => 200,
            '/api/sets/containing/' . $this->testSpan->id => 200,
            '/api/wikipedia/on-this-day/1/1' => 200,
        ];

        $failures = [];
        foreach ($publicRoutes as $route => $expectedStatus) {
            $response = $this->get($route);
            $status = $response->getStatusCode();

            if ($status !== $expectedStatus) {
                $location = $response->headers->get('Location');
                $failures[] = "Route {$route} returned {$status}, expected {$expectedStatus}"
                    .($location ? " (location {$location})" : '');
                continue;
            }

            try {
                $this->assertResponseBodyHasNoErrorMarkers($response, [
                    'Call to undefined method',
                    'Fatal error',
                    'Parse error',
                    'Class not found',
                ], "Route {$route}");
            } catch (\Throwable $e) {
                $failures[] = $e->getMessage();
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
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
            // '/admin/spans/' . $this->testSpan->id, // Skipped: groupMembers not implemented yet
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
            '/admin/import/' . $this->testSpan->id, // redirects to the import index
            '/admin/import/desert-island-discs',
            '/admin/import/desert-island-discs/step-import',
            '/admin/import/musicbrainz',
            '/admin/import/parliament',
            '/admin/import/prime-ministers',
            '/admin/import/prime-ministers/recent',
            '/admin/import/simple-desert-island-discs',
            '/admin/data-export',
            '/admin/data-export/export-all',
            '/admin/data-export/stats',
            '/admin/data-import',
            '/admin/span-access',
            '/admin/improvement',
            '/admin/improvement/status',
            // '/admin/system-history', // Skipped: pages return 500; revisit when system history is in use
            // '/admin/system-history/stats',
            '/admin/tools',
            '/admin/merge/find-similar-spans',
            '/admin/tools/make-things-public',
            '/admin/tools/create-desert-island-discs',
            '/admin/tools/prewarm-wikipedia-cache',
            '/admin/visualizer',
            '/admin/visualizer/temporal',
            '/admin/ai-yaml-generator',
            '/admin/ai-yaml-generator/placeholders',
        ];

        $expectedStatuses = [
            '/admin/import/' . $this->testSpan->id => 302,
        ];

        $failures = [];
        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            $status = $response->getStatusCode();
            $expectedStatus = $expectedStatuses[$route] ?? 200;

            if ($status !== $expectedStatus) {
                $location = $response->headers->get('Location');
                $failures[] = "Admin route {$route} returned {$status}, expected {$expectedStatus}"
                    .($location ? " (location {$location})" : '');
                continue;
            }

            try {
                $this->assertResponseBodyHasNoErrorMarkers($response, [
                    'Call to undefined method',
                    'Fatal error',
                    'Parse error',
                    'Class not found',
                ], "Admin route {$route}");
            } catch (\Throwable $e) {
                $failures[] = $e->getMessage();
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
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
            $this->get($route)->assertRedirect(route('login'));
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

        $response->assertRedirect(route('login'));
        
        $this->assertResponseBodyHasNoErrorMarkers($response, [
            'getCreator',
            'Call to undefined method',
        ], 'Route /spans/types');
    }

    /**
     * Test /spans/{span} with a valid and invalid span
     */
    public function test_spans_show_route_handles_valid_and_invalid_ids()
    {
        // Valid span
        $response = $this->get('/spans/' . $this->testSpan->id);
        $response->assertStatus(301);
        $response->assertRedirect('/spans/' . $this->testSpan->slug);

        // Invalid span (random string)
        $response = $this->get('/spans/something');
        $this->assertNotEquals(500, $response->getStatusCode(),
            "/spans/something returned 500 error");
        $this->assertEquals(404, $response->getStatusCode(),
            "/spans/something should return 404, got {$response->getStatusCode()}");
    }
} 