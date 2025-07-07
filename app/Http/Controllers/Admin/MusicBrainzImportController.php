<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\SpanController;
use App\Models\Connection;
use App\Services\MusicBrainzImportService;

class MusicBrainzImportController extends Controller
{
    protected $spanController;
    protected $musicBrainzService;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->spanController = new SpanController();
        $this->musicBrainzService = new MusicBrainzImportService();
    }

    public function index()
    {
        $bandType = SpanType::where('type_id', 'band')->first();
        
        if (!$bandType) {
            return view('admin.import.musicbrainz.index', [
                'bands' => collect(),
                'error' => 'Band span type not found. Please create a span type with type_id "band" first.'
            ]);
        }

        // Get bands
        $bands = Span::where('type_id', $bandType->type_id)
            ->orderBy('name')
            ->get();

        // Get spans connected to the "musician" role
        $musicianRole = Span::where('name', 'Musician')->first();
        $musicians = collect();
        
        if ($musicianRole) {
            $musicianConnections = Connection::where('child_id', $musicianRole->id)
                ->where('type_id', 'has_role')
                ->with('parent')
                ->get();
            
            $musicians = $musicianConnections->map(function ($connection) {
                return $connection->parent;
            })->sortBy('name');
        }

        // Combine bands and musicians
        $allArtists = $bands->concat($musicians)->sortBy('name');

        // Get import statistics for each artist
        $importStats = $this->getImportStatistics($allArtists);

        return view('admin.import.musicbrainz.index', compact('allArtists', 'importStats'));
    }

    /**
     * Get import statistics for artists
     */
    private function getImportStatistics($artists)
    {
        $stats = [];
        
        foreach ($artists as $artist) {
            // Count albums created by this artist
            $albumCount = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'album')
                ->whereHas('connectionsAsObject', function ($query) use ($artist) {
                    $query->where('parent_id', $artist->id)
                          ->where('type_id', 'created');
                })
                ->count();

            // Count tracks created by this artist (through albums)
            $trackCount = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'track')
                ->whereHas('connectionsAsObject', function ($query) use ($artist) {
                    $query->whereHas('parent', function ($albumQuery) use ($artist) {
                        $albumQuery->where('type_id', 'thing')
                                  ->whereJsonContains('metadata->subtype', 'album')
                                  ->whereHas('connectionsAsObject', function ($albumConnectionQuery) use ($artist) {
                                      $albumConnectionQuery->where('parent_id', $artist->id)
                                                          ->where('type_id', 'created');
                                  });
                    })
                    ->where('type_id', 'contains');
                })
                ->count();

            // Get list of albums with their track counts
            $albums = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'album')
                ->whereHas('connectionsAsObject', function ($query) use ($artist) {
                    $query->where('parent_id', $artist->id)
                          ->where('type_id', 'created');
                })
                ->with(['connectionsAsSubject' => function ($query) {
                    $query->where('type_id', 'contains');
                }])
                ->get()
                ->map(function ($album) {
                    return [
                        'id' => $album->id,
                        'name' => $album->name,
                        'track_count' => $album->connectionsAsSubject->count(),
                        'release_date' => $album->start_year ? 
                            ($album->start_year . 
                             ($album->start_month ? '-' . str_pad($album->start_month, 2, '0', STR_PAD_LEFT) : '') .
                             ($album->start_day ? '-' . str_pad($album->start_day, 2, '0', STR_PAD_LEFT) : '')) : 
                            null
                    ];
                })
                ->sortBy('release_date');

            $stats[$artist->id] = [
                'album_count' => $albumCount,
                'track_count' => $trackCount,
                'albums' => $albums
            ];
        }

        return $stats;
    }

    public function search(Request $request)
    {
        $request->validate([
            'band_id' => 'required|exists:spans,id',
        ]);

        try {
            $band = Span::findOrFail($request->band_id);
            
            Log::info('Searching MusicBrainz for artist', [
                'band_name' => $band->name,
            ]);

            $artists = $this->musicBrainzService->searchArtist($band->name);
            
            Log::info('Parsed artist search results', [
                'artists_count' => count($artists),
                'first_artist' => $artists[0] ?? null,
            ]);

            return response()->json([
                'artists' => $artists,
            ]);
        } catch (\Exception $e) {
            Log::error('MusicBrainz search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to search MusicBrainz',
            ], 500);
        }
    }

    public function showDiscography(Request $request)
    {
        $request->validate([
            'band_id' => 'required|exists:spans,id',
            'mbid' => 'required|string',
        ]);

        try {
            Log::info('Fetching discography from MusicBrainz', [
                'mbid' => $request->mbid,
            ]);

            $albums = $this->musicBrainzService->getDiscography($request->mbid);
            
            // Filter out unwanted album types
            $albums = collect($albums)
                ->filter(function ($album) {
                    // Must not have any of these words in the title
                    $excludeWords = [
                        'live', 'compilation', 'b-sides', 'rarities', 'best of',
                        'greatest hits', 'box set', 'boxset', 'unplugged',
                        'interview', 'session', 'bootleg', 'remix', 'collection'
                    ];
                    $title = strtolower($album['title']);
                    foreach ($excludeWords as $word) {
                        if (str_contains($title, $word)) {
                            return false;
                        }
                    }
                    return true;
                })
                ->map(function ($album) {
                    return [
                        'id' => $album['id'],
                        'title' => $album['title'],
                        'first_release_date' => $album['first_release_date'],
                        'type' => $album['type'],
                    ];
                })
                ->sortBy('first_release_date')
                ->values();

            Log::info('Filtered albums', [
                'count' => $albums->count(),
                'first_album' => $albums->first(),
            ]);

            return response()->json([
                'albums' => $albums,
            ]);
        } catch (\Exception $e) {
            Log::error('MusicBrainz discography error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to fetch discography',
            ], 500);
        }
    }

    public function showTracks(Request $request)
    {
        $request->validate([
            'release_group_id' => 'required|string',
        ]);

        try {
            Log::info('Fetching tracks from MusicBrainz', [
                'release_group_id' => $request->release_group_id,
            ]);

            $tracks = $this->musicBrainzService->getTracks($request->release_group_id);
            
            Log::info('Fetched tracks', [
                'count' => count($tracks),
                'first_track' => $tracks[0] ?? null,
            ]);

            return response()->json([
                'tracks' => $tracks,
            ]);
        } catch (\Exception $e) {
            Log::error('MusicBrainz tracks error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to fetch tracks',
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'band_id' => 'required|exists:spans,id',
            'albums' => 'required|array',
            'albums.*.id' => 'required|string',
            'albums.*.title' => 'required|string',
            'albums.*.first_release_date' => 'nullable|date',
            'albums.*.tracks' => 'nullable|array',
            'albums.*.tracks.*.id' => 'required|string',
            'albums.*.tracks.*.title' => 'required|string',
            'albums.*.tracks.*.length' => 'nullable|integer',
            'albums.*.tracks.*.isrc' => 'nullable|string',
            'albums.*.tracks.*.artist_credits' => 'nullable|string',
            'albums.*.tracks.*.first_release_date' => 'nullable|date',
        ]);

        try {
            $band = Span::findOrFail($request->band_id);
            
            $imported = [];
            foreach ($request->albums as $album) {
                // Clean the album title by removing date patterns and trailing spaces
                $cleanTitle = preg_replace('/\s+\d{4}(-\d{2}(-\d{2})?)?$/', '', $album['title']);
                $cleanTitle = trim($cleanTitle);

                // Check if album already exists
                $albumSpan = Span::whereJsonContains('metadata->musicbrainz_id', $album['id'])->first();
                
                if ($albumSpan) {
                    // Update existing album
                    $updateData = [
                        'name' => $cleanTitle,
                        'metadata' => array_merge($albumSpan->metadata ?? [], [
                            'type' => $album['type'] ?? null,
                            'disambiguation' => $album['disambiguation'] ?? null,
                            'subtype' => 'album'
                        ]),
                        'updater_id' => $request->user()->id,
                    ];
                    
                    // Only set date fields if we have a release date and it's not today
                    if (!empty($album['first_release_date'])) {
                        $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                        $today = strtotime('today');
                        
                        // Don't set today's date as a release date
                        if ($releaseDate !== $today) {
                            $updateData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                                $updateData['start_month'] = date('m', $releaseDate);
                                $updateData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                                $updateData['start_month'] = date('m', $releaseDate);
                            }
                        }
                    }
                    
                    $albumSpan->update($updateData);
                } else {
                    // Determine state based on whether we have release date and it's not today
                    $hasReleaseDate = !empty($album['first_release_date']);
                    $albumState = 'placeholder'; // Default to placeholder
                    
                    if ($hasReleaseDate) {
                        $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                        $today = strtotime('today');
                        
                        // Only set to complete if we have a valid release date that's not today
                        if ($releaseDate !== $today) {
                            $albumState = 'complete';
                        }
                    }
                    
                    // Prepare album data
                    $albumData = [
                        'name' => $cleanTitle,
                        'type_id' => 'thing',
                        'state' => $albumState,
                        'access_level' => 'private',
                        'metadata' => [
                            'musicbrainz_id' => $album['id'],
                            'type' => $album['type'] ?? null,
                            'disambiguation' => $album['disambiguation'] ?? null,
                            'subtype' => 'album'
                        ],
                        'owner_id' => $request->user()->id,
                        'updater_id' => $request->user()->id,
                    ];
                    
                    // Only set date fields if we have a release date and it's not today
                    if ($hasReleaseDate) {
                        $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                        $today = strtotime('today');
                        
                        // Don't set today's date as a release date
                        if ($releaseDate !== $today) {
                            $albumData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                                $albumData['start_month'] = date('m', $releaseDate);
                                $albumData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                                $albumData['start_month'] = date('m', $releaseDate);
                            }
                        }
                    }
                    
                    // Create new album span
                    $albumSpan = Span::create($albumData);

                    // Create connection span for the created connection
                    $hasConnectionDate = !empty($album['first_release_date']);
                    $connectionState = 'placeholder'; // Default to placeholder
                    
                    if ($hasConnectionDate) {
                        $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                        $today = strtotime('today');
                        
                        // Only set to complete if we have a valid release date that's not today
                        if ($releaseDate !== $today) {
                            $connectionState = 'complete';
                        }
                    }
                    
                    $connectionData = [
                        'name' => "{$band->name} created {$albumSpan->name}",
                        'type_id' => 'connection',
                        'state' => $connectionState,
                        'access_level' => 'private',
                        'metadata' => [
                            'connection_type' => 'created'
                        ],
                        'owner_id' => $request->user()->id,
                        'updater_id' => $request->user()->id,
                    ];
                    
                    // Only set date fields if we have a release date and it's not today
                    if ($hasConnectionDate) {
                        $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                        $today = strtotime('today');
                        
                        // Don't set today's date as a release date
                        if ($releaseDate !== $today) {
                            $connectionData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                                $connectionData['start_month'] = date('m', $releaseDate);
                                $connectionData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                                $connectionData['start_month'] = date('m', $releaseDate);
                            }
                        }
                    }
                    
                    $connectionSpan1 = Span::create($connectionData);

                    // Create connection between band and album
                    Connection::create([
                        'parent_id' => $band->id,
                        'child_id' => $albumSpan->id,
                        'type_id' => 'created',
                        'connection_span_id' => $connectionSpan1->id
                    ]);
                }

                // Import tracks if available
                if (!empty($album['tracks'])) {
                    foreach ($album['tracks'] as $track) {
                        // Check if track already exists
                        $trackSpan = Span::whereJsonContains('metadata->musicbrainz_id', $track['id'])->first();

                        if ($trackSpan) {
                            // Update existing track
                            $updateData = [
                                'name' => $track['title'],
                                'metadata' => array_merge($trackSpan->metadata ?? [], [
                                    'isrc' => $track['isrc'],
                                    'length' => $track['length'],
                                    'artist_credits' => $track['artist_credits'],
                                    'subtype' => 'track'
                                ]),
                                'updater_id' => $request->user()->id,
                            ];
                            
                            // Only update date fields if we have a release date and it's not today
                            if (!empty($track['first_release_date'])) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                
                                // Don't set today's date as a release date
                                if ($releaseDate !== $today) {
                                    $updateData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                                    // Set month/day based on available precision
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                        $updateData['start_month'] = date('m', $releaseDate);
                                        $updateData['start_day'] = date('d', $releaseDate);
                                    } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                        $updateData['start_month'] = date('m', $releaseDate);
                                    }
                                }
                            }
                            
                            $trackSpan->update($updateData);
                        } else {
                            // Determine state based on whether we have release date and it's not today
                            $hasTrackReleaseDate = !empty($track['first_release_date']);
                            $trackState = 'placeholder'; // Default to placeholder
                            
                            if ($hasTrackReleaseDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                
                                // Only set to complete if we have a valid release date that's not today
                                if ($releaseDate !== $today) {
                                    $trackState = 'complete';
                                }
                            }
                            
                            // Prepare track data
                            $trackData = [
                                'name' => $track['title'],
                                'type_id' => 'thing',
                                'state' => $trackState,
                                'access_level' => 'private',
                                'metadata' => [
                                    'musicbrainz_id' => $track['id'],
                                    'isrc' => $track['isrc'],
                                    'length' => $track['length'],
                                    'artist_credits' => $track['artist_credits'],
                                    'subtype' => 'track'
                                ],
                                'owner_id' => $request->user()->id,
                                'updater_id' => $request->user()->id,
                            ];
                            
                            // Only set date fields if we have a release date and it's not today
                            if ($hasTrackReleaseDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                
                                // Don't set today's date as a release date
                                if ($releaseDate !== $today) {
                                    $trackData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                                    // Set month/day based on available precision
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                        $trackData['start_month'] = date('m', $releaseDate);
                                        $trackData['start_day'] = date('d', $releaseDate);
                                    } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                        $trackData['start_month'] = date('m', $releaseDate);
                                    }
                                }
                            }
                            
                            // Create new track span
                            $trackSpan = Span::create($trackData);

                            // Create connection span for the contains connection
                            $hasTrackConnectionDate = !empty($track['first_release_date']);
                            $trackConnectionState = 'placeholder'; // Default to placeholder
                            
                            if ($hasTrackConnectionDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                
                                // Only set to complete if we have a valid release date that's not today
                                if ($releaseDate !== $today) {
                                    $trackConnectionState = 'complete';
                                }
                            }
                            
                            $trackConnectionData = [
                                'name' => "{$albumSpan->name} contains {$trackSpan->name}",
                                'type_id' => 'connection',
                                'state' => $trackConnectionState,
                                'access_level' => 'private',
                                'metadata' => [
                                    'connection_type' => 'contains'
                                ],
                                'owner_id' => $request->user()->id,
                                'updater_id' => $request->user()->id,
                            ];
                            
                            // Only set date fields if we have a release date and it's not today
                            if ($hasTrackConnectionDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                
                                // Don't set today's date as a release date
                                if ($releaseDate !== $today) {
                                    $trackConnectionData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                                    // Set month/day based on available precision
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                        $trackConnectionData['start_month'] = date('m', $releaseDate);
                                        $trackConnectionData['start_day'] = date('d', $releaseDate);
                                    } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                        $trackConnectionData['start_month'] = date('m', $releaseDate);
                                    }
                                }
                            }
                            
                            $connectionSpan2 = Span::create($trackConnectionData);

                            // Create connection between album and track if it doesn't exist
                            if (!Connection::where('parent_id', $albumSpan->id)
                                ->where('child_id', $trackSpan->id)
                                ->where('type_id', 'contains')
                                ->exists()) {
                                Connection::create([
                                    'parent_id' => $albumSpan->id,
                                    'child_id' => $trackSpan->id,
                                    'type_id' => 'contains',
                                    'connection_span_id' => $connectionSpan2->id
                                ]);
                            }
                        }
                    }
                }

                $imported[] = $albumSpan;
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully imported ' . count($imported) . ' albums',
                'imported' => $imported,
            ]);
        } catch (\Exception $e) {
            Log::error('MusicBrainz import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to import albums',
            ], 500);
        }
    }

    /**
     * Parse a release date from MusicBrainz, handling year-only dates properly
     */
    private function parseReleaseDate(string $dateString): int
    {
        // If it's just a 4-digit year, don't use strtotime as it interprets as time
        if (preg_match('/^\d{4}$/', $dateString)) {
            return strtotime($dateString . '-01-01');
        }
        
        // If it's YYYY-MM format, don't use strtotime as it might interpret as time
        if (preg_match('/^\d{4}-\d{2}$/', $dateString)) {
            return strtotime($dateString . '-01');
        }
        
        // Otherwise, use strtotime as normal
        return strtotime($dateString);
    }

    /**
     * Extract year from a release date string, handling year-only dates properly
     */
    private function extractYearFromDate(string $dateString): ?int
    {
        // If it's just a 4-digit year, extract directly
        if (preg_match('/^\d{4}$/', $dateString)) {
            return (int)$dateString;
        }
        
        // For all other formats, use strtotime and extract year
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return null;
        }
        
        return (int)date('Y', $timestamp);
    }
} 