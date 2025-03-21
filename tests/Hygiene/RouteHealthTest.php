<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use App\Models\User;

class RouteHealthTest extends TestCase
{
    /**
     * Routes that should be excluded from our convention checks
     */
    private array $excludedRoutes = [
        'api/user',
        'api/spans/search',
        'api/spans',
        'auth/email',
        '_ignition/health-check',
        'sanctum/csrf-cookie',
    ];

    /**
     * Test that all public routes return 200
     */
    public function test_public_routes_are_accessible(): void
    {
        $publicRoutes = [
            '/',
            '/login',
        ];

        foreach ($publicRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->status() === 200,
                "Route {$route} returned {$response->status()} instead of 200"
            );
        }
    }

    /**
     * Test that protected routes redirect to login
     */
    public function test_protected_routes_require_auth(): void
    {
        $protectedRoutes = [
            '/profile',
            '/spans/create',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->status() === 302 && 
                str_contains($response->headers->get('Location'), '/login'),
                "Route {$route} should redirect to login"
            );
        }
    }

    /**
     * Test that all routes have names
     */
    public function test_all_routes_are_named(): void
    {
        $unnamedRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return !$route->getName() && 
                   !in_array($route->uri(), $this->excludedRoutes) &&
                   !str_starts_with($route->uri(), '_');
        });

        $this->assertEmpty(
            $unnamedRoutes, 
            'Found unnamed routes: ' . implode(', ', $unnamedRoutes->pluck('uri')->toArray())
        );
    }

    /**
     * Test that admin routes are protected
     */
    public function test_admin_routes_are_protected(): void
    {
        $adminRoutes = [
            '/admin',  // Dashboard
            '/admin/import',  // Import management
            '/admin/span-types',  // Span types management
            '/admin/connection-types',  // Connection types management
        ];

        // Test unauthenticated access
        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->status() === 302 && 
                str_contains($response->headers->get('Location'), '/login'),
                "Route {$route} should redirect to login when unauthenticated"
            );
        }

        // Test non-admin user access
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->status() === 403,
                "Route {$route} should return 403 for non-admin users"
            );
        }

        // Test admin user access
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        foreach ($adminRoutes as $route) {
            $response = $this->get($route);
            $this->assertTrue(
                $response->status() === 200,
                "Route {$route} should be accessible to admin users"
            );
        }
    }

    /**
     * Test that all routes use appropriate middleware
     */
    public function test_routes_have_appropriate_middleware(): void
    {
        $routes = Route::getRoutes();
        $violations = [];

        foreach ($routes as $route) {
            // Skip excluded routes and API routes
            if (in_array($route->uri(), $this->excludedRoutes) || 
                str_starts_with($route->uri(), '_') ||
                str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            // Check web middleware
            if (!in_array('web', $route->middleware())) {
                $violations[] = "Route {$route->uri()} is missing web middleware";
            }

            // Check auth middleware for protected routes
            if ((str_starts_with($route->uri(), 'spans') && 
                 !in_array($route->uri(), ['spans', 'spans/{span}'])) || 
                str_starts_with($route->uri(), 'profile')) {
                if (!in_array('auth', $route->middleware())) {
                    $violations[] = "Protected route {$route->uri()} is missing auth middleware";
                }
            }
        }

        $this->assertEmpty($violations, implode(PHP_EOL, $violations));
    }
} 