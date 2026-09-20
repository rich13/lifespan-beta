<?php

namespace Tests\Feature;

use App\Jobs\FetchAlbumCoverArtJob;
use App\Models\Span;
use App\Services\MusicBrainzCoverArtService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CoverArtDeferredLoadTest extends TestCase
{
    private const MBID = 'bca9280e-28b4-327f-8fe0-fd918579e486';

    private const COVER_PAYLOAD = [
        'images' => [
            [
                'front' => true,
                'id' => 14451051246,
                'image' => 'https://coverartarchive.org/release/15ef2b50-902d-416f-9e1f-5ad9f602dbad/14451051246.jpg',
            ],
        ],
    ];

    private function makeAlbum(array $overrides = []): Span
    {
        $user = $this->createUserWithoutPersonalSpan();

        return Span::create(array_merge([
            'name' => 'Heroes',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1977,
            'metadata' => [
                'subtype' => 'album',
                'musicbrainz_id' => self::MBID,
            ],
        ], $overrides));
    }

    public function test_album_accessors_read_stored_metadata_only(): void
    {
        Http::fake();

        $album = $this->makeAlbum();
        $this->assertFalse($album->has_cover_art);
        $this->assertNull($album->cover_art_small_url);
        $this->assertTrue($album->needsCoverArtFetch());

        Http::assertNothingSent();

        $album->setMeta('cover_art', [
            'small' => 'https://coverartarchive.org/release/abc/1-250.jpg',
            'medium' => 'https://coverartarchive.org/release/abc/1-500.jpg',
            'large' => 'https://coverartarchive.org/release/abc/1-1200.jpg',
            'missing' => false,
        ]);
        $album->saveQuietly();
        $album->refresh();

        $this->assertTrue($album->has_cover_art);
        $this->assertSame('https://coverartarchive.org/release/abc/1-250.jpg', $album->cover_art_small_url);
        $this->assertFalse($album->needsCoverArtFetch());
        Http::assertNothingSent();
    }

    public function test_album_span_page_does_not_call_cover_art_archive(): void
    {
        Http::fake();

        $album = $this->makeAlbum();
        $response = $this->get(route('spans.show', ['subject' => $album->slug]));

        $response->assertOk();
        $response->assertSee('js-cover-art', false);
        $response->assertSee('data-cover-art-span="'.$album->id.'"', false);
        $response->assertSee('spinner-border', false);
        $response->assertSee('Loading cover art', false);
        Http::assertNothingSent();
    }

    public function test_cover_art_api_dispatches_job_when_cover_is_missing(): void
    {
        Queue::fake();
        Http::fake();

        $album = $this->makeAlbum();

        $response = $this->getJson(route('api.cover-art', [
            'span_ids' => [$album->id],
        ]));

        $response->assertOk();
        $response->assertJsonPath('pending.0', $album->id);
        $this->assertArrayNotHasKey($album->id, $response->json('covers'));
        Queue::assertPushed(FetchAlbumCoverArtJob::class, function (FetchAlbumCoverArtJob $job) use ($album) {
            return $job->spanId === $album->id;
        });
        Http::assertNothingSent();
    }

    public function test_cover_art_api_hides_private_albums_from_guests(): void
    {
        Queue::fake();
        $album = $this->makeAlbum();
        $album->access_level = 'private';
        $album->saveQuietly();

        $response = $this->getJson(route('api.cover-art', [
            'span_ids' => [$album->id],
        ]));

        $response->assertOk();
        $response->assertJsonPath('pending', []);
        $response->assertJsonPath('covers', []);
        Queue::assertNothingPushed();
    }

    public function test_job_persists_cover_urls_on_the_album(): void
    {
        Http::fake([
            'https://coverartarchive.org/release-group/'.self::MBID => Http::response(self::COVER_PAYLOAD, 200),
        ]);

        $album = $this->makeAlbum();
        (new FetchAlbumCoverArtJob($album->id))->handle();

        $album->refresh();
        $this->assertTrue($album->has_cover_art);
        $this->assertStringContainsString('-250.jpg', $album->cover_art_small_url);
        $this->assertFalse($album->needsCoverArtFetch());
    }

    public function test_cover_art_api_returns_stored_urls_without_a_job(): void
    {
        Queue::fake();
        $album = $this->makeAlbum();
        MusicBrainzCoverArtService::getInstance()->persistCoverArtOnSpan($album, [
            'small' => 'https://coverartarchive.org/release/abc/1-250.jpg',
            'medium' => 'https://coverartarchive.org/release/abc/1-500.jpg',
            'large' => 'https://coverartarchive.org/release/abc/1-1200.jpg',
        ]);

        $response = $this->getJson(route('api.cover-art', [
            'span_ids' => [$album->id],
        ]));

        $response->assertOk();
        $response->assertJsonPath('pending', []);
        $response->assertJsonPath('covers.'.$album->id.'.small', 'https://coverartarchive.org/release/abc/1-250.jpg');
        Queue::assertNothingPushed();
    }
}
