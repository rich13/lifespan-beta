<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMusicBrainzJob;
use App\Models\Span;
use App\Models\ImportProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Connection;
use App\Services\MusicBrainzImportService;

class MusicBrainzImportController extends Controller
{
    protected $musicBrainzService;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->musicBrainzService = new MusicBrainzImportService();
    }

    public function index()
    {
        $allArtists = $this->musicBrainzService->catalogueArtists();
        $withMusicBrainz = $this->musicBrainzService->matchedCatalogueArtistIds($allArtists)->count();

        return view('admin.import.musicbrainz.index', [
            'allArtists' => $allArtists,
            'artistCount' => $allArtists->count(),
            'withMusicBrainz' => $withMusicBrainz,
            'readyCount' => $allArtists->count() - $withMusicBrainz,
        ]);
    }

    public function startBackgroundImport(Request $request)
    {
        $request->validate([
            'artist_id' => 'nullable|uuid|exists:spans,id',
        ]);

        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forMusicBrainz($userId);
        if ($existing && $existing->status === 'running') {
            return response()->json([
                'success' => true,
                'message' => 'A MusicBrainz import is already running.',
            ]);
        }

        ImportProgress::where('import_type', ImportMusicBrainzJob::IMPORT_TYPE)
            ->where('user_id', $request->user()->id)
            ->delete();

        ImportMusicBrainzJob::releaseUniquenessFor($userId);
        ImportMusicBrainzJob::dispatch($userId, $request->input('artist_id'));

        return response()->json([
            'success' => true,
            'message' => 'MusicBrainz import started in the background.',
        ]);
    }

    public function cancelBackgroundImport(Request $request)
    {
        $progress = ImportProgress::forMusicBrainz((string) $request->user()->id);
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'MusicBrainz import cancelled. If it was running, it will stop after the current artist.',
        ]);
    }

    public function status(Request $request)
    {
        $progress = ImportProgress::forMusicBrainz((string) $request->user()->id);

        $payload = [
            'success' => true,
            'background_job' => false,
            'is_importing' => false,
        ];

        if ($progress && in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'], true)) {
            $payload['background_job'] = true;
            $payload['job_status'] = $progress->status;
            $payload['is_importing'] = $progress->status === 'running';
            $payload['job_progress'] = $progress->toJobProgressArray();
        }

        return response()->json($payload);
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
            $artists = $this->musicBrainzService->searchArtist($band->name, $band->type_id);

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
            $albums = $this->musicBrainzService->studioAlbums($request->mbid);

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
            $tracks = $this->musicBrainzService->getTracks($request->release_group_id);

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
            $imported = $this->musicBrainzService->importDiscography(
                $band,
                $request->albums,
                (string) $request->user()->id
            );

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
            'mbid' => 'nullable|string',
        ]);

        $band = Span::findOrFail($request->band_id);
        if ($request->filled('mbid')) {
            $this->musicBrainzService->stampMusicBrainzMatch($band, [
                'id' => $request->mbid,
                'name' => $band->name,
            ]);
        }

        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forMusicBrainz($userId);
        if ($existing && $existing->status === 'running') {
            return response()->json([
                'success' => true,
                'message' => 'A MusicBrainz import is already running.',
            ]);
        }

        ImportProgress::where('import_type', ImportMusicBrainzJob::IMPORT_TYPE)
            ->where('user_id', $request->user()->id)
            ->delete();

        ImportMusicBrainzJob::dispatch($userId, $band->id);

        return response()->json([
            'success' => true,
            'message' => "MusicBrainz import for {$band->name} started in the background.",
        ]);
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