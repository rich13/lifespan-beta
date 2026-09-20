<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarCollapsedStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_renders_expanded_when_cookie_is_absent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $html = $response->getContent();
        $head = strstr($html, '<body', true);

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\bsidebar-collapsed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sidebar"[^>]*\bcollapsed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="main-content"[^>]*\bcollapsed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sidebar-toggle"[^>]*\bcollapsed\b/', $html);
        $this->assertNotFalse($head);
        $this->assertStringContainsString('sidebarCollapsed', $head);
        $this->assertStringContainsString("document.documentElement.classList.add('sidebar-collapsed')", $head);
    }

    public function test_sidebar_renders_collapsed_from_javascript_cookie_on_first_paint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withUnencryptedCookie('sidebarCollapsed', 'true')
            ->get(route('home'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\bsidebar-collapsed\b/', $html);
        $this->assertMatchesRegularExpression('/id="sidebar"[^>]*\bcollapsed\b/', $html);
        $this->assertMatchesRegularExpression('/id="main-content"[^>]*\bcollapsed\b/', $html);
        $this->assertMatchesRegularExpression('/id="sidebar-toggle"[^>]*\bcollapsed\b/', $html);
    }

    public function test_sidebar_stays_expanded_when_cookie_is_false(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withUnencryptedCookie('sidebarCollapsed', 'false')
            ->get(route('home'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\bsidebar-collapsed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sidebar"[^>]*\bcollapsed\b/', $html);
    }
}
