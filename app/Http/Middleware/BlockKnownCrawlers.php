<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockKnownCrawlers
{
    /**
     * Refuse known scrapers and mark every response as non-indexable.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $this->withRobotsTag($next($request));
        }

        if ($this->isBlockedCrawler($request)) {
            return $this->withRobotsTag(response('Forbidden', 403));
        }

        return $this->withRobotsTag($next($request));
    }

    protected function shouldSkip(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return in_array($path, ['health', 'robots.txt'], true);
    }

    protected function isBlockedCrawler(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();
        if ($userAgent === '') {
            return false;
        }

        foreach (config('crawlers.user_agent_patterns', []) as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function withRobotsTag(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
