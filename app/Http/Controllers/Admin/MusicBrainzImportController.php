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
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
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

            // Log raw album titles and sample data structure
            $rawTitles = collect($albums)->pluck('title')->all();
            $sampleAlbum = $albums[0] ?? null;
            Log::info('Raw albums from MusicBrainz', [
                'mbid' => $request->mbid,
                'raw_titles' => $rawTitles,
                'album_count' => count($rawTitles),
                'sample_album_structure' => $sampleAlbum,
                'available_fields' => $sampleAlbum ? array_keys($sampleAlbum) : []
            ]);

            // Filter for studio albums only
            $filteredAlbums = collect($albums)
                ->filter(function ($album) {
                    $title = strtolower($album['title']);
                    
                    // Primary filter: exclude date/venue patterns (live recordings)
                    // This catches patterns like "1994-07-29: Cat's Cradle, Carrboro, NC, USA"
                    if (preg_match('/^\d{4}-\d{2}-\d{2}:/', $album['title'])) {
                        return false;
                    }
                    
                    // Filter by type: must be "Album" (studio album)
                    if (isset($album['type']) && strtolower($album['type']) !== 'album') {
                        return false;
                    }
                    
                    // Filter by primary-type: must be "Album"
                    if (isset($album['primary-type']) && strtolower($album['primary-type']) !== 'album') {
                        return false;
                    }
                    
                    // Filter out secondary types that indicate non-studio albums
                    if (isset($album['secondary-types']) && is_array($album['secondary-types'])) {
                        $excludeSecondaryTypes = [
                            'compilation', 'live', 'soundtrack', 'remix', 'mixtape',
                            'dj-mix', 'karaoke', 'spokenword', 'audiobook', 'other',
                            'broadcast', 'demo', 'interview', 'session', 'bootleg'
                        ];
                        
                        foreach ($album['secondary-types'] as $secondaryType) {
                            if (in_array(strtolower($secondaryType), $excludeSecondaryTypes)) {
                                return false;
                            }
                        }
                    }
                    
                    
                    // Additional title-based filtering for obvious non-studio albums
                    $excludeWords = [
                        'live', 'compilation', 'b-sides', 'rarities', 'best of',
                        'greatest hits', 'box set', 'boxset', 'unplugged',
                        'interview', 'session', 'bootleg', 'remix', 'collection',
                        'soundtrack', 'ost', 'original soundtrack', 'demo',
                        'acoustic', 'unplugged', 'in concert', 'at the',
                        'ep', 'single', 'singles', 'maxi', '12"', '7"',
                        'vinyl', 'cd', 'cassette', 'tape', 'digital',
                        'deluxe', 'expanded', 'anniversary', 'edition',
                        'remastered', 'remaster', 'reissue', 're-release',
                        'limited', 'special', 'collector', 'boxed',
                        'complete', 'definitive', 'ultimate', 'essential',
                        'classic', 'masterpiece', 'legacy', 'heritage',
                        'anthology', 'retrospective', 'chronicles', 'story',
                        'journey', 'evolution', 'transformation', 'metamorphosis',
                        'reimagined', 'revisited', 'reworked', 'reinterpreted',
                        'cover', 'covers', 'tribute', 'tributes', 'homage',
                        'salute', 'celebration', 'festival', 'concert',
                        'performance', 'show', 'gig', 'tour', 'touring',
                        'live at', 'live from', 'live in', 'live on',
                        'recorded', 'filmed', 'documentary', 'biography',
                        'autobiography', 'memoir', 'diary', 'journal',
                        'archive', 'archives', 'vault', 'vaults', 'unreleased',
                        'outtakes', 'alternate', 'alternative', 'version',
                        'versions', 'variation', 'variations', 'mix', 'mixes',
                        'edit', 'edits', 'cut', 'cuts', 'take', 'takes',
                        'draft', 'drafts', 'sketch', 'sketches', 'demo',
                        'demos', 'rough', 'roughs', 'early', 'late',
                        'preview', 'previews', 'teaser', 'teasers',
                        'promo', 'promotional', 'advance', 'advances',
                        'leak', 'leaked', 'bootleg', 'bootlegs', 'unofficial',
                        'fan', 'fans', 'fan-made', 'fanmade', 'amateur',
                        'home', 'homemade', 'diy', 'indie', 'independent',
                        'underground', 'alternative', 'experimental', 'avant-garde',
                        'avantgarde', 'avant garde', 'progressive', 'art rock',
                        'glam', 'glam rock', 'punk', 'punk rock', 'new wave',
                        'synth', 'synthpop', 'electronic', 'electronica',
                        'ambient', 'atmospheric', 'instrumental', 'vocal',
                        'a cappella', 'acapella', 'karaoke', 'instrumental',
                        'orchestral', 'symphonic', 'chamber', 'classical',
                        'jazz', 'blues', 'folk', 'country', 'reggae',
                        'world', 'ethnic', 'traditional', 'contemporary',
                        'modern', 'post-modern', 'postmodern', 'neo',
                        'retro', 'vintage', 'classic', 'timeless', 'eternal',
                        'immortal', 'legendary', 'iconic', 'seminal',
                        'influential', 'groundbreaking', 'revolutionary',
                        'innovative', 'pioneering', 'trailblazing', 'trendsetting'
                    ];
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

            // Log filtered album titles
            $filteredTitles = $filteredAlbums->pluck('title')->all();
            $excludedAlbums = collect($albums)->diffUsing($filteredAlbums, function($a, $b) {
                return $a['id'] === $b['id'] ? 0 : 1;
            });
            
            Log::info('Filtered albums after exclusion', [
                'mbid' => $request->mbid,
                'filtered_titles' => $filteredTitles,
                'filtered_count' => count($filteredTitles),
                'excluded_count' => $excludedAlbums->count(),
                'excluded_samples' => $excludedAlbums->take(5)->map(function($album) {
                    return [
                        'title' => $album['title'],
                        'type' => $album['type'] ?? 'unknown',
                        'primary_type' => $album['primary-type'] ?? 'unknown',
                        'secondary_types' => $album['secondary-types'] ?? []
                    ];
                })->toArray()
            ]);

            // Return summary instead of individual albums
            $summary = [
                'total_albums' => $filteredAlbums->count(),
                'albums_by_type' => $filteredAlbums->groupBy('type')->map->count(),
                'date_range' => [
                    'earliest' => $filteredAlbums->min('first_release_date'),
                    'latest' => $filteredAlbums->max('first_release_date'),
                ],
                'sample_albums' => $filteredAlbums->take(5)->map(function($album) {
                    return [
                        'title' => $album['title'],
                        'type' => $album['type'],
                        'date' => $album['first_release_date']
                    ];
                })->toArray(),
                'all_albums' => $filteredAlbums->toArray() // Keep full data for import
            ];

            Log::info('Discography summary', [
                'mbid' => $request->mbid,
                'summary' => $summary
            ]);

            return response()->json($summary);
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
                        'access_level' => 'public',
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
                            $existingMetadata = $trackSpan->metadata ?? [];
                            $wasFixed = false;
                            
                            // Add MusicBrainz ID if it doesn't exist
                            if (!isset($existingMetadata['musicbrainz_id'])) {
                                $existingMetadata['musicbrainz_id'] = $track['id'];
                                $wasFixed = true;
                            }
                            
                            $updateData = [
                                'name' => $track['title'],
                                'metadata' => array_merge($existingMetadata, [
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
                            
                            // Prepare track data (tracks are public by default)
                            $trackData = [
                                'name' => $track['title'],
                                'type_id' => 'thing',
                                'state' => $trackState,
                                'access_level' => 'public',
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
        
        // If it's YYYY-MM format, extract the year
        if (preg_match('/^(\d{4})-\d{2}$/', $dateString, $matches)) {
            return (int)$matches[1];
        }
        
        // If it's YYYY-MM-DD format, extract the year
        if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', $dateString, $matches)) {
            return (int)$matches[1];
        }
        
        // For other formats, try to parse with strtotime
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return null;
        }
        
        return (int)date('Y', $timestamp);
    }

    /**
     * Clean up redundant direct artist-track connections
     */
    private function cleanupRedundantArtistTrackConnections(Span $artist, Span $track): void
    {
        // Find any direct artist-track connections (redundant since track-artist relationship comes through album)
        $directArtistTrackConnections = Connection::where(function($query) use ($artist, $track) {
            $query->where('parent_id', $artist->id)
                  ->where('child_id', $track->id)
                  ->where('type_id', 'created');
        })->orWhere(function($query) use ($artist, $track) {
            $query->where('parent_id', $track->id)
                  ->where('child_id', $artist->id)
                  ->where('type_id', 'created');
        })->get();

        foreach ($directArtistTrackConnections as $connection) {
            Log::info('Removing redundant direct artist-track connection', [
                'artist' => $artist->name,
                'track' => $track->name,
                'connection_id' => $connection->id
            ]);
            
            // Delete the connection span if it exists
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            
            // Delete the connection
            $connection->delete();
        }
    }

    private function cleanupAllDirectArtistTrackConnections(Span $artist, Span $track): void
    {
        // Find any direct artist-track connections regardless of type
        $directArtistTrackConnections = Connection::where(function($query) use ($artist, $track) {
            $query->where('parent_id', $artist->id)
                  ->where('child_id', $track->id);
        })->orWhere(function($query) use ($artist, $track) {
            $query->where('parent_id', $track->id)
                  ->where('child_id', $artist->id);
        })->get();

        foreach ($directArtistTrackConnections as $connection) {
            Log::info('Removing direct artist-track connection', [
                'artist' => $artist->name,
                'track' => $track->name,
                'connection_id' => $connection->id,
                'connection_type' => $connection->type_id
            ]);
            
            // Delete the connection span if it exists
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            
            // Delete the connection
            $connection->delete();
        }
    }

    public function importAll(Request $request)
    {
        $request->validate([
            'band_id' => 'required|exists:spans,id',
            'mbid' => 'required|string',
        ]);

        try {
            $band = Span::findOrFail($request->band_id);
            
            Log::info('Starting bulk import for artist', [
                'band_name' => $band->name,
                'mbid' => $request->mbid,
            ]);

            // Get all albums for this artist
            $albums = $this->musicBrainzService->getDiscography($request->mbid);
            
            // Apply the same filtering as showDiscography
            $filteredAlbums = collect($albums)
                ->filter(function ($album) {
                    $title = strtolower($album['title']);
                    
                    // Primary filter: exclude date/venue patterns (live recordings)
                    if (preg_match('/^\d{4}-\d{2}-\d{2}:/', $album['title'])) {
                        return false;
                    }
                    
                    // Filter by type: must be "Album" (studio album)
                    if (isset($album['type']) && strtolower($album['type']) !== 'album') {
                        return false;
                    }
                    
                    // Filter by primary-type: must be "Album"
                    if (isset($album['primary-type']) && strtolower($album['primary-type']) !== 'album') {
                        return false;
                    }
                    
                    // Filter out secondary types that indicate non-studio albums
                    if (isset($album['secondary-types']) && is_array($album['secondary-types'])) {
                        $excludeSecondaryTypes = [
                            'compilation', 'live', 'soundtrack', 'remix', 'mixtape',
                            'dj-mix', 'karaoke', 'spokenword', 'audiobook', 'other',
                            'broadcast', 'demo', 'interview', 'session', 'bootleg'
                        ];
                        
                        foreach ($album['secondary-types'] as $secondaryType) {
                            if (in_array(strtolower($secondaryType), $excludeSecondaryTypes)) {
                                return false;
                            }
                        }
                    }
                    
                    // Additional title-based filtering for obvious non-studio albums
                    $excludeWords = [
                        'live', 'compilation', 'b-sides', 'rarities', 'best of',
                        'greatest hits', 'box set', 'boxset', 'unplugged',
                        'interview', 'session', 'bootleg', 'remix', 'collection',
                        'soundtrack', 'ost', 'original soundtrack', 'demo',
                        'acoustic', 'unplugged', 'in concert', 'at the'
                    ];
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

            // Import all albums using the existing import logic
            $imported = [];
            $totalTracksImported = 0;
            $totalTracksFixed = 0;
            foreach ($filteredAlbums as $album) {
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
                    
                    // Prepare album data (albums are public by default)
                    $albumData = [
                        'name' => $cleanTitle,
                        'type_id' => 'thing',
                        'state' => $albumState,
                        'access_level' => 'public',
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

                // Import tracks for this album
                try {
                    $tracks = $this->musicBrainzService->getTracks($album['id']);
                    $albumTracksImported = 0;
                    $albumTracksFixed = 0;
                    
                    foreach ($tracks as $track) {
                        // Check if track already exists by MusicBrainz ID first
                        $trackSpan = Span::whereJsonContains('metadata->musicbrainz_id', $track['id'])->first();
                        
                        // If not found by MusicBrainz ID, try to find by normalised name that doesn't have MusicBrainz ID yet
                        if (!$trackSpan) {
                            $normalisedTitle = $this->normaliseTrackName($track['title']);
                            $trackSpan = Span::whereRaw("LOWER(REGEXP_REPLACE(LOWER(name), '[^a-z0-9 ]', '', 'g')) = ?", [$normalisedTitle])
                                ->whereJsonContains('metadata->subtype', 'track')
                                ->whereRaw("NOT (metadata->>'musicbrainz_id') IS NOT NULL")
                                ->first();
                        }

                        if ($trackSpan) {
                            // Update existing track
                            $existingMetadata = $trackSpan->metadata ?? [];
                            $wasFixed = false;
                            
                            // Add MusicBrainz ID if it doesn't exist
                            if (!isset($existingMetadata['musicbrainz_id'])) {
                                $existingMetadata['musicbrainz_id'] = $track['id'];
                                $wasFixed = true;
                            }
                            
                            $updateData = [
                                'name' => $track['title'],
                                'metadata' => array_merge($existingMetadata, [
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
                            
                            // Clean up any redundant direct artist-track connections
                            $this->cleanupRedundantArtistTrackConnections($band, $trackSpan);
                            
                            // Also clean up any other direct artist-track connections that might exist
                            // (in case the track was created by other importers)
                            $this->cleanupAllDirectArtistTrackConnections($band, $trackSpan);
                            
                            // Check if album-track connection exists, create if not
                            $albumConnectionExisted = Connection::where('parent_id', $albumSpan->id)
                                ->where('child_id', $trackSpan->id)
                                ->where('type_id', 'contains')
                                ->exists();
                                
                            if (!$albumConnectionExisted) {
                                
                                // Create connection span for the contains connection
                                $hasTrackConnectionDate = !empty($track['first_release_date']);
                                $isTrackConnectionToday = false;
                                if ($hasTrackConnectionDate) {
                                    $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                    $today = strtotime('today');
                                    $isTrackConnectionToday = (date('Y-m-d', $releaseDate) === date('Y-m-d', $today));
                                }
                                $trackConnectionState = ($hasTrackConnectionDate && !$isTrackConnectionToday) ? 'complete' : 'placeholder';
                                
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
                                    if (date('Y-m-d', $releaseDate) !== date('Y-m-d', $today)) {
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

                                // Create connection between album and track
                                Connection::create([
                                    'parent_id' => $albumSpan->id,
                                    'child_id' => $trackSpan->id,
                                    'type_id' => 'contains',
                                    'connection_span_id' => $connectionSpan2->id
                                ]);
                            }
                            
                            // Track if this was a fix (no MusicBrainz ID before or no album connection)
                            if ($wasFixed || !$albumConnectionExisted) {
                                $albumTracksFixed++;
                            }
                            
                            $albumTracksImported++;
                        } else {
                            // Determine state based on whether we have release date and it's not today
                            $hasTrackReleaseDate = !empty($track['first_release_date']);
                            $isTrackToday = false;
                            if ($hasTrackReleaseDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                $isTrackToday = (date('Y-m-d', $releaseDate) === date('Y-m-d', $today));
                            }
                            $trackState = ($hasTrackReleaseDate && !$isTrackToday) ? 'complete' : 'placeholder';
                            
                            // Prepare track data (tracks are public by default)
                            $trackData = [
                                'name' => $track['title'],
                                'type_id' => 'thing',
                                'state' => $trackState,
                                'access_level' => 'public',
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
                                if (date('Y-m-d', $releaseDate) !== date('Y-m-d', $today)) {
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
                            $isTrackConnectionToday = false;
                            if ($hasTrackConnectionDate) {
                                $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                                $today = strtotime('today');
                                $isTrackConnectionToday = (date('Y-m-d', $releaseDate) === date('Y-m-d', $today));
                            }
                            $trackConnectionState = ($hasTrackConnectionDate && !$isTrackConnectionToday) ? 'complete' : 'placeholder';
                            
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
                                if (date('Y-m-d', $releaseDate) !== date('Y-m-d', $today)) {
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
                            $albumTracksImported++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to import tracks for album', [
                        'album_id' => $album['id'],
                        'album_title' => $album['title'],
                        'error' => $e->getMessage()
                    ]);
                    // Continue with next album
                }

                $totalTracksImported += $albumTracksImported;
                $totalTracksFixed += $albumTracksFixed;
                $imported[] = $albumSpan;
            }

            Log::info('Completed bulk import', [
                'band_name' => $band->name,
                'albums_imported' => count($imported),
                'total_albums_found' => $filteredAlbums->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully imported " . count($imported) . " albums with all tracks for {$band->name}",
                'imported_count' => count($imported),
                'imported_tracks' => $totalTracksImported,
                'fixed_tracks' => $totalTracksFixed,
                'total_found' => $filteredAlbums->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('MusicBrainz bulk import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to import albums',
            ], 500);
        }
    }

    /**
     * Normalise a track name for comparison (lowercase, trim, remove punctuation except spaces)
     */
    private function normaliseTrackName(string $name): string
    {
        // Lowercase, trim, remove all non-alphanumeric and non-space chars
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9 ]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name); // collapse multiple spaces
        return $name;
    }

    /**
     * Preview a MusicBrainz release by URL
     */
    public function previewByUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $url = $request->input('url');
            
            // Extract MusicBrainz release ID from URL
            if (preg_match('/musicbrainz\.org\/release\/([a-f0-9-]+)/', $url, $matches)) {
                $releaseId = $matches[1];
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid MusicBrainz release URL. Please provide a valid MusicBrainz release URL.'
                ], 400);
            }

            // Fetch release data from MusicBrainz API
            $response = Http::get("https://musicbrainz.org/ws/2/release/{$releaseId}", [
                'fmt' => 'json',
                'inc' => 'artists+recordings'
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to fetch release data from MusicBrainz'
                ], 500);
            }

            $releaseData = $response->json();
            
            // Parse release date
            $releaseDate = null;
            $startYear = null;
            $startMonth = null;
            $startDay = null;
            
            if (!empty($releaseData['date'])) {
                $releaseDate = $releaseData['date'];
                $startYear = $this->extractYearFromDate($releaseDate);
                
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate)) {
                    $startMonth = date('m', strtotime($releaseDate));
                    $startDay = date('d', strtotime($releaseDate));
                } elseif (preg_match('/^\d{4}-\d{2}$/', $releaseDate)) {
                    $startMonth = date('m', strtotime($releaseDate));
                }
            }

            // Get artist name
            $artistName = null;
            if (!empty($releaseData['artist-credit']) && count($releaseData['artist-credit']) > 0) {
                $artistName = $releaseData['artist-credit'][0]['name'];
            }

            // Get tracks
            $tracks = [];
            if (!empty($releaseData['media']) && count($releaseData['media']) > 0) {
                foreach ($releaseData['media'] as $medium) {
                    if (!empty($medium['tracks'])) {
                        foreach ($medium['tracks'] as $track) {
                            $tracks[] = [
                                'title' => $track['title'] ?? null,
                                'length' => $track['length'] ?? null,
                                'id' => $track['id'] ?? null
                            ];
                        }
                    }
                }
            }

            $preview = [
                'title' => $releaseData['title'] ?? null,
                'artist_name' => $artistName,
                'date' => $releaseDate,
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'start_day' => $startDay,
                'tracks' => $tracks,
                'release_id' => $releaseId
            ];

            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);

        } catch (\Exception $e) {
            Log::error('MusicBrainz preview by URL failed', [
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to preview release: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import a MusicBrainz release by URL
     */
    public function importByUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $url = $request->input('url');
            
            // Extract MusicBrainz release ID from URL
            if (preg_match('/musicbrainz\.org\/release\/([a-f0-9-]+)/', $url, $matches)) {
                $releaseId = $matches[1];
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid MusicBrainz release URL. Please provide a valid MusicBrainz release URL.'
                ], 400);
            }

            // Fetch release data from MusicBrainz API
            $response = Http::get("https://musicbrainz.org/ws/2/release/{$releaseId}", [
                'fmt' => 'json',
                'inc' => 'artists+recordings'
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to fetch release data from MusicBrainz'
                ], 500);
            }

            $releaseData = $response->json();
            
            // Get artist name and find/create artist span
            $artistName = null;
            if (!empty($releaseData['artist-credit']) && count($releaseData['artist-credit']) > 0) {
                $artistName = $releaseData['artist-credit'][0]['name'];
            }

            if (!$artistName) {
                return response()->json([
                    'success' => false,
                    'error' => 'No artist found in release data'
                ], 400);
            }

            // Find or create artist span
            $artistSpan = Span::where('name', $artistName)
                ->where('type_id', 'person')
                ->first();

            if (!$artistSpan) {
                // Create artist span
                $artistSpan = Span::create([
                    'name' => $artistName,
                    'type_id' => 'person',
                    'state' => 'placeholder',
                    'access_level' => 'private',
                    'owner_id' => $request->user()->id,
                    'updater_id' => $request->user()->id,
                ]);
            }

            // Parse release date
            $releaseDate = $releaseData['date'] ?? null;
            $startYear = null;
            $startMonth = null;
            $startDay = null;
            
            if ($releaseDate) {
                $startYear = $this->extractYearFromDate($releaseDate);
                
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate)) {
                    $startMonth = date('m', strtotime($releaseDate));
                    $startDay = date('d', strtotime($releaseDate));
                } elseif (preg_match('/^\d{4}-\d{2}$/', $releaseDate)) {
                    $startMonth = date('m', strtotime($releaseDate));
                }
            }

            // Determine album state
            $albumState = 'placeholder';
            if ($startYear) {
                $albumState = 'complete';
            }

            // Create album span (albums are public by default)
            $albumSpan = Span::create([
                'name' => $releaseData['title'],
                'type_id' => 'thing',
                'state' => $albumState,
                'access_level' => 'public',
                'metadata' => [
                    'musicbrainz_id' => $releaseId,
                    'subtype' => 'album'
                ],
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'start_day' => $startDay,
                'owner_id' => $request->user()->id,
                'updater_id' => $request->user()->id,
            ]);

            // Create connection between artist and album
            $connectionSpan = Span::create([
                'name' => "{$artistSpan->name} created {$albumSpan->name}",
                'type_id' => 'connection',
                'state' => $albumState,
                'access_level' => 'private',
                'metadata' => [
                    'connection_type' => 'created'
                ],
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'start_day' => $startDay,
                'owner_id' => $request->user()->id,
                'updater_id' => $request->user()->id,
            ]);

            Connection::create([
                'parent_id' => $artistSpan->id,
                'child_id' => $albumSpan->id,
                'type_id' => 'created',
                'connection_span_id' => $connectionSpan->id
            ]);

            // Import tracks if available
            $tracksImported = 0;
            if (!empty($releaseData['media']) && count($releaseData['media']) > 0) {
                foreach ($releaseData['media'] as $medium) {
                    if (!empty($medium['tracks'])) {
                        foreach ($medium['tracks'] as $track) {
                            // Create track span (tracks are public by default)
                            $trackSpan = Span::create([
                                'name' => $track['title'],
                                'type_id' => 'thing',
                                'state' => 'placeholder',
                                'access_level' => 'public',
                                'metadata' => [
                                    'musicbrainz_id' => $track['id'],
                                    'subtype' => 'track'
                                ],
                                'owner_id' => $request->user()->id,
                                'updater_id' => $request->user()->id,
                            ]);

                            // Create connection between album and track
                            $trackConnectionSpan = Span::create([
                                'name' => "{$albumSpan->name} contains {$trackSpan->name}",
                                'type_id' => 'connection',
                                'state' => 'placeholder',
                                'access_level' => 'private',
                                'metadata' => [
                                    'connection_type' => 'contains'
                                ],
                                'owner_id' => $request->user()->id,
                                'updater_id' => $request->user()->id,
                            ]);

                            Connection::create([
                                'parent_id' => $albumSpan->id,
                                'child_id' => $trackSpan->id,
                                'type_id' => 'contains',
                                'connection_span_id' => $trackConnectionSpan->id
                            ]);

                            $tracksImported++;
                        }
                    }
                }
            }

            Log::info('MusicBrainz import by URL completed', [
                'release_id' => $releaseId,
                'artist_name' => $artistName,
                'album_title' => $releaseData['title'],
                'tracks_imported' => $tracksImported
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully imported '{$releaseData['title']}' by {$artistName} with {$tracksImported} tracks"
            ]);

        } catch (\Exception $e) {
            Log::error('MusicBrainz import by URL failed', [
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to import release: ' . $e->getMessage()
            ], 500);
        }
    }
} 