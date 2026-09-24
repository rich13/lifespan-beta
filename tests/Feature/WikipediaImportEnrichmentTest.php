<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaImportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikipediaImportEnrichmentTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_import_stores_wikidata_facts_and_wikipedia_lead_image(): void
    {
        $this->fakeAdaLovelace();

        $person = $this->publicFigure('Ada Lovelace', [
            'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
        ]);

        $result = $this->processWithoutModelEvents($person);

        $this->assertTrue($result['success']);
        $person->refresh();
        $this->assertSame('Augusta Ada King', $person->metadata['birth_name']);
        $this->assertSame('mathematician, writer', $person->metadata['occupation']);
        $this->assertSame('United Kingdom', $person->metadata['nationality']);
        $this->assertSame('female', $person->metadata['gender']);
        $this->assertSame('Q7259', $person->metadata['wikidata_id']);
        $this->assertTrue($this->sourcesContain($person, 'https://example.com/ada'));

        $photo = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->where('metadata->original_url', 'https://upload.wikimedia.org/wikipedia/commons/ada.png')
            ->first();
        $this->assertNotNull($photo);
        $this->assertSame(
            'https://upload.wikimedia.org/wikipedia/commons/thumb/ada.png/330px-ada.png',
            $photo->metadata['thumbnail_url']
        );
        $this->assertNotEmpty($photo->short_id);
        $this->assertTrue(Connection::where('type_id', 'features')
            ->where('parent_id', $photo->id)
            ->where('child_id', $person->id)
            ->exists());
        $this->assertTrue($result['data']['facts']['image_added']);
    }

    public function test_import_does_not_replace_existing_facts_or_add_a_second_photo(): void
    {
        $this->fakeAdaLovelace();

        $person = $this->publicFigure('Ada Lovelace', [
            'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
        ], [
            'occupation' => 'poet',
            'wikidata_id' => 'Q111',
            'gender' => 'male',
        ]);

        $existingPhoto = Span::create([
            'name' => 'Existing photo',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'photo',
                'original_url' => 'https://example.com/existing.jpg',
                'thumbnail_url' => 'https://example.com/existing.jpg',
            ],
        ]);
        $connectionSpan = Span::create([
            'name' => 'Existing photo features Ada Lovelace',
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['timeless' => true, 'connection_type' => 'features'],
        ]);
        Connection::create([
            'parent_id' => $existingPhoto->id,
            'child_id' => $person->id,
            'type_id' => 'features',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $result = $this->processWithoutModelEvents($person);

        $person->refresh();
        $this->assertSame('poet', $person->metadata['occupation']);
        $this->assertSame('Q111', $person->metadata['wikidata_id']);
        $this->assertSame('male', $person->metadata['gender']);
        $this->assertSame('Augusta Ada King', $person->metadata['birth_name']);
        $this->assertFalse($result['data']['facts']['image_added']);
        $this->assertSame(1, Connection::where('type_id', 'features')->where('child_id', $person->id)->count());
    }

    public function test_birth_name_matching_the_span_name_is_not_stored(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'extract' => 'A mathematician.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Ada_Lovelace']],
                    'wikibase_item' => 'Q7259',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org')) {
                $ids = $request->data()['ids'] ?? '';
                if ($ids === 'Q7259') {
                    return Http::response([
                        'entities' => [
                            'Q7259' => [
                                'id' => 'Q7259',
                                'claims' => [
                                    'P1477' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'monolingualtext',
                                                'value' => ['text' => 'Ada Lovelace', 'language' => 'en'],
                                            ],
                                        ],
                                    ]],
                                ],
                            ],
                        ],
                    ], 200);
                }
            }

            return Http::response([], 404);
        });

        $person = $this->publicFigure('Ada Lovelace', [
            'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
        ]);

        $this->processWithoutModelEvents($person);

        $person->refresh();
        $this->assertArrayNotHasKey('birth_name', $person->metadata);
    }

    public function test_conflicting_gender_values_are_not_stored(): void
    {
        $this->fakePersonWithGenderClaims([
            $this->genderClaim('Q6581097'),
            $this->genderClaim('Q6581072'),
        ]);

        $person = $this->publicFigure('Ada Lovelace', [
            'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
        ]);

        $this->processWithoutModelEvents($person);

        $person->refresh();
        $this->assertArrayNotHasKey('gender', $person->metadata);
    }

    public function test_a_non_binary_gender_label_is_stored_as_other(): void
    {
        $this->fakePersonWithGenderClaims([
            $this->genderClaim('Q999888'),
        ], [
            'Q999888' => ['labels' => ['en' => ['value' => 'non-binary gender']]],
        ]);

        $person = $this->publicFigure('Ada Lovelace', [
            'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
        ]);

        $this->processWithoutModelEvents($person);

        $person->refresh();
        $this->assertSame('other', $person->metadata['gender']);
    }

    public function test_a_matching_musical_group_is_corrected_from_person_to_band(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'type' => 'standard',
                    'extract' => 'ABBA is a Swedish pop group.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/ABBA']],
                    'wikibase_item' => 'Q18233',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org')) {
                $ids = $request->data()['ids'] ?? '';
                if ($ids === 'Q18233') {
                    return Http::response([
                        'entities' => [
                            'Q18233' => [
                                'id' => 'Q18233',
                                'labels' => ['en' => ['value' => 'ABBA']],
                                'claims' => [
                                    'P31' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'wikibase-entityid',
                                                'value' => ['id' => 'Q215380'],
                                            ],
                                        ],
                                    ]],
                                    'P106' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'wikibase-entityid',
                                                'value' => ['id' => 'Q639669'],
                                            ],
                                        ],
                                    ]],
                                    'P571' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'time',
                                                'value' => [
                                                    'time' => '+1972-01-01T00:00:00Z',
                                                    'precision' => 11,
                                                ],
                                            ],
                                        ],
                                    ]],
                                    'P856' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'string',
                                                'value' => 'https://abba.com',
                                            ],
                                        ],
                                    ]],
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response([
                    'entities' => [
                        'Q639669' => ['labels' => ['en' => ['value' => 'musical group']]],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $band = $this->publicFigure('ABBA', [
            'url' => 'https://en.wikipedia.org/wiki/ABBA',
        ]);

        $result = $this->processWithoutModelEvents($band);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['type_corrected']);
        $band->refresh();
        $this->assertSame('band', $band->type_id);
        $this->assertSame('ABBA is a Swedish pop group.', $band->description);
        $this->assertSame('Q18233', $band->metadata['wikidata_id']);
        $this->assertSame('wikipedia', $band->metadata['type_corrected_by']);
        $this->assertSame('person', $band->metadata['type_corrected_from']);
        $this->assertArrayNotHasKey('subtype', $band->metadata);
        $this->assertArrayNotHasKey('occupation', $band->metadata);
        $this->assertSame(1972, $band->start_year);
        $this->assertTrue($this->sourcesContain($band, 'https://abba.com'));
    }

    public function test_a_matching_book_is_corrected_from_person_to_thing(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'type' => 'standard',
                    'extract' => 'The Arabian Nights is a collection of Middle Eastern folktales.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/The_Arabian_Nights']],
                    'wikibase_item' => 'Q8258',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org') && ($request->data()['ids'] ?? '') === 'Q8258') {
                return Http::response([
                    'entities' => [
                        'Q8258' => [
                            'id' => 'Q8258',
                            'labels' => ['en' => ['value' => 'The Arabian Nights']],
                            'claims' => [
                                'P31' => [[
                                    'rank' => 'normal',
                                    'mainsnak' => [
                                        'snaktype' => 'value',
                                        'datavalue' => [
                                            'type' => 'wikibase-entityid',
                                            'value' => ['id' => 'Q571'],
                                        ],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $book = $this->publicFigure('The Arabian Nights', [
            'url' => 'https://en.wikipedia.org/wiki/The_Arabian_Nights',
        ]);

        $result = app(WikipediaImportService::class)->processSpan($book);

        $this->assertTrue($result['success']);
        $book->refresh();
        $this->assertSame('thing', $book->type_id);
        $this->assertSame('book', $book->metadata['subtype']);
        $this->assertSame('Q8258', $book->metadata['wikidata_id']);
    }

    public function test_a_book_article_with_a_different_name_is_not_applied_to_the_person(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'type' => 'standard',
                    'extract' => 'One Thousand and One Nights is a collection of folktales.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/One_Thousand_and_One_Nights']],
                    'wikibase_item' => 'Q8258',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org') && ($request->data()['ids'] ?? '') === 'Q8258') {
                return Http::response([
                    'entities' => [
                        'Q8258' => [
                            'id' => 'Q8258',
                            'labels' => ['en' => ['value' => 'One Thousand and One Nights']],
                            'claims' => [
                                'P31' => [[
                                    'rank' => 'normal',
                                    'mainsnak' => [
                                        'snaktype' => 'value',
                                        'datavalue' => [
                                            'type' => 'wikibase-entityid',
                                            'value' => ['id' => 'Q571'],
                                        ],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $person = $this->publicFigure('1001 Nights', [
            'url' => 'https://en.wikipedia.org/wiki/One_Thousand_and_One_Nights',
        ]);

        $result = $this->processWithoutModelEvents($person);

        $this->assertFalse($result['success']);
        $person->refresh();
        $this->assertSame('person', $person->type_id);
        $this->assertSame('public_figure', $person->metadata['subtype']);
        $this->assertNull($person->description);
    }

    private function fakeAdaLovelace(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'extract' => 'Ada Lovelace was a mathematician.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Ada_Lovelace']],
                    'wikibase_item' => 'Q7259',
                    'thumbnail' => [
                        'source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/ada.png/330px-ada.png?utm_source=en.wikipedia.org',
                    ],
                    'originalimage' => [
                        'source' => 'https://upload.wikimedia.org/wikipedia/commons/ada.png?utm_content=thumbnail_unscaled',
                    ],
                ], 200);
            }

            if (str_contains($url, 'wikidata.org')) {
                $ids = $request->data()['ids'] ?? '';
                if ($ids === 'Q7259') {
                    return Http::response([
                        'entities' => [
                            'Q7259' => [
                                'id' => 'Q7259',
                                'labels' => ['en' => ['value' => 'Ada Lovelace']],
                                'claims' => [
                                    'P31' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'wikibase-entityid',
                                                'value' => ['id' => 'Q5'],
                                            ],
                                        ],
                                    ]],
                                    'P21' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'wikibase-entityid',
                                                'value' => ['id' => 'Q6581072'],
                                            ],
                                        ],
                                    ]],
                                    'P1477' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'monolingualtext',
                                                'value' => ['text' => 'Augusta Ada King', 'language' => 'en'],
                                            ],
                                        ],
                                    ]],
                                    'P106' => [
                                        [
                                            'rank' => 'normal',
                                            'mainsnak' => [
                                                'snaktype' => 'value',
                                                'datavalue' => [
                                                    'type' => 'wikibase-entityid',
                                                    'value' => ['id' => 'Q170790'],
                                                ],
                                            ],
                                        ],
                                        [
                                            'rank' => 'normal',
                                            'mainsnak' => [
                                                'snaktype' => 'value',
                                                'datavalue' => [
                                                    'type' => 'wikibase-entityid',
                                                    'value' => ['id' => 'Q36180'],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'P27' => [
                                        [
                                            'rank' => 'preferred',
                                            'mainsnak' => [
                                                'snaktype' => 'value',
                                                'datavalue' => [
                                                    'type' => 'wikibase-entityid',
                                                    'value' => ['id' => 'Q145'],
                                                ],
                                            ],
                                        ],
                                        [
                                            'rank' => 'deprecated',
                                            'mainsnak' => [
                                                'snaktype' => 'value',
                                                'datavalue' => [
                                                    'type' => 'wikibase-entityid',
                                                    'value' => ['id' => 'Q174193'],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'P856' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'string',
                                                'value' => 'https://example.com/ada',
                                            ],
                                        ],
                                    ]],
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response([
                    'entities' => [
                        'Q170790' => ['labels' => ['en' => ['value' => 'mathematician']]],
                        'Q36180' => ['labels' => ['en' => ['value' => 'writer']]],
                        'Q145' => ['labels' => ['en' => ['value' => 'United Kingdom']]],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $genderClaims
     * @param  array<string, mixed>  $labelEntities
     */
    private function fakePersonWithGenderClaims(array $genderClaims, array $labelEntities = []): void
    {
        Http::fake(function ($request) use ($genderClaims, $labelEntities) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'extract' => 'A mathematician.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Ada_Lovelace']],
                    'wikibase_item' => 'Q7259',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org')) {
                $ids = $request->data()['ids'] ?? '';
                if ($ids === 'Q7259') {
                    return Http::response([
                        'entities' => [
                            'Q7259' => [
                                'id' => 'Q7259',
                                'labels' => ['en' => ['value' => 'Ada Lovelace']],
                                'claims' => [
                                    'P31' => [[
                                        'rank' => 'normal',
                                        'mainsnak' => [
                                            'snaktype' => 'value',
                                            'datavalue' => [
                                                'type' => 'wikibase-entityid',
                                                'value' => ['id' => 'Q5'],
                                            ],
                                        ],
                                    ]],
                                    'P21' => $genderClaims,
                                ],
                            ],
                        ],
                    ], 200);
                }

                if (isset($labelEntities[$ids])) {
                    return Http::response(['entities' => $labelEntities], 200);
                }
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function genderClaim(string $id): array
    {
        return [
            'rank' => 'normal',
            'mainsnak' => [
                'snaktype' => 'value',
                'datavalue' => [
                    'type' => 'wikibase-entityid',
                    'value' => ['id' => $id],
                ],
            ],
        ];
    }

    private function publicFigure(string $name, array $source, array $metadata = []): Span
    {
        return Span::create([
            'name' => $name,
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => array_merge(['subtype' => 'public_figure'], $metadata),
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => $source['url'],
                'type' => 'web',
            ]],
        ]);
    }

    private function processWithoutModelEvents(Span $person): array
    {
        $spanDispatcher = Span::getEventDispatcher();
        $connectionDispatcher = Connection::getEventDispatcher();
        Span::unsetEventDispatcher();
        Connection::unsetEventDispatcher();

        try {
            return app(WikipediaImportService::class)->processSpan($person);
        } finally {
            Span::setEventDispatcher($spanDispatcher);
            Connection::setEventDispatcher($connectionDispatcher);
        }
    }

    private function sourcesContain(Span $span, string $url): bool
    {
        foreach ($span->sources ?? [] as $source) {
            if (is_array($source) && ($source['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }
}
