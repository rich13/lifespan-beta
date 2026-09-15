<?php

namespace Tests\Feature;

use App\Models\Span;
use Tests\TestCase;

class CrawlerDefenceTest extends TestCase
{
    public function test_robots_txt_disallows_all_crawlers(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /', false);
        $response->assertSee('User-agent: GPTBot', false);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_home_page_includes_robots_meta_tag(): void
    {
        $response = $this->get('/', [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_known_crawler_is_forbidden_on_home(): void
    {
        $response = $this->get('/', [
            'User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
        ]);

        $response->assertForbidden();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_known_crawler_is_forbidden_on_span_url(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Public Person',
        ]);

        $response = $this->get(route('spans.show', $span), [
            'User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
        ]);

        $response->assertForbidden();
    }

    public function test_browser_user_agent_is_allowed_on_home(): void
    {
        $response = $this->get('/', [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $response->assertOk();
    }

    public function test_known_crawler_can_still_read_robots_txt(): void
    {
        $response = $this->get('/robots.txt', [
            'User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
        ]);

        $response->assertOk();
        $response->assertSee('Disallow: /', false);
    }
}
