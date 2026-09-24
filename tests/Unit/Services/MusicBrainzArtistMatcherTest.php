<?php

namespace Tests\Unit\Services;

use App\Services\MusicBrainzArtistMatcher;
use Tests\TestCase;

class MusicBrainzArtistMatcherTest extends TestCase
{
    private MusicBrainzArtistMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new MusicBrainzArtistMatcher();
    }

    public function test_it_builds_a_typed_lucene_query_for_bands(): void
    {
        $query = $this->matcher->buildSearchQuery('The Beatles', 'band');

        $this->assertSame('artist:"The Beatles" AND (type:group OR type:orchestra OR type:choir)', $query);
    }

    public function test_it_rejects_tribute_and_bootleg_artists(): void
    {
        $this->assertTrue($this->matcher->isRejected('The Beatles Tribute Band', '', 'Group'));
        $this->assertTrue($this->matcher->isRejected('The Beatles', 'bootleg recordings', 'Group'));
        $this->assertFalse($this->matcher->isRejected('The Beatles', 'UK rock group', 'Group'));
    }

    public function test_it_auto_accepts_a_clear_official_artist(): void
    {
        $decision = $this->matcher->pickUnambiguous([
            [
                'id' => 'mb-beatles',
                'name' => 'The Beatles',
                'type' => 'Group',
                'score' => '100',
                'disambiguation' => null,
            ],
            [
                'id' => 'mb-tribute',
                'name' => 'The Beatles Tribute Band',
                'type' => 'Group',
                'score' => '62',
                'disambiguation' => 'tribute act',
            ],
        ], 'The Beatles', 'band');

        $this->assertSame('matched', $decision['status']);
        $this->assertSame('mb-beatles', $decision['artist']['id']);
    }

    public function test_it_skips_when_two_people_score_similarly(): void
    {
        $decision = $this->matcher->pickUnambiguous([
            [
                'id' => 'mb-one',
                'name' => 'John Smith',
                'type' => 'Person',
                'score' => '98',
                'disambiguation' => 'British singer',
            ],
            [
                'id' => 'mb-two',
                'name' => 'John Smith',
                'type' => 'Person',
                'score' => '96',
                'disambiguation' => 'American pianist',
            ],
        ], 'John Smith', 'person');

        $this->assertSame('ambiguous', $decision['status']);
        $this->assertArrayNotHasKey('artist', $decision);
    }

    public function test_it_returns_no_match_when_only_rejected_hits_remain(): void
    {
        $decision = $this->matcher->pickUnambiguous([
            [
                'id' => 'mb-karaoke',
                'name' => 'David Bowie Karaoke',
                'type' => 'Group',
                'score' => '90',
                'disambiguation' => 'karaoke',
            ],
        ], 'David Bowie', 'person');

        $this->assertSame('no_match', $decision['status']);
    }
}
