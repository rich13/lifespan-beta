<?php

namespace App\Services;

use Guardian\GuardianAPI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class GuardianService
{
    private GuardianAPI $api;

    public function __construct()
    {
        $apiKey = config('services.guardian.api_key');
        if (!$apiKey) {
            throw new \Exception('Guardian API key not configured');
        }

        $this->api = new GuardianAPI($apiKey);
    }

    /**
     * Allowed sections for Guardian articles
     */
    private const ALLOWED_SECTIONS = ['uk-news', 'us-news', 'world'];

    /**
     * Get articles from The Guardian for a specific date
     * 
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $maxResults Maximum number of articles to return (default: 10)
     * @return array Array of article data
     */
    public function getArticlesForDate(int $year, int $month, int $day, int $maxResults = 10): array
    {
        $cacheKey = "guardian_articles_{$year}_{$month}_{$day}_{$maxResults}_filtered";
        
        // Cache for 24 hours
        return Cache::remember($cacheKey, 60 * 60 * 24, function () use ($year, $month, $day, $maxResults) {
            try {
                $date = Carbon::createFromDate($year, $month, $day);
                $fromDate = new \DateTimeImmutable($date->copy()->startOfDay()->toDateTimeString());
                $toDate = new \DateTimeImmutable($date->copy()->endOfDay()->toDateTimeString());

                $response = $this->api->content()
                    ->setFromDate($fromDate)
                    ->setToDate($toDate)
                    ->setSection('uk-news|us-news|world')
                    ->setPageSize($maxResults)
                    ->setOrderBy('newest')
                    ->setShowFields('headline,thumbnail,trailText,webUrl,webPublicationDate')
                    ->fetch(true); // Pass true to get arrays instead of objects
                
                $articles = [];
                
                if (isset($response['response']['results']) && is_array($response['response']['results'])) {
                    foreach ($response['response']['results'] as $result) {
                        $sectionId = $result['sectionId'] ?? null;
                        
                        // Filter to only allowed sections
                        if ($sectionId && in_array($sectionId, self::ALLOWED_SECTIONS)) {
                            $articles[] = [
                                'id' => $result['id'] ?? null,
                                'title' => strip_tags($result['webTitle'] ?? 'Untitled'),
                                'url' => $result['webUrl'] ?? null,
                                'publication_date' => $result['webPublicationDate'] ?? null,
                                'section' => $result['sectionName'] ?? null,
                                'thumbnail' => $result['fields']['thumbnail'] ?? null,
                                'trail_text' => isset($result['fields']['trailText']) ? strip_tags($result['fields']['trailText']) : null,
                            ];
                        }
                    }
                }

                // Limit to maxResults after filtering
                return array_slice($articles, 0, $maxResults);
            } catch (\Exception $e) {
                Log::error('Guardian API error', [
                    'date' => "{$year}-{$month}-{$day}",
                    'error' => $e->getMessage(),
                ]);
                
                return [];
            }
        });
    }

    /**
     * Search for tags matching a person's name
     * 
     * @param string $personName The person's name to search for
     * @return array Array of tag IDs that match, ordered by relevance
     */
    private function findTagsForPerson(string $personName): array
    {
        $cacheKey = "guardian_tags_" . md5($personName);
        
        // Cache for 7 days (tags don't change often)
        return Cache::remember($cacheKey, 60 * 60 * 24 * 7, function () use ($personName) {
            try {
                // Search for tags matching the person's name (no type restriction)
                $response = $this->api->tags()
                    ->setQuery($personName)
                    ->setPageSize(20)
                    ->fetch(true);
                
                $keywordTags = [];
                $contributorTags = [];
                $otherTags = [];
                
                if (isset($response['response']['results']) && is_array($response['response']['results'])) {
                    foreach ($response['response']['results'] as $tag) {
                        if (!isset($tag['id'])) {
                            continue;
                        }
                        
                        $tagId = $tag['id'];
                        $tagType = $tag['type'] ?? '';
                        $tagTitle = strtolower($tag['webTitle'] ?? '');
                        $personNameLower = strtolower($personName);
                        
                        // Prefer keyword tags that match the person's name closely
                        if ($tagType === 'keyword' && $tagTitle === $personNameLower) {
                            $keywordTags[] = $tagId;
                        }
                        // Also accept contributor tags that match
                        elseif ($tagType === 'contributor' && $tagTitle === $personNameLower) {
                            $contributorTags[] = $tagId;
                        }
                        // Include other keyword tags that contain the person's name
                        elseif ($tagType === 'keyword' && strpos($tagId, strtolower(str_replace(' ', '-', $personName))) !== false) {
                            $otherTags[] = $tagId;
                        }
                    }
                }
                
                // Return tags in order of preference: exact keyword matches, then contributor matches, then other keyword matches
                return array_merge($keywordTags, $contributorTags, $otherTags);
            } catch (\Exception $e) {
                Log::error('Guardian API error (tags search)', [
                    'person_name' => $personName,
                    'error' => $e->getMessage(),
                ]);
                
                return [];
            }
        });
    }

    /**
     * Get articles from The Guardian about a specific person/span using tags
     * 
     * @param string $personName The person's name to search for
     * @param int $maxResults Maximum number of articles to return (default: 10)
     * @return array Array of article data
     */
    public function getArticlesAbout(string $personName, int $maxResults = 10): array
    {
        $cacheKey = "guardian_articles_about_" . md5($personName) . "_{$maxResults}_filtered";
        
        // Cache for 24 hours
        return Cache::remember($cacheKey, 60 * 60 * 24, function () use ($personName, $maxResults) {
            try {
                // First, find tags for this person
                $tags = $this->findTagsForPerson($personName);
                
                if (empty($tags)) {
                    // If no tags found, fall back to query search
                    Log::info('No tags found for person, falling back to query search', [
                        'person_name' => $personName,
                    ]);
                    
                    $response = $this->api->content()
                        ->setQuery($personName)
                        ->setSection('uk-news|us-news|world')
                        ->setPageSize($maxResults)
                        ->setOrderBy('newest')
                        ->setShowFields('headline,thumbnail,trailText,webUrl,webPublicationDate')
                        ->fetch(true);
                } else {
                    // Use the first matching tag (most relevant)
                    $tag = $tags[0];
                    
                    $response = $this->api->content()
                        ->setTag($tag)
                        ->setSection('uk-news|us-news|world')
                        ->setPageSize($maxResults)
                        ->setOrderBy('newest')
                        ->setShowFields('headline,thumbnail,trailText,webUrl,webPublicationDate')
                        ->fetch(true);
                }
                
                $articles = [];
                
                if (isset($response['response']['results']) && is_array($response['response']['results'])) {
                    foreach ($response['response']['results'] as $result) {
                        $sectionId = $result['sectionId'] ?? null;
                        
                        // Filter to only allowed sections
                        if ($sectionId && in_array($sectionId, self::ALLOWED_SECTIONS)) {
                            $articles[] = [
                                'id' => $result['id'] ?? null,
                                'title' => strip_tags($result['webTitle'] ?? 'Untitled'),
                                'url' => $result['webUrl'] ?? null,
                                'publication_date' => $result['webPublicationDate'] ?? null,
                                'section' => $result['sectionName'] ?? null,
                                'thumbnail' => $result['fields']['thumbnail'] ?? null,
                                'trail_text' => isset($result['fields']['trailText']) ? strip_tags($result['fields']['trailText']) : null,
                            ];
                        }
                    }
                }

                // Limit to maxResults after filtering
                return array_slice($articles, 0, $maxResults);
            } catch (\Exception $e) {
                Log::error('Guardian API error (articles search)', [
                    'person_name' => $personName,
                    'error' => $e->getMessage(),
                ]);
                
                return [];
            }
        });
    }
}

