<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WikimediaCommonsApiService;
use App\Models\User;
use App\Models\Span;
use App\Models\SpanType;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WikimediaCommonsImportController extends Controller
{
    protected WikimediaCommonsApiService $wikimediaService;

    public function __construct(WikimediaCommonsApiService $wikimediaService)
    {
        $this->middleware(['auth', 'admin']);
        $this->wikimediaService = $wikimediaService;
    }

    /**
     * Show the Wikimedia Commons import interface
     */
    public function index(Request $request)
    {
        $initialSearch = $request->get('search', '');
        $originatingSpanUuid = $request->get('span_uuid', '');
        
        // If we have a search term but no span_uuid, try to find the span by name
        $originatingSpanName = '';
        $originatingSpanId = '';
        if ($initialSearch && !$originatingSpanUuid) {
            $span = Span::where('name', 'ilike', $initialSearch)
                ->orWhere('name', 'ilike', '%' . $initialSearch . '%')
                ->first();
            if ($span) {
                $originatingSpanUuid = $span->uuid;
                $originatingSpanName = $span->name;
                $originatingSpanId = $span->id;
            }
        } elseif ($originatingSpanUuid) {
            // Get the span details if we have the UUID
            $span = Span::where('id', $originatingSpanUuid)->first();
            if ($span) {
                $originatingSpanName = $span->name;
                $originatingSpanId = $span->id;
            }
        }
        
        return view('admin.import.wikimedia-commons.index', compact('initialSearch', 'originatingSpanId', 'originatingSpanName', 'originatingSpanUuid'));
    }

    /**
     * Search for images in Wikimedia Commons
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        try {
            $results = $this->wikimediaService->searchImages($query, $page, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search Wikimedia Commons images', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for images by year in Wikimedia Commons
     */
    public function searchByYear(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:100',
            'year' => 'required|integer|min:1800|max:' . (date('Y') + 1),
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $year = $request->input('year');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        try {
            $results = $this->wikimediaService->searchImagesByYear($query, $year, $page, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search Wikimedia Commons images by year', [
                'error' => $e->getMessage(),
                'query' => $query,
                'year' => $year
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific image
     */
    public function getImageData(Request $request)
    {
        $request->validate([
            'image_id' => 'required|string'
        ]);

        $imageId = $request->input('image_id');
        
        Log::info('Wikimedia Commons getImageData called', ['image_id' => $imageId]);

        try {
            // Get the image data
            $imageData = $this->wikimediaService->getImage($imageId);
            
            if (!$imageData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $imageData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Wikimedia Commons image data', [
                'error' => $e->getMessage(),
                'image_id' => $imageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get image data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview what will be imported
     */
    public function previewImport(Request $request)
    {
        $request->validate([
            'image_id' => 'required|string'
        ]);

        $imageId = $request->input('image_id');
        
        Log::info('Wikimedia Commons previewImport called', ['image_id' => $imageId]);

        try {
            // Get the image data
            $imageData = $this->wikimediaService->getImage($imageId);
            
            if (!$imageData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Check if image already exists
            $existingImage = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'photo')
                ->whereJsonContains('metadata->wikimedia_id', $imageId)
                ->first();

            // Look for potential spans to connect to based on the image title/description
            $potentialSpans = $this->findPotentialSpans($imageData);

            // Clean the description and author for preview
            $cleanDescription = $this->cleanDescription($imageData['metadata']['description'] ?? '');
            $cleanAuthor = $this->cleanDescription($imageData['metadata']['author'] ?? '');
            $previewDescription = !empty($cleanDescription) ? $cleanDescription : 'Image from Wikimedia Commons';
            
            $preview = [
                'image' => $imageData,
                'existing_image' => $existingImage ? [
                    'id' => $existingImage->id,
                    'name' => $existingImage->name
                ] : null,
                'potential_spans' => $potentialSpans,
                'will_create_image' => !$existingImage,
                'import_plan' => [
                    'create_image' => !$existingImage,
                    'image_name' => $this->extractImageName($imageData['title']),
                    'image_description' => $previewDescription,
                    'image_date' => $this->parseDate($imageData['metadata']['date'] ?? ''),
                    'image_author' => $cleanAuthor ?: 'Unknown',
                    'image_license' => $imageData['metadata']['license'] ?? 'Unknown'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview Wikimedia Commons import', [
                'error' => $e->getMessage(),
                'image_id' => $imageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import the image
     */
    public function importImage(Request $request)
    {
        $request->validate([
            'image_id' => 'required|string',
            'target_span_id' => 'nullable|string'
        ]);

        $imageId = $request->input('image_id');
        $targetSpanId = $request->input('target_span_id');
        
        Log::info('Wikimedia Commons importImage called', [
            'image_id' => $imageId,
            'target_span_id' => $targetSpanId
        ]);

        try {
            DB::beginTransaction();

            // Get the image data
            $imageData = $this->wikimediaService->getImage($imageId);
            
            if (!$imageData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Check if image already exists
            $existingImage = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'photo')
                ->whereJsonContains('metadata->wikimedia_id', $imageId)
                ->first();

            if ($existingImage) {
                $imageSpan = $existingImage;
            } else {
                // Create the image span
                $imageSpan = $this->createImageSpan($imageData, Auth::user());
            }

            // Create connection to target span if provided
            if ($targetSpanId) {
                // Try to find span by ID first, then by UUID
                $targetSpan = Span::find($targetSpanId);
                if (!$targetSpan) {
                    $targetSpan = Span::where('uuid', $targetSpanId)->first();
                }
                
                if (!$targetSpan) {
                    throw new \Exception('Target span not found');
                }
                
                if (!$this->connectionExists($imageSpan, $targetSpan)) {
                    $this->createSubjectOfConnection($imageSpan, $targetSpan, Auth::user());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Image imported successfully',
                'data' => [
                    'image_span' => [
                        'id' => $imageSpan->id,
                        'name' => $imageSpan->name
                    ],
                    'target_span' => $targetSpanId ? [
                        'id' => $targetSpan->id,
                        'name' => $targetSpan->name
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to import Wikimedia Commons image', [
                'error' => $e->getMessage(),
                'image_id' => $imageId,
                'target_span_id' => $targetSpanId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear the cache
     */
    public function clearCache()
    {
        try {
            $this->wikimediaService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear Wikimedia Commons cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create an image span from Wikimedia Commons data
     */
    protected function createImageSpan(array $imageData, User $user): Span
    {
        // Get thing span type
        $thingType = SpanType::where('type_id', 'thing')->first();
        
        if (!$thingType) {
            throw new \Exception('Thing span type not found in database');
        }

        // Extract image name
        $imageName = $this->extractImageName($imageData['title']);
        
        // Parse date
        $dateInfo = $this->parseDate($imageData['metadata']['date'] ?? '');
        
        // Determine state based on date availability
        $state = $dateInfo['year'] ? 'complete' : 'placeholder';

        // Clean the description and author
        $cleanDescription = $this->cleanDescription($imageData['metadata']['description'] ?? '');
        $cleanAuthor = $this->cleanDescription($imageData['metadata']['author'] ?? '');
        $finalDescription = !empty($cleanDescription) ? $cleanDescription : 'Image from Wikimedia Commons';
        
        // Create the span
        $span = Span::create([
            'name' => $imageName,
            'type_id' => $thingType->type_id,
            'description' => $finalDescription,
            'start_year' => $dateInfo['year'],
            'start_month' => $dateInfo['month'],
            'start_day' => $dateInfo['day'],
            'end_year' => $dateInfo['year'], // Photo spans typically have same start/end date
            'end_month' => $dateInfo['month'],
            'end_day' => $dateInfo['day'],
            'metadata' => [
                'subtype' => 'photo',
                'wikimedia_id' => $imageData['id'],
                'thumbnail_url' => $imageData['url'],
                'medium_url' => $imageData['url'], // Use same URL for now
                'large_url' => $imageData['url'],
                'original_url' => $imageData['url'],
                'title' => $imageData['title'],
                'description' => $cleanDescription,
                'date' => $imageData['metadata']['date'],
                'author' => $cleanAuthor,
                'license' => $imageData['metadata']['license'],
                'license_url' => $imageData['metadata']['license_url'] ?? '',
                'requires_attribution' => $imageData['metadata']['requires_attribution'] ?? true,
                'source' => 'Wikimedia Commons',
                'description_url' => $imageData['description_url']
            ],
            'sources' => [
                [
                    'type' => 'wikimedia_commons',
                    'name' => 'Wikimedia Commons',
                    'url' => $imageData['description_url'],
                    'author' => $cleanAuthor,
                    'license' => $imageData['metadata']['license']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => $state
        ]);

        return $span;
    }

    /**
     * Create a subject_of connection between image and target span
     */
    protected function createSubjectOfConnection(Span $imageSpan, Span $targetSpan, User $user): void
    {
        // Get subject_of connection type
        $connectionType = ConnectionType::where('type', 'subject_of')->first();
        
        if (!$connectionType) {
            throw new \Exception('Subject_of connection type not found in database');
        }

        // Create connection span
        $connectionSpan = Span::create([
            'name' => "{$imageSpan->name} features {$targetSpan->name}",
            'type_id' => 'connection',
            'access_level' => 'public',
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'subject_of',
                'timeless' => true
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        // Create the connection
        Connection::create([
            'parent_id' => $imageSpan->id,
            'child_id' => $targetSpan->id,
            'type_id' => 'subject_of',
            'connection_span_id' => $connectionSpan->id,
        ]);
    }

    /**
     * Check if a subject_of connection already exists between two spans
     */
    protected function connectionExists(Span $imageSpan, Span $targetSpan): bool
    {
        return Connection::where('parent_id', $imageSpan->id)
            ->where('child_id', $targetSpan->id)
            ->where('type_id', 'subject_of')
            ->exists();
    }

    /**
     * Find potential spans to connect the image to based on image metadata
     */
    protected function findPotentialSpans(array $imageData): array
    {
        $potentialSpans = [];
        
        // Extract keywords from title and description
        $keywords = [];
        
        // Add title keywords
        $title = $imageData['title'] ?? '';
        $titleKeywords = array_filter(explode(' ', str_replace(['File:', '_', '.'], ' ', $title)));
        $keywords = array_merge($keywords, $titleKeywords);
        
        // Add description keywords (use cleaned description)
        $description = $this->cleanDescription($imageData['metadata']['description'] ?? '');
        $descKeywords = array_filter(explode(' ', str_replace([',', '.', ';', ':', '!', '?'], ' ', $description)));
        $keywords = array_merge($keywords, $descKeywords);
        
        // Remove common words and short words
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'from', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'];
        $keywords = array_filter($keywords, function($word) use ($commonWords) {
            return strlen($word) > 2 && !in_array(strtolower($word), $commonWords);
        });
        
        // Search for spans with these keywords
        foreach (array_slice($keywords, 0, 5) as $keyword) { // Limit to first 5 keywords
            $spans = Span::where('name', 'ilike', "%{$keyword}%")
                ->orWhere('description', 'ilike', "%{$keyword}%")
                ->limit(5)
                ->get(['id', 'name', 'type_id', 'description']);
                
            foreach ($spans as $span) {
                if (!isset($potentialSpans[$span->id])) {
                    $potentialSpans[$span->id] = [
                        'id' => $span->id,
                        'name' => $span->name,
                        'type_id' => $span->type_id,
                        'description' => $span->description,
                        'relevance_score' => 0
                    ];
                }
                $potentialSpans[$span->id]['relevance_score']++;
            }
        }
        
        // Sort by relevance score and return top 10
        usort($potentialSpans, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
        
        return array_slice($potentialSpans, 0, 10);
    }

    /**
     * Parse date from Wikimedia Commons format
     */
    protected function parseDate(string $dateString): array
    {
        $date = [
            'year' => null,
            'month' => null,
            'day' => null
        ];

        if (empty($dateString)) {
            return $date;
        }

        // Try to parse various date formats
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString, $matches)) {
            $date['year'] = (int) $matches[1];
            $date['month'] = (int) $matches[2];
            $date['day'] = (int) $matches[3];
        } elseif (preg_match('/(\d{4})/', $dateString, $matches)) {
            $date['year'] = (int) $matches[1];
        }

        return $date;
    }

    /**
     * Extract a clean image name from Wikimedia Commons title
     */
    protected function extractImageName(string $title): string
    {
        // Remove "File:" prefix
        $name = str_replace('File:', '', $title);
        
        // Remove file extension
        $name = pathinfo($name, PATHINFO_FILENAME);
        
        // Replace underscores with spaces
        $name = str_replace('_', ' ', $name);
        
        // Limit length
        if (strlen($name) > 100) {
            $name = substr($name, 0, 97) . '...';
        }

        return $name;
    }

    /**
     * Clean MediaWiki markup from description text
     */
    protected function cleanDescription(string $description): string
    {
        if (empty($description)) {
            return '';
        }

        // First, extract content from simple templates
        $description = $this->extractTemplateContent($description);

        // Remove nested templates recursively
        $description = $this->removeNestedTemplates($description);

        // Remove language prefixes like "{{en|1=" and closing "}}"
        $description = preg_replace('/\{\{[a-z]{2}\|1=(.*?)\}\}/', '$1', $description);
        
        // Remove simple language tags like "{{en|" and "}}"
        $description = preg_replace('/\{\{[a-z]{2}\|(.*?)\}\}/', '$1', $description);
        
        // Remove any remaining language tags
        $description = preg_replace('/\{\{[a-z]{2}\}\}/', '', $description);
        
        // Remove wiki links [[text]] -> text
        $description = preg_replace('/\[\[([^|\]]*?)\]\]/', '$1', $description);
        
        // Remove wiki links with pipes [[text|display]] -> display
        $description = preg_replace('/\[\[([^|]*?)\|([^\]]*?)\]\]/', '$2', $description);
        
        // Remove bold markup '''text''' -> text
        $description = preg_replace('/\'\'\'(.*?)\'\'\'/', '$1', $description);
        
        // Remove italic markup ''text'' -> text
        $description = preg_replace('/\'\'(.*?)\'\'/', '$1', $description);
        
        // Remove HTML tags
        $description = strip_tags($description);
        
        // Clean up extra whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        return $description;
    }

    /**
     * Recursively remove nested MediaWiki templates
     */
    protected function removeNestedTemplates(string $text): string
    {
        $original = $text;
        $maxIterations = 10; // Prevent infinite loops
        $iteration = 0;
        
        while ($iteration < $maxIterations) {
            // Find and remove the innermost templates first
            $text = preg_replace('/\{\{[^}]*\{\{[^}]*\}\}[^}]*\}\}/', '', $text);
            $text = preg_replace('/\{\{[^}]*\}\}/', '', $text);
            
            // If no changes were made, we're done
            if ($text === $original) {
                break;
            }
            
            $original = $text;
            $iteration++;
        }
        
        return $text;
    }

    /**
     * Extract content from simple MediaWiki templates
     */
    protected function extractTemplateContent(string $text): string
    {
        // Handle specific cases first
        // {{European Union|...}} -> European Union (handle nested templates)
        $text = preg_replace('/\{\{European Union\|[^}]*\{\{[^}]*\}\}[^}]*\}\}/', 'European Union', $text);
        $text = preg_replace('/\{\{European Union\|[^}]*\}\}/', 'European Union', $text);
        
        // Extract content from User templates {{User|John Doe}} -> John Doe
        $text = preg_replace('/\{\{User\|([^}]*)\}\}/', '$1', $text);
        
        // For other simple templates, try to extract the meaningful part
        // {{TemplateName|meaningful content|other params}} -> meaningful content
        $text = preg_replace('/\{\{([^|}]+)\|([^|}]*)\|[^}]*\}\}/', '$2', $text);
        
        // For two-parameter templates {{TemplateName|content}} -> content
        $text = preg_replace('/\{\{([^|}]+)\|([^}]*)\}\}/', '$2', $text);
        
        return $text;
    }
}

