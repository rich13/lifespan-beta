<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UKParliamentApiService
{
    protected const BASE_URL = 'https://members-api.parliament.uk/api';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Search for members in the UK Parliament
     */
    public function searchMembers(array $filters = [], int $skip = 0, int $take = 20): array
    {
        $cacheKey = "parliament_search_" . md5(serialize($filters) . $skip . $take);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $skip, $take) {
            try {
                $params = array_merge([
                    'House' => '1', // 1 = Commons, 2 = Lords
                    'skip' => $skip,
                    'take' => $take
                ], $filters);

                $response = Http::timeout(30)->get(self::BASE_URL . '/Members/Search', $params);
                
                if ($response->successful()) {
                    return $response->json();
                } else {
                    Log::warning("Failed to search Parliament members", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return ['items' => [], 'totalResults' => 0];
                }
            } catch (\Exception $e) {
                Log::error("Exception searching Parliament members", [
                    'error' => $e->getMessage()
                ]);
                return ['items' => [], 'totalResults' => 0];
            }
        });
    }

    /**
     * Get detailed information about a specific member
     */
    public function getMember(int $memberId): array
    {
        $cacheKey = "parliament_member_{$memberId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($memberId) {
            try {
                $response = Http::timeout(30)->get(self::BASE_URL . "/Members/{$memberId}");
                
                if ($response->successful()) {
                    return $response->json();
                } else {
                    Log::warning("Failed to fetch member {$memberId}", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching member {$memberId}", [
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Get member synopsis (additional biographical information)
     */
    public function getMemberSynopsis(int $memberId): string
    {
        $cacheKey = "parliament_member_synopsis_{$memberId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($memberId) {
            try {
                $response = Http::timeout(30)->get(self::BASE_URL . "/Members/{$memberId}/Synopsis");
                
                if ($response->successful()) {
                    $data = $response->json();
                    return $data['value'] ?? '';
                } else {
                    Log::warning("Failed to fetch member synopsis {$memberId}", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return '';
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching member synopsis {$memberId}", [
                    'error' => $e->getMessage()
                ]);
                return '';
            }
        });
    }

    /**
     * Search for Prime Ministers by name using the Parliament API
     */
    public function searchPrimeMinisters(string $searchTerm = '', int $skip = 0, int $take = 20): array
    {
        try {
            // Search for members by name
            $searchResults = $this->searchMembers(['Name' => $searchTerm], $skip, $take);
            
            if (empty($searchResults['items'])) {
                return [
                    'items' => [],
                    'totalResults' => 0,
                    'skip' => $skip,
                    'take' => $take
                ];
            }

            // Filter for current and former Prime Ministers by checking their synopsis
            $primeMinisters = [];
            foreach ($searchResults['items'] as $member) {
                $memberId = $member['value']['id'];
                $synopsis = $this->getMemberSynopsis($memberId);
                
                // Check if the member is/was a Prime Minister
                if ($this->isPrimeMinister($synopsis, $member['value'])) {
                    $primeMinisters[] = [
                        'name' => $member['value']['nameDisplayAs'],
                        'parliament_id' => $memberId,
                        'party' => $member['value']['latestParty']['name'] ?? 'Unknown',
                        'constituency' => $member['value']['latestHouseMembership']['membershipFrom'] ?? 'Unknown',
                        'is_current' => $this->isCurrentPrimeMinister($synopsis),
                        'synopsis' => $synopsis
                    ];
                }
            }

            return [
                'items' => $primeMinisters,
                'totalResults' => count($primeMinisters),
                'skip' => $skip,
                'take' => $take
            ];

        } catch (\Exception $e) {
            Log::error('Failed to search Prime Ministers', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm
            ]);

            return [
                'items' => [],
                'totalResults' => 0,
                'skip' => $skip,
                'take' => $take
            ];
        }
    }

    /**
     * Check if a member is/was a Prime Minister based on their synopsis
     */
    private function isPrimeMinister(string $synopsis, array $memberData): bool
    {
        // Check for Prime Minister references in synopsis
        $pmKeywords = [
            'Prime Minister',
            'Prime Minister of the United Kingdom',
            'First Lord of the Treasury'
        ];

        foreach ($pmKeywords as $keyword) {
            if (stripos($synopsis, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a member is the current Prime Minister
     */
    private function isCurrentPrimeMinister(string $synopsis): bool
    {
        $currentKeywords = [
            'currently holds the Government post of Prime Minister',
            'is the current Prime Minister',
            'serves as Prime Minister'
        ];

        foreach ($currentKeywords as $keyword) {
            if (stripos($synopsis, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get comprehensive data for a Prime Minister
     */
    public function getPrimeMinisterData(int $parliamentId): array
    {
        $memberData = $this->getMember($parliamentId);
        $synopsis = $this->getMemberSynopsis($parliamentId);

        if (empty($memberData)) {
            return [];
        }

        $value = $memberData['value'] ?? [];
        
        // Extract Prime Ministership information from synopsis
        $primeMinisterships = $this->extractPrimeMinisterships($synopsis);
        
        return [
            'parliament_id' => $parliamentId,
            'name' => $value['nameDisplayAs'] ?? '',
            'full_name' => $value['nameFullTitle'] ?? '',
            'gender' => $value['gender'] ?? '',
            'party' => $value['latestParty']['name'] ?? '',
            'party_abbreviation' => $value['latestParty']['abbreviation'] ?? '',
            'constituency' => $value['latestHouseMembership']['membershipFrom'] ?? '',
            'membership_start' => $value['latestHouseMembership']['membershipStartDate'] ?? '',
            'membership_end' => $value['latestHouseMembership']['membershipEndDate'] ?? '',
            'synopsis' => $synopsis,
            'thumbnail_url' => $value['thumbnailUrl'] ?? '',
            'is_current_pm' => $this->isCurrentPrimeMinister($synopsis),
            'prime_ministerships' => $primeMinisterships,
            'raw_data' => $memberData
        ];
    }

    /**
     * Extract Prime Ministership periods from synopsis text
     */
    private function extractPrimeMinisterships(string $synopsis): array
    {
        $primeMinisterships = [];
        
        // Look for date patterns in the synopsis
        // This is a simplified approach - in practice, you might need more sophisticated parsing
        
        // Check if currently Prime Minister
        if ($this->isCurrentPrimeMinister($synopsis)) {
            $primeMinisterships[] = [
                'start_date' => null, // Would need to be manually entered
                'end_date' => null,
                'ongoing' => true,
                'party' => null // Would need to be determined from context
            ];
        }
        
        // Look for historical Prime Ministership periods
        // This would require more sophisticated text parsing or manual input
        // For now, we'll return an empty array and rely on manual input
        
        return $primeMinisterships;
    }

    /**
     * Convert Parliament API data to Lifespan YAML format
     */
    public function convertToLifespanYaml(array $pmData): array
    {
        $yaml = [
            'name' => $pmData['name'],
            'type' => 'prime_minister',
            'parliament_id' => $pmData['parliament_id'],
            'party' => $pmData['party'],
            'constituency' => $pmData['constituency'],
            'description' => $pmData['synopsis'],
            'metadata' => [
                'parliament_api_data' => $pmData['raw_data'],
                'gender' => $pmData['gender'],
                'party_abbreviation' => $pmData['party_abbreviation']
            ]
        ];

        // Add birth/death dates if available in synopsis
        // This would need more sophisticated parsing of the synopsis text
        // For now, we'll leave it as placeholder data

        // Add Prime Ministership periods
        // This would need to be manually curated as the API doesn't directly identify PMs
        $yaml['prime_ministerships'] = [
            [
                'start_date' => 'YYYY-MM-DD', // Would need manual input
                'end_date' => 'YYYY-MM-DD',   // Would need manual input
                'party' => $pmData['party']
            ]
        ];

        return $yaml;
    }

    /**
     * Clear cache for a specific member
     */
    public function clearMemberCache(int $memberId): void
    {
        Cache::forget("parliament_member_{$memberId}");
        Cache::forget("parliament_member_synopsis_{$memberId}");
    }

    /**
     * Clear all Parliament API cache
     */
    public function clearAllCache(): void
    {
        // This is a simplified approach - in production you might want more granular cache management
        Cache::flush();
    }
} 