<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\SpanType;
use App\Models\ConnectionType;
use App\Models\Connection;
use App\Services\WikimediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResearchController extends Controller
{
    protected $wikimediaService;

    public function __construct(WikimediaService $wikimediaService)
    {
        $this->wikimediaService = $wikimediaService;
    }

    /**
     * Show the research index page with search
     */
    public function index()
    {
        return view('research.index');
    }

    /**
     * Check if a span should be excluded from connected spans display
     */
    private function shouldExcludeSpan(Span $span): bool
    {
        // Exclude note spans
        if ($span->type_id === 'note') {
            return true;
        }
        
        // Exclude set spans
        if ($span->type_id === 'set') {
            return true;
        }
        
        // Exclude thing spans with subtype photo
        if ($span->type_id === 'thing' && $span->getMeta('subtype') === 'photo') {
            return true;
        }
        
        return false;
    }

    /**
     * Check if we should skip traversing into a span for second-level connections
     */
    private function shouldSkipSecondLevelTraversal(Span $span): bool
    {
        // Don't traverse into places (too many unrelated connections)
        if ($span->type_id === 'place') {
            return true;
        }
        
        return false;
    }

    /**
     * Show the research page for a specific span
     */
    public function show(Span $span, Request $request)
    {
        $article = null;
        $wikidataEntity = null;
        $isPrivateIndividual = $span->isPrivateIndividual();
        
        // Only fetch Wikipedia content for non-private individuals
        if (!$isPrivateIndividual) {
            // Check if user selected a specific article from disambiguation
            $selectedTitle = $request->get('article');

            if ($selectedTitle) {
                // User selected a specific article, fetch it directly
                $article = $this->wikimediaService->getFullWikipediaArticle($selectedTitle);
                
                // If it's still a disambiguation, that's fine - show it
                // Otherwise, we have the article
            } else {
                // Check sources for a Wikipedia URL first
                $wikipediaTitle = $this->extractWikipediaTitleFromSources($span->sources ?? []);
                
                if ($wikipediaTitle) {
                    // Use the Wikipedia title from sources
                    $searchQuery = $wikipediaTitle;
                    Log::info('ResearchController: Using Wikipedia title from sources', [
                        'span_name' => $span->name,
                        'wikipedia_title' => $wikipediaTitle
                    ]);
                } else {
                    // Get the span name and append span type to help disambiguate
                    $searchQuery = $span->name;
                    
                    // Append span type to search query to help disambiguate
                    // e.g., "the divine comedy" becomes "the divine comedy band"
                    $spanType = $span->type_id;
                    
                    // Only append for types that commonly need disambiguation
                    // Skip types that are usually unambiguous or don't help (person, organisation, connection, name, note, etc.)
                    $typesToAppend = ['band', 'thing', 'event', 'place', 'role', 'phase', 'set'];
                    if ($spanType && in_array($spanType, $typesToAppend)) {
                        $searchQuery = $searchQuery . ' ' . $spanType;
                    }
                    
                    Log::info('ResearchController: Searching Wikipedia', [
                        'span_name' => $span->name,
                        'span_type' => $spanType,
                        'search_query' => $searchQuery,
                        'type_appended' => in_array($spanType, $typesToAppend)
                    ]);
                }
                
                // Fetch full Wikipedia article
                $article = $this->wikimediaService->getFullWikipediaArticle($searchQuery);
            }
            
            // Clean Wikipedia HTML to remove resource loader references that cause 404 errors
            if ($article && isset($article['html'])) {
                $article['html'] = $this->cleanWikipediaHtml($article['html']);
            }
            
            // Fetch Wikidata entity data if we have a Wikipedia article (not a disambiguation)
            Log::info('ResearchController: Checking for Wikidata entity', [
                'has_article' => !is_null($article),
                'article_title' => $article['title'] ?? 'N/A',
                'is_disambiguation' => $article['is_disambiguation'] ?? false,
                'span_name' => $span->name
            ]);
            
            // Check if article exists, has a title, and is NOT a disambiguation page
            // is_disambiguation will be set to true if it's a disambiguation, false or not set otherwise
            $isDisambiguation = ($article['is_disambiguation'] ?? false) === true;
            if ($article && isset($article['title']) && !$isDisambiguation) {
                Log::info('ResearchController: Attempting to fetch Wikidata entity', [
                    'article_title' => $article['title']
                ]);
                $entityId = $this->wikimediaService->getWikidataEntityIdFromWikipediaTitle($article['title']);
                if ($entityId) {
                    Log::info('ResearchController: Found Wikidata entity ID, fetching entity data', [
                        'entity_id' => $entityId
                    ]);
                    $wikidataEntity = $this->wikimediaService->getWikidataEntity($entityId);
                    if ($wikidataEntity) {
                        Log::info('ResearchController: Successfully fetched Wikidata entity', [
                            'entity_id' => $entityId,
                            'has_id' => isset($wikidataEntity['id']),
                            'has_claims' => isset($wikidataEntity['claims']),
                            'entity_keys' => array_keys($wikidataEntity)
                        ]);
                        
                        // Extract all property IDs and entity IDs from claims for label resolution
                        $idsToResolve = [];
                        if (isset($wikidataEntity['claims'])) {
                            foreach ($wikidataEntity['claims'] as $propertyId => $claims) {
                                // Add property ID
                                $idsToResolve[] = $propertyId;
                                
                                // Extract entity IDs from claim values
                                foreach ($claims as $claim) {
                                    if (isset($claim['mainsnak']['datavalue']['type']) && 
                                        $claim['mainsnak']['datavalue']['type'] === 'wikibase-entityid' &&
                                        isset($claim['mainsnak']['datavalue']['value']['numeric-id'])) {
                                        $idsToResolve[] = 'Q' . $claim['mainsnak']['datavalue']['value']['numeric-id'];
                                    }
                                }
                            }
                        }
                        
                        // Fetch labels for all IDs
                        $labels = [];
                        if (!empty($idsToResolve)) {
                            $labels = $this->wikimediaService->getLabelsForEntities($idsToResolve);
                            Log::info('ResearchController: Fetched labels for Wikidata entities/properties', [
                                'ids_count' => count($idsToResolve),
                                'labels_count' => count($labels)
                            ]);
                        }
                        
                        // Add labels to the entity array for easy access in the view
                        $wikidataEntity['_labels'] = $labels;
                    } else {
                        Log::warning('ResearchController: Failed to fetch Wikidata entity', [
                            'entity_id' => $entityId,
                            'article_title' => $article['title']
                        ]);
                    }
                } else {
                    Log::info('ResearchController: No Wikidata entity ID found for Wikipedia article', [
                        'article_title' => $article['title']
                    ]);
                }
            } else {
                $isDisambiguation = $article['is_disambiguation'] ?? null;
                Log::info('ResearchController: Skipping Wikidata fetch', [
                    'has_article' => !is_null($article),
                    'has_title' => isset($article['title']),
                    'is_disambiguation' => $isDisambiguation
                ]);
            }
        }

        // Get all connected spans where this span is the subject (parent) - first level
        $connectionsAsSubject = $span->connectionsAsSubjectWithAccess()
            ->with(['child.type', 'type', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'span' => $connection->child,
                    'connection_type' => $connection->type,
                    'connection' => $connection,
                    'direction' => 'subject' // This span is the subject
                ];
            })
            ->filter(function ($item) {
                return $item['span'] !== null && !$this->shouldExcludeSpan($item['span']);
            });

        // Get all connected spans where this span is the object (child) - first level
        $connectionsAsObject = $span->connectionsAsObjectWithAccess()
            ->with(['parent.type', 'type', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'span' => $connection->parent,
                    'connection_type' => $connection->type,
                    'connection' => $connection,
                    'direction' => 'object' // This span is the object
                ];
            })
            ->filter(function ($item) {
                return $item['span'] !== null && !$this->shouldExcludeSpan($item['span']);
            });

        // Combine both directions and deduplicate by span ID
        // Manually combine to avoid merge() calling getKey() on arrays
        $allFirstLevel = collect();
        $seenIds = [];
        
        foreach ($connectionsAsSubject as $item) {
            $spanId = $item['span']->id;
            if (!in_array($spanId, $seenIds)) {
                $seenIds[] = $spanId;
                $allFirstLevel->push($item);
            }
        }
        
        foreach ($connectionsAsObject as $item) {
            $spanId = $item['span']->id;
            if (!in_array($spanId, $seenIds)) {
                $seenIds[] = $spanId;
                $allFirstLevel->push($item);
            }
        }
        
        $uniqueFirstLevel = $allFirstLevel;
        
        $allFirstLevel = $uniqueFirstLevel
            ->sortBy(function ($item) {
                // Sort by connection type forward_predicate, then by span name
                $connectionTypeName = $item['connection_type'] ? $item['connection_type']->forward_predicate : '';
                return $connectionTypeName . '|' . $item['span']->name;
            })
            ->values();

        // Get second level connections (connections of the first level spans)
        // Optimize: Batch load all connections at once instead of looping
        $secondLevelConnections = collect();
        $seenSpanIds = [$span->id]; // Don't show the original span again
        
        // Track all first-level span IDs for deduplication and get spans to traverse
        $firstLevelSpanIds = [];
        $firstLevelSpanMap = [];
        foreach ($allFirstLevel as $firstLevel) {
            $firstLevelSpan = $firstLevel['span'];
            $seenSpanIds[] = $firstLevelSpan->id;
            
            // Only include spans we should traverse into
            if (!$this->shouldSkipSecondLevelTraversal($firstLevelSpan)) {
                $firstLevelSpanIds[] = $firstLevelSpan->id;
                $firstLevelSpanMap[$firstLevelSpan->id] = $firstLevelSpan;
            }
        }
        
        // Batch load all second-level connections at once (much faster than looping)
        if (!empty($firstLevelSpanIds)) {
            $user = auth()->user();
            
            // Build access-controlled queries similar to connectionsAsSubjectWithAccess
            $subjectQuery = Connection::query()
                ->whereIn('parent_id', $firstLevelSpanIds)
                ->with(['child.type', 'type', 'parent', 'connectionSpan']);
            
            $objectQuery = Connection::query()
                ->whereIn('child_id', $firstLevelSpanIds)
                ->with(['parent.type', 'type', 'child', 'connectionSpan']);
            
            // Apply access control (similar to Span::connectionsAsSubjectWithAccess logic)
            if (!$user) {
                // Guest users can only see connections to/from public spans
                $subjectQuery->whereHas('child', function ($q) {
                    $q->where('access_level', 'public');
                });
                $objectQuery->whereHas('parent', function ($q) {
                    $q->where('access_level', 'public');
                });
            } elseif (!$user->is_admin) {
                // Regular users can see connections to/from spans they have permission to view
                $subjectQuery->whereHas('child', function ($q) use ($user) {
                    $q->where(function ($subQ) use ($user) {
                        $subQ->where('access_level', 'public')
                            ->orWhere('owner_id', $user->id)
                            ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                                $permQ->where('user_id', $user->id)
                                      ->whereIn('permission_type', ['view', 'edit']);
                            })
                            ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                                $permQ->whereNotNull('group_id')
                                      ->whereIn('permission_type', ['view', 'edit'])
                                      ->whereHas('group', function ($groupQ) use ($user) {
                                          $groupQ->whereHas('users', function ($userQ) use ($user) {
                                              $userQ->where('user_id', $user->id);
                                          });
                                      });
                            });
                    });
                });
                $objectQuery->whereHas('parent', function ($q) use ($user) {
                    $q->where(function ($subQ) use ($user) {
                        $subQ->where('access_level', 'public')
                            ->orWhere('owner_id', $user->id)
                            ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                                $permQ->where('user_id', $user->id)
                                      ->whereIn('permission_type', ['view', 'edit']);
                            })
                            ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                                $permQ->whereNotNull('group_id')
                                      ->whereIn('permission_type', ['view', 'edit'])
                                      ->whereHas('group', function ($groupQ) use ($user) {
                                          $groupQ->whereHas('users', function ($userQ) use ($user) {
                                              $userQ->where('user_id', $user->id);
                                          });
                                      });
                            });
                    });
                });
            }
            // Admins can see all connections (no additional whereHas needed)
            
            $allSecondLevelAsSubject = $subjectQuery->get();
            $allSecondLevelAsObject = $objectQuery->get();
            
            // Process subject connections
            foreach ($allSecondLevelAsSubject as $connection) {
                if (!$connection->child || !$connection->parent) {
                    continue;
                }
                
                $parentSpan = $firstLevelSpanMap[$connection->parent_id] ?? null;
                if (!$parentSpan) {
                    continue;
                }
                
                $childSpan = $connection->child;
                if (in_array($childSpan->id, $seenSpanIds) || $this->shouldExcludeSpan($childSpan)) {
                    continue;
                }
                
                $secondLevelConnections->push([
                    'span' => $childSpan,
                    'connection_type' => $connection->type,
                    'connection' => $connection,
                    'parent_span' => $parentSpan,
                    'parent_span_id' => $parentSpan->id
                ]);
                $seenSpanIds[] = $childSpan->id; // Track to avoid duplicates
            }
            
            // Process object connections
            foreach ($allSecondLevelAsObject as $connection) {
                if (!$connection->parent || !$connection->child) {
                    continue;
                }
                
                $childSpan = $firstLevelSpanMap[$connection->child_id] ?? null;
                if (!$childSpan) {
                    continue;
                }
                
                $parentSpan = $connection->parent;
                if (in_array($parentSpan->id, $seenSpanIds) || $this->shouldExcludeSpan($parentSpan)) {
                    continue;
                }
                
                $secondLevelConnections->push([
                    'span' => $parentSpan,
                    'connection_type' => $connection->type,
                    'connection' => $connection,
                    'parent_span' => $childSpan,
                    'parent_span_id' => $childSpan->id
                ]);
                $seenSpanIds[] = $parentSpan->id; // Track to avoid duplicates
            }
        }
        
        // Deduplicate across all second-level connections manually
        $uniqueSecondLevel = collect();
        $seenSecondLevelIds = [];
        foreach ($secondLevelConnections as $item) {
            $spanId = $item['span']->id;
            if (!in_array($spanId, $seenSecondLevelIds)) {
                $seenSecondLevelIds[] = $spanId;
                $uniqueSecondLevel->push($item);
            }
        }
        
        $secondLevelConnections = $uniqueSecondLevel
            ->sortBy(function ($item) {
                // Sort by parent span name, then connection type, then span name
                $parentName = $item['parent_span']->name ?? '';
                $connectionTypeName = $item['connection_type'] ? $item['connection_type']->forward_predicate : '';
                return $parentName . '|' . $connectionTypeName . '|' . $item['span']->name;
            })
            ->values();

        // Get span types for candidate span creation
        $spanTypes = SpanType::where('type_id', '!=', 'connection')->get();
        
        // Get connection types for creating connections from candidate spans back to the research subject
        // Include allowed_span_types for filtering - prepare data for JavaScript
        $connectionTypes = ConnectionType::orderBy('forward_predicate')->get()->map(function($type) {
            return [
                'type' => $type->type,
                'forward_predicate' => $type->forward_predicate,
                'forward_description' => $type->forward_description,
                'inverse_predicate' => $type->inverse_predicate,
                'inverse_description' => $type->inverse_description,
                'allowed_span_types' => $type->allowed_span_types ?: []
            ];
        });

        return view('research.show', [
            'span' => $span,
            'article' => $article,
            'wikidataEntity' => $wikidataEntity ?? null,
            'isPrivateIndividual' => $isPrivateIndividual,
            'firstLevelConnections' => $allFirstLevel,
            'secondLevelConnections' => $secondLevelConnections,
            'spanTypes' => $spanTypes,
            'connectionTypes' => $connectionTypes
        ]);
    }

    /**
     * Extract Wikipedia article title from sources field
     * 
     * @param array $sources The sources array (can contain strings or objects with 'url' key)
     * @return string|null The Wikipedia article title if found, null otherwise
     */
    private function extractWikipediaTitleFromSources(array $sources): ?string
    {
        foreach ($sources as $source) {
            $url = null;
            
            // Handle both string URLs and array objects with 'url' key
            if (is_string($source)) {
                $url = $source;
            } elseif (is_array($source) && isset($source['url'])) {
                $url = $source['url'];
            }
            
            if ($url && strpos($url, 'wikipedia.org') !== false) {
                // Extract title from Wikipedia URL
                // URLs can be: https://en.wikipedia.org/wiki/Article_Title
                // or: https://en.wikipedia.org/wiki/Article_Title?params
                if (preg_match('/wikipedia\.org\/wiki\/([^?#]+)/', $url, $matches)) {
                    $title = urldecode($matches[1]);
                    // Replace underscores with spaces
                    $title = str_replace('_', ' ', $title);
                    return $title;
                }
            }
        }
        
        return null;
    }

    /**
     * Clean Wikipedia HTML to remove resource loader references
     */
    private function cleanWikipediaHtml(string $html): string
    {
        // Use DOMDocument to parse and clean the HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Remove resource loader links
        $resourceLinks = $xpath->query("//link[contains(@href, 'load.php')] | //link[starts-with(@href, '/w/')]");
        foreach ($resourceLinks as $link) {
            $link->parentNode->removeChild($link);
        }

        // Remove resource loader scripts
        $resourceScripts = $xpath->query("//script[contains(@src, 'load.php')] | //script[starts-with(@src, '/w/')]");
        foreach ($resourceScripts as $script) {
            $script->parentNode->removeChild($script);
        }

        // Remove base tags
        $baseTags = $xpath->query("//base");
        foreach ($baseTags as $base) {
            $base->parentNode->removeChild($base);
        }

        // Get the cleaned HTML
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $cleanedHtml = '';
            foreach ($body->childNodes as $node) {
                $cleanedHtml .= $dom->saveHTML($node);
            }
            return $cleanedHtml;
        }

        return $html;
    }
}
