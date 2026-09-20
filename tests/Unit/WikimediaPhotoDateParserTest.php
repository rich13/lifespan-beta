<?php

namespace Tests\Unit;

use App\Services\WikimediaPhotoDateParser;
use Tests\TestCase;

class WikimediaPhotoDateParserTest extends TestCase
{
    private WikimediaPhotoDateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WikimediaPhotoDateParser();
    }

    public function test_keeps_full_date_when_title_year_matches(): void
    {
        $resolved = $this->parser->resolve(
            '1998-07-12',
            'File:Thom Yorke 1998.jpg',
            'Wash D.C. 1998'
        );

        $this->assertSame([
            'year' => 1998,
            'month' => 7,
            'day' => 12,
        ], $resolved);
    }

    public function test_prefers_title_year_over_upload_timestamp(): void
    {
        $resolved = $this->parser->resolve(
            '2019-06-29 20:27',
            'File:Thom Yorke 1998.jpg',
            'Wash D.C. 1998'
        );

        $this->assertSame([
            'year' => 1998,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_prefers_description_year_when_title_has_none(): void
    {
        $resolved = $this->parser->resolve(
            '2019-06-29 20:27',
            'File:Thom Yorke.jpg',
            'Wash D.C. 1998'
        );

        $this->assertSame([
            'year' => 1998,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_uses_title_year_when_date_is_missing(): void
    {
        $resolved = $this->parser->resolve(
            '',
            'File:Thom Yorke 1998.jpg',
            ''
        );

        $this->assertSame([
            'year' => 1998,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_keeps_timestamp_when_no_hint_year_is_present(): void
    {
        $resolved = $this->parser->resolve(
            '2019-06-29 20:27',
            'File:Thom Yorke.jpg',
            'Live in Washington D.C.'
        );

        $this->assertSame([
            'year' => 2019,
            'month' => 6,
            'day' => 29,
        ], $resolved);
    }

    public function test_keeps_parsed_year_when_it_already_appears_in_the_title(): void
    {
        $resolved = $this->parser->resolve(
            '1936',
            'Alan Turing (1912-1954) in 1936 at Princeton University',
            ''
        );

        $this->assertSame([
            'year' => 1936,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_keeps_a_date_range_start_when_that_year_is_in_the_title(): void
    {
        $resolved = $this->parser->resolve(
            '1997',
            'BBC logo 1997-2021',
            ''
        );

        $this->assertSame([
            'year' => 1997,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_trusts_a_year_only_commons_date_over_the_filename(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '1881',
            'title' => 'File:Charles Darwin 1880.jpg',
            'description' => 'Charles Darwin in 1881, cropped version.',
            'categories' => ['1881 portrait photographs', 'Portraits of Charles Darwin'],
        ]);

        $this->assertSame([
            'year' => 1881,
            'month' => null,
            'day' => null,
        ], $resolved);
    }

    public function test_uses_taken_on_template_ahead_of_other_sources(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '2019-06-29 20:27',
            'title' => 'File:Concert.jpg',
            'taken_on' => '1998-06-15',
            'uploaded_at' => '2019-06-30T14:20:04Z',
        ]);

        $this->assertSame([
            'year' => 1998,
            'month' => 6,
            'day' => 15,
        ], $resolved);
    }

    public function test_keeps_a_precise_commons_date_when_a_category_only_has_the_year(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '2018-01-31',
            'title' => 'File:Super moon over City of London from Tate Modern 2018-01-31 4.jpg',
            'categories' => ['2018 photographs'],
            'uploaded_at' => '2018-02-04T12:00:00Z',
        ]);

        $this->assertSame([
            'year' => 2018,
            'month' => 1,
            'day' => 31,
        ], $resolved);
    }

    public function test_keeps_a_same_day_taken_date_when_the_title_year_agrees(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '2018-07-07',
            'title' => 'File:Radiohead at United Center Chicago 6 7 2018.jpg',
            'categories' => ['2018 concerts'],
            'uploaded_at' => '2018-07-07T23:00:00Z',
        ]);

        $this->assertSame([
            'year' => 2018,
            'month' => 7,
            'day' => 7,
        ], $resolved);
    }

    public function test_keeps_a_timed_taken_date_when_the_title_year_agrees(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '2018-01-31 21:04',
            'title' => 'File:Super moon over City of London from Tate Modern 2018-01-31 4.jpg',
            'categories' => ['2018 photographs'],
            'uploaded_at' => '2018-02-01T08:00:00Z',
        ]);

        $this->assertSame([
            'year' => 2018,
            'month' => 1,
            'day' => 31,
        ], $resolved);
    }

    public function test_treats_a_calendar_date_near_the_upload_as_untrusted(): void
    {
        $resolved = $this->parser->resolveFromSources([
            'date' => '2019-11-20',
            'title' => 'File:Dan Ackroyd 1996.jpg',
            'uploaded_at' => '2019-11-20T12:00:00Z',
        ]);

        $this->assertSame([
            'year' => 1996,
            'month' => null,
            'day' => null,
        ], $resolved);
    }
}
