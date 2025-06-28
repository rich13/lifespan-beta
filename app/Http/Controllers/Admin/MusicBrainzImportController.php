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

class MusicBrainzImportController extends Controller
{
    protected $musicBrainzApiUrl = 'https://musicbrainz.org/ws/2';
    protected $userAgent = 'LifespanBeta/1.0 (rich@example.com)';
    protected $spanController;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->spanController = new SpanController();
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

        return view('admin.import.musicbrainz.index', compact('allArtists'));
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
                'url' => "{$this->musicBrainzApiUrl}/artist",
                'params' => [
                    'query' => $band->name,
                    'fmt' => 'json',
                    'limit' => 10,
                ]
            ]);

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
            ])->get("{$this->musicBrainzApiUrl}/artist", [
                'query' => $band->name,
                'fmt' => 'json',
                'limit' => 10,
            ]);

            Log::info('MusicBrainz artist search response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('MusicBrainz API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return response()->json([
                    'error' => 'Failed to search MusicBrainz',
                ], 500);
            }

            $data = $response->json();
            
            Log::info('Parsed artist search results', [
                'has_artists' => isset($data['artists']),
                'artists_count' => count($data['artists'] ?? []),
                'first_artist' => $data['artists'][0] ?? null,
            ]);

            return response()->json([
                'artists' => collect($data['artists'] ?? [])->map(function ($artist) {
                    return [
                        'id' => $artist['id'],
                        'name' => $artist['name'],
                        'disambiguation' => $artist['disambiguation'] ?? null,
                        'type' => $artist['type'] ?? null,
                    ];
                }),
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
                'url' => "{$this->musicBrainzApiUrl}/release-group",
                'params' => [
                    'artist' => $request->mbid,
                    'fmt' => 'json',
                    'limit' => 100,
                    'type' => 'album',
                ]
            ]);

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
            ])->get("{$this->musicBrainzApiUrl}/release-group", [
                'artist' => $request->mbid,
                'fmt' => 'json',
                'limit' => 100,
                'type' => 'album',
            ]);

            Log::info('MusicBrainz API response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('MusicBrainz API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return response()->json([
                    'error' => 'Failed to fetch discography',
                ], 500);
            }

            $data = $response->json();
            
            // Log the raw data structure
            Log::info('Parsed MusicBrainz data', [
                'has_release_groups' => isset($data['release-groups']),
                'release_groups_count' => count($data['release-groups'] ?? []),
                'first_release_group' => $data['release-groups'][0] ?? null,
            ]);

            // Log a table of all release groups with their attributes
            $releaseGroupsTable = collect($data['release-groups'] ?? [])->map(function ($album) {
                return [
                    'title' => $album['title'],
                    'primary_type' => $album['primary-type'] ?? 'N/A',
                    'secondary_types' => collect($album['secondary-types'] ?? [])->pluck('name')->join(', ') ?: 'N/A',
                    'first_release_date' => $album['first-release-date'] ?? 'N/A',
                    'disambiguation' => $album['disambiguation'] ?? 'N/A',
                ];
            })->toArray();

            Log::info('Release Groups Table', [
                'table' => $releaseGroupsTable
            ]);

            $albums = collect($data['release-groups'] ?? [])
                ->filter(function ($album) {
                    // Must have a primary type of "Album"
                    return ($album['primary-type'] ?? '') === 'Album';
                })
                ->filter(function ($album) {
                    // Must have a release date
                    return !empty($album['first-release-date']);
                })
                ->filter(function ($album) {
                    // Must not have any secondary types
                    return empty($album['secondary-types']);
                })
                ->filter(function ($album) {
                    // Must not have any disambiguation
                    return empty($album['disambiguation']);
                })
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
                        'first_release_date' => $album['first-release-date'],
                        'type' => $album['primary-type'],
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
            // First, get the releases for this release group
            Log::info('Fetching releases from MusicBrainz', [
                'release_group_id' => $request->release_group_id,
                'url' => "{$this->musicBrainzApiUrl}/release",
                'params' => [
                    'release-group' => $request->release_group_id,
                    'fmt' => 'json',
                    'limit' => 100,
                    'inc' => 'recordings+media+artist-credits+isrcs',
                ]
            ]);

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
            ])->get("{$this->musicBrainzApiUrl}/release", [
                'release-group' => $request->release_group_id,
                'fmt' => 'json',
                'limit' => 100,
                'inc' => 'recordings+media+artist-credits+isrcs',
            ]);

            if (!$response->successful()) {
                Log::error('MusicBrainz API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return response()->json([
                    'error' => 'Failed to fetch tracks',
                ], 500);
            }

            $data = $response->json();
            
            // Get the first release (usually the original release)
            $release = collect($data['releases'] ?? [])->first();
            
            if (!$release) {
                return response()->json([
                    'error' => 'No releases found for this album',
                ], 404);
            }

            // Get the first medium (usually the first disc)
            $medium = collect($release['media'] ?? [])->first();
            
            if (!$medium) {
                return response()->json([
                    'error' => 'No media found for this release',
                ], 404);
            }

            // Log the medium structure to help debug
            Log::info('Medium structure:', [
                'medium' => $medium,
                'first_track' => $medium['tracks'][0] ?? null,
            ]);

            // Now get the tracks from the medium
            $tracks = collect($medium['tracks'] ?? [])
                ->map(function ($track) use ($release) {
                    $recording = $track['recording'] ?? null;
                    if (!$recording) {
                        Log::warning('Track missing recording data:', ['track' => $track]);
                        return null;
                    }

                    // Log the recording structure to help debug
                    Log::info('Recording structure:', [
                        'recording' => $recording,
                    ]);

                    return [
                        'id' => $recording['id'],
                        'title' => $recording['title'],
                        'length' => $recording['length'] ?? null,
                        'isrc' => $recording['isrcs'][0] ?? null,
                        'artist_credits' => collect($recording['artist-credit'] ?? [])
                            ->map(function ($credit) {
                                return $credit['name'] . ($credit['joinphrase'] ?? '');
                            })
                            ->join(''),
                        'first_release_date' => $release['date'] ?? null,
                        'position' => $track['position'] ?? null,
                        'number' => $track['number'] ?? null,
                    ];
                })
                ->filter() // Remove any null entries
                ->sortBy('position') // Sort by track position instead of title
                ->values();

            Log::info('Fetched tracks', [
                'count' => $tracks->count(),
                'first_track' => $tracks->first(),
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
                    $albumSpan->update([
                        'name' => $cleanTitle,
                        'metadata' => array_merge($albumSpan->metadata ?? [], [
                            'type' => $album['type'] ?? null,
                            'disambiguation' => $album['disambiguation'] ?? null,
                            'subtype' => 'album'
                        ]),
                        'updater_id' => $request->user()->id,
                        'start_year' => $album['first_release_date'] ? date('Y', strtotime($album['first_release_date'])) : null,
                        'start_month' => $album['first_release_date'] ? date('m', strtotime($album['first_release_date'])) : null,
                        'start_day' => $album['first_release_date'] ? date('d', strtotime($album['first_release_date'])) : null,
                    ]);
                } else {
                    // Create new album span
                    $albumSpan = Span::create([
                        'name' => $cleanTitle,
                        'type_id' => 'thing',
                        'state' => 'complete',
                        'access_level' => 'private',
                        'metadata' => [
                            'musicbrainz_id' => $album['id'],
                            'type' => $album['type'] ?? null,
                            'disambiguation' => $album['disambiguation'] ?? null,
                            'subtype' => 'album'
                        ],
                        'owner_id' => $request->user()->id,
                        'updater_id' => $request->user()->id,
                        'start_year' => $album['first_release_date'] ? date('Y', strtotime($album['first_release_date'])) : null,
                        'start_month' => $album['first_release_date'] ? date('m', strtotime($album['first_release_date'])) : null,
                        'start_day' => $album['first_release_date'] ? date('d', strtotime($album['first_release_date'])) : null,
                    ]);

                    // Create connection span for the created connection
                    $connectionSpan1 = Span::create([
                        'name' => "{$band->name} created {$albumSpan->name}",
                        'type_id' => 'connection',
                        'state' => 'complete',
                        'access_level' => 'private',
                        'metadata' => [
                            'connection_type' => 'created'
                        ],
                        'owner_id' => $request->user()->id,
                        'updater_id' => $request->user()->id,
                        'start_year' => $album['first_release_date'] ? date('Y', strtotime($album['first_release_date'])) : null,
                        'start_month' => $album['first_release_date'] ? date('m', strtotime($album['first_release_date'])) : null,
                        'start_day' => $album['first_release_date'] ? date('d', strtotime($album['first_release_date'])) : null,
                    ]);

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
                            $trackSpan->update([
                                'name' => $track['title'],
                                'metadata' => array_merge($trackSpan->metadata ?? [], [
                                    'isrc' => $track['isrc'],
                                    'length' => $track['length'],
                                    'artist_credits' => $track['artist_credits'],
                                    'subtype' => 'track'
                                ]),
                                'updater_id' => $request->user()->id,
                                'start_year' => $track['first_release_date'] ? date('Y', strtotime($track['first_release_date'])) : null,
                                'start_month' => $track['first_release_date'] ? date('m', strtotime($track['first_release_date'])) : null,
                                'start_day' => $track['first_release_date'] ? date('d', strtotime($track['first_release_date'])) : null,
                            ]);
                        } else {
                            // Create new track span
                            $trackSpan = Span::create([
                                'name' => $track['title'],
                                'type_id' => 'thing',
                                'state' => 'complete',
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
                                'start_year' => $track['first_release_date'] ? date('Y', strtotime($track['first_release_date'])) : null,
                                'start_month' => $track['first_release_date'] ? date('m', strtotime($track['first_release_date'])) : null,
                                'start_day' => $track['first_release_date'] ? date('d', strtotime($track['first_release_date'])) : null,
                            ]);

                            // Create connection span for the contains connection
                            $connectionSpan2 = Span::create([
                                'name' => "{$albumSpan->name} contains {$trackSpan->name}",
                                'type_id' => 'connection',
                                'state' => 'complete',
                                'access_level' => 'private',
                                'metadata' => [
                                    'connection_type' => 'contains'
                                ],
                                'owner_id' => $request->user()->id,
                                'updater_id' => $request->user()->id,
                                'start_year' => $track['first_release_date'] ? date('Y', strtotime($track['first_release_date'])) : null,
                                'start_month' => $track['first_release_date'] ? date('m', strtotime($track['first_release_date'])) : null,
                                'start_day' => $track['first_release_date'] ? date('d', strtotime($track['first_release_date'])) : null,
                            ]);

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
} 