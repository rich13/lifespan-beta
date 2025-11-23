<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WikimediaCommonsApiService
{
    protected string $baseUrl = 'https://commons.wikimedia.org/w/api.php';
    protected array $headers;

    public function __construct()
    {
        $this->headers = [
            'Accept' => 'application/json',
            'User-Agent' => config('app.user_agent')
        ];
    }

    /**
     * Search for images in Wikimedia Commons
     */
    public function searchImages(string $query, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = "wikimedia_search_{$query}_{$page}_{$perPage}";
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $page, $perPage) {
            try {
                $offset = ($page - 1) * $perPage;
                
                $response = Http::withHeaders($this->headers)
                    ->get($this->baseUrl, [
                        'action' => 'query',
                        'list' => 'search',
                        'srsearch' => $query,
                        'srnamespace' => 6, // File namespace
                        'sroffset' => $offset,
                        'srlimit' => $perPage,
                        'format' => 'json'
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Transform the data to match our expected format
                    $results = [
                        'data' => [],
                        'meta' => [
                            'total' => $data['query']['searchinfo']['totalhits'] ?? 0,
                            'current_page' => $page,
                            'per_page' => $perPage,
                            'last_page' => ceil(($data['query']['searchinfo']['totalhits'] ?? 0) / $perPage)
                        ]
                    ];

                    foreach ($data['query']['search'] ?? [] as $item) {
                        $results['data'][] = [
                            'id' => $item['pageid'],
                            'title' => $item['title'],
                            'snippet' => $item['snippet'],
                            'timestamp' => $item['timestamp']
                        ];
                    }

                    return $results;
                }

                Log::error('Wikimedia Commons API search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return ['data' => [], 'meta' => ['total' => 0]];
            } catch (\Exception $e) {
                Log::error('Wikimedia Commons API search exception', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);

                return ['data' => [], 'meta' => ['total' => 0]];
            }
        });
    }

    /**
     * Get detailed information about a specific image
     */
    public function getImage(string $imageId): ?array
    {
        $cacheKey = "wikimedia_image_{$imageId}";
        
        return Cache::remember($cacheKey, 86400, function () use ($imageId) {
            try {
                Log::info('Wikimedia Commons API: Fetching image data', ['image_id' => $imageId]);
                
                // First get the image info
                $response = Http::withHeaders($this->headers)
                    ->get($this->baseUrl, [
                        'action' => 'query',
                        'pageids' => $imageId,
                        'prop' => 'imageinfo|extracts',
                        'iiprop' => 'url|size|mime|timestamp|user|comment',
                        'format' => 'json'
                    ]);

                Log::info('Wikimedia Commons API: Image info response', [
                    'image_id' => $imageId,
                    'status' => $response->status(),
                    'successful' => $response->successful()
                ]);

                if (!$response->successful()) {
                    Log::error('Wikimedia Commons API image fetch failed', [
                        'image_id' => $imageId,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return null;
                }

                $data = $response->json();
                Log::info('Wikimedia Commons API: Parsed response', [
                    'image_id' => $imageId,
                    'has_query' => isset($data['query']),
                    'has_pages' => isset($data['query']['pages']),
                    'page_keys' => array_keys($data['query']['pages'] ?? [])
                ]);
                
                $page = $data['query']['pages'][$imageId] ?? null;
                
                if (!$page) {
                    Log::warning('Wikimedia Commons API: Page not found', ['image_id' => $imageId]);
                    return null;
                }

                // Get the page content for metadata
                $contentResponse = Http::withHeaders($this->headers)
                    ->get($this->baseUrl, [
                        'action' => 'query',
                        'pageids' => $imageId,
                        'prop' => 'revisions',
                        'rvprop' => 'content',
                        'format' => 'json'
                    ]);

                Log::info('Wikimedia Commons API: Content response', [
                    'image_id' => $imageId,
                    'status' => $contentResponse->status(),
                    'successful' => $contentResponse->successful()
                ]);

                $contentData = $contentResponse->json();
                $contentPage = $contentData['query']['pages'][$imageId] ?? null;
                $content = $contentPage['revisions'][0]['*'] ?? '';

                // Extract metadata from the content
                $metadata = $this->extractMetadata($content);
                
                Log::info('Wikimedia Commons API: Extracted metadata', [
                    'image_id' => $imageId,
                    'metadata' => $metadata,
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 500)
                ]);

                // Get image info
                $imageInfo = $page['imageinfo'][0] ?? null;
                
                if (!$imageInfo) {
                    Log::warning('Wikimedia Commons API: No image info found', ['image_id' => $imageId]);
                    return null;
                }

                $result = [
                    'id' => $imageId,
                    'title' => $page['title'],
                    'url' => $imageInfo['url'],
                    'description_url' => $imageInfo['descriptionurl'],
                    'width' => $imageInfo['width'],
                    'height' => $imageInfo['height'],
                    'size' => $imageInfo['size'],
                    'mime' => $imageInfo['mime'],
                    'timestamp' => $imageInfo['timestamp'],
                    'uploader' => $imageInfo['user'],
                    'comment' => $imageInfo['comment'],
                    'metadata' => $metadata
                ];

                Log::info('Wikimedia Commons API: Successfully processed image', [
                    'image_id' => $imageId,
                    'title' => $result['title'],
                    'url' => $result['url']
                ]);

                return $result;
            } catch (\Exception $e) {
                Log::error('Wikimedia Commons API image fetch exception', [
                    'image_id' => $imageId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return null;
            }
        });
    }

    /**
     * Extract metadata from Wikimedia Commons page content
     */
    protected function extractMetadata(string $content): array
    {
        $metadata = [
            'description' => '',
            'source' => '',
            'date' => '',
            'author' => '',
            'permission' => '',
            'license' => '',
            'license_url' => '',
            'requires_attribution' => false,
            'categories' => []
        ];

        // Extract description - multiple patterns
        if (preg_match('/Description\s*=\s*(.+?)(?:\n|$)/', $content, $matches)) {
            $metadata['description'] = trim($matches[1]);
        } elseif (preg_match('/\|description\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['description'] = trim($matches[1]);
        }

        // Extract source - multiple patterns
        if (preg_match('/Source\s*=\s*(.+?)(?:\n|$)/', $content, $matches)) {
            $metadata['source'] = trim($matches[1]);
        } elseif (preg_match('/\|source\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['source'] = trim($matches[1]);
        }

        // Extract date - multiple patterns
        if (preg_match('/Date\s*=\s*(.+?)(?:\n|$)/', $content, $matches)) {
            $metadata['date'] = trim($matches[1]);
        } elseif (preg_match('/\|date\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['date'] = trim($matches[1]);
        }

        // Extract author - multiple patterns
        if (preg_match('/Author\s*=\s*(.+?)(?:\n|$)/', $content, $matches)) {
            $metadata['author'] = trim($matches[1]);
        } elseif (preg_match('/\|author\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['author'] = trim($matches[1]);
        } elseif (preg_match('/\|artist\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['author'] = trim($matches[1]);
        } elseif (preg_match('/\|photographer\s*=\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $metadata['author'] = trim($matches[1]);
        }

        // Extract permission
        if (preg_match('/Permission\s*=\s*(.+?)(?:\n|$)/', $content, $matches)) {
            $metadata['permission'] = trim($matches[1]);
        }

        // Extract license - comprehensive patterns
        $licensePatterns = [
            // Creative Commons licenses
            '/\{\{(cc-by[^}]*)\}\}/i',
            '/\{\{(cc-by-sa[^}]*)\}\}/i',
            '/\{\{(cc-by-nd[^}]*)\}\}/i',
            '/\{\{(cc-by-nc[^}]*)\}\}/i',
            '/\{\{(cc-by-nc-sa[^}]*)\}\}/i',
            '/\{\{(cc-by-nc-nd[^}]*)\}\}/i',
            '/\{\{(cc-zero[^}]*)\}\}/i',
            // Public domain
            '/\{\{(public-domain[^}]*)\}\}/i',
            '/\{\{(pd-[^}]*)\}\}/i',
            // Fair use
            '/\{\{(fairuse[^}]*)\}\}/i',
            // Other common licenses
            '/\{\{(gfdl[^}]*)\}\}/i',
            '/\{\{(mit[^}]*)\}\}/i',
            '/\{\{(apache[^}]*)\}\}/i',
            // License parameter in templates
            '/\|license\s*=\s*(.+?)(?:\n|$)/i',
            '/\|permission\s*=\s*(.+?)(?:\n|$)/i'
        ];

        foreach ($licensePatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $license = trim($matches[1]);
                // Clean up license text to make it more readable
                $license = $this->cleanLicenseText($license);
                $metadata['license'] = $license;
                
                // Get license URL and attribution requirements
                $licenseInfo = $this->getLicenseInfo($license);
                $metadata['license_url'] = $licenseInfo['url'];
                $metadata['requires_attribution'] = $licenseInfo['requires_attribution'];
                break;
            }
        }

        // Extract categories
        if (preg_match_all('/\[\[Category:(.+?)\]\]/', $content, $matches)) {
            $metadata['categories'] = $matches[1];
        }

        return $metadata;
    }

    /**
     * Clean up license text to make it more readable
     */
    protected function cleanLicenseText(string $license): string
    {
        // Remove template braces
        $license = preg_replace('/^\{\{|\}\}$/', '', $license);
        
        // Replace common license abbreviations with full names
        $licenseMap = [
            'cc-by' => 'Creative Commons Attribution',
            'cc-by-sa' => 'Creative Commons Attribution-ShareAlike',
            'cc-by-nd' => 'Creative Commons Attribution-NoDerivs',
            'cc-by-nc' => 'Creative Commons Attribution-NonCommercial',
            'cc-by-nc-sa' => 'Creative Commons Attribution-NonCommercial-ShareAlike',
            'cc-by-nc-nd' => 'Creative Commons Attribution-NonCommercial-NoDerivs',
            'cc-zero' => 'Creative Commons Zero (Public Domain)',
            'public-domain' => 'Public Domain',
            'pd-' => 'Public Domain',
            'fairuse' => 'Fair Use',
            'gfdl' => 'GNU Free Documentation License',
            'mit' => 'MIT License',
            'apache' => 'Apache License'
        ];

        foreach ($licenseMap as $abbreviation => $fullName) {
            if (stripos($license, $abbreviation) !== false) {
                $license = str_ireplace($abbreviation, $fullName, $license);
            }
        }

        // Clean up any remaining template parameters
        $license = preg_replace('/\|[^|}]+/', '', $license);
        $license = preg_replace('/\s+/', ' ', $license);
        
        return trim($license);
    }

    /**
     * Get license URL and attribution requirements
     */
    protected function getLicenseInfo(string $license): array
    {
        $license = strtolower($license);
        
        $licenseMap = [
            'creative commons attribution' => [
                'url' => 'https://creativecommons.org/licenses/by/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-sharealike' => [
                'url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noderivs' => [
                'url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial' => [
                'url' => 'https://creativecommons.org/licenses/by-nc/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial-sharealike' => [
                'url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial-noderivs' => [
                'url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
                'requires_attribution' => true
            ],
            'creative commons zero' => [
                'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'requires_attribution' => false
            ],
            'public domain' => [
                'url' => 'https://creativecommons.org/publicdomain/mark/1.0/',
                'requires_attribution' => false
            ],
            'fair use' => [
                'url' => 'https://en.wikipedia.org/wiki/Fair_use',
                'requires_attribution' => true
            ],
            'gnu free documentation license' => [
                'url' => 'https://www.gnu.org/licenses/fdl.html',
                'requires_attribution' => true
            ],
            'mit license' => [
                'url' => 'https://opensource.org/licenses/MIT',
                'requires_attribution' => true
            ],
            'apache license' => [
                'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
                'requires_attribution' => true
            ]
        ];

        // Try to match the license
        foreach ($licenseMap as $pattern => $info) {
            if (strpos($license, $pattern) !== false) {
                return $info;
            }
        }

        // Default for unknown licenses
        return [
            'url' => '',
            'requires_attribution' => true // Assume attribution is required for unknown licenses
        ];
    }

    /**
     * Search for images by year (useful for finding images from specific time periods)
     */
    public function searchImagesByYear(string $query, int $year, int $page = 1, int $perPage = 20): array
    {
        $searchQuery = "{$query} {$year}";
        return $this->searchImages($searchQuery, $page, $perPage);
    }

    /**
     * Search for images in a specific category
     */
    public function searchImagesInCategory(string $category, int $page = 1, int $perPage = 20): array
    {
        $searchQuery = "category:{$category}";
        return $this->searchImages($searchQuery, $page, $perPage);
    }

    /**
     * Get image URLs in different sizes
     */
    public function getImageUrls(string $imageId): array
    {
        $image = $this->getImage($imageId);
        
        if (!$image) {
            return [];
        }

        $originalUrl = $image['url'];
        
        // Wikimedia Commons doesn't provide different sizes via API,
        // but we can construct thumbnail URLs
        $filename = basename($originalUrl);
        $path = dirname($originalUrl);
        
        return [
            'original' => $originalUrl,
            'large' => $originalUrl, // Same as original for Wikimedia
            'medium' => $originalUrl, // Same as original for Wikimedia
            'thumbnail' => $originalUrl // Same as original for Wikimedia
        ];
    }

    /**
     * Clear the API cache
     */
    public function clearCache(): void
    {
        Cache::flush();
    }
}
