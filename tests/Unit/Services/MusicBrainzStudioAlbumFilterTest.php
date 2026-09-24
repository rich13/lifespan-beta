<?php

namespace Tests\Unit\Services;

use App\Services\MusicBrainzStudioAlbumFilter;
use Tests\TestCase;

class MusicBrainzStudioAlbumFilterTest extends TestCase
{
    private MusicBrainzStudioAlbumFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new MusicBrainzStudioAlbumFilter();
    }

    public function test_it_keeps_official_studio_albums(): void
    {
        $this->assertTrue($this->filter->isStudioAlbum([
            'title' => 'OK Computer',
            'type' => 'Album',
            'primary-type' => 'Album',
            'secondary-types' => [],
        ]));
    }

    public function test_it_drops_live_compilations_and_bootlegs(): void
    {
        $this->assertFalse($this->filter->isStudioAlbum([
            'title' => 'Greatest Hits',
            'primary-type' => 'Album',
            'secondary-types' => ['Compilation'],
        ]));
        $this->assertFalse($this->filter->isStudioAlbum([
            'title' => 'Live at the BBC',
            'primary-type' => 'Album',
            'secondary-types' => ['Live'],
        ]));
        $this->assertFalse($this->filter->isStudioAlbum([
            'title' => 'Sgt. Pepper bootleg',
            'primary-type' => 'Album',
            'secondary-types' => [],
        ]));
        $this->assertFalse($this->filter->isStudioAlbum([
            'title' => '1994-07-29: Cat\'s Cradle, Carrboro, NC, USA',
            'primary-type' => 'Album',
            'secondary-types' => [],
        ]));
    }

    public function test_it_does_not_exclude_studio_albums_with_genre_words_in_the_title(): void
    {
        $this->assertTrue($this->filter->isStudioAlbum([
            'title' => 'Kind of Blue',
            'primary-type' => 'Album',
            'secondary-types' => [],
        ]));
        $this->assertTrue($this->filter->isStudioAlbum([
            'title' => 'The Jazz Age',
            'primary-type' => 'Album',
            'secondary-types' => [],
        ]));
    }
}
