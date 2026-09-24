<?php

namespace Tests\Unit\Services;

use App\Services\WikidataSpanTypeResolver;
use App\Services\WikimediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSpanTypeResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_a_human_stays_a_person_even_when_another_class_is_present(): void
    {
        $resolved = $this->resolver()->resolve($this->entity(['Q5', 'Q571']));

        $this->assertSame('matched', $resolved['status']);
        $this->assertSame('person', $resolved['type_id']);
    }

    public function test_a_musical_group_is_a_band(): void
    {
        $resolved = $this->resolver()->resolve($this->entity(['Q215380']));

        $this->assertSame('band', $resolved['type_id']);
        $this->assertNull($resolved['subtype']);
    }

    public function test_a_subclass_of_musical_group_is_a_band(): void
    {
        Http::fake(function ($request) {
            if (($request->data()['ids'] ?? '') !== 'Q999001') {
                return Http::response([], 404);
            }

            return Http::response([
                'entities' => [
                    'Q999001' => [
                        'id' => 'Q999001',
                        'claims' => [
                            'P279' => [[
                                'rank' => 'normal',
                                'mainsnak' => [
                                    'snaktype' => 'value',
                                    'datavalue' => [
                                        'type' => 'wikibase-entityid',
                                        'value' => ['id' => 'Q215380'],
                                    ],
                                ],
                            ]],
                        ],
                    ],
                ],
            ], 200);
        });

        $resolved = $this->resolver()->resolve($this->entity(['Q999001']));

        $this->assertSame('matched', $resolved['status']);
        $this->assertSame('band', $resolved['type_id']);
        $this->assertSame('Q215380', $resolved['via']);
    }

    public function test_a_book_and_a_film_on_the_same_item_are_ambiguous(): void
    {
        $resolved = $this->resolver()->resolve($this->entity(['Q571', 'Q11424']));

        $this->assertSame('ambiguous', $resolved['status']);
    }

    public function test_a_deprecated_human_class_does_not_override_a_band(): void
    {
        $entity = [
            'id' => 'Q1',
            'claims' => [
                'P31' => [
                    [
                        'rank' => 'deprecated',
                        'mainsnak' => [
                            'snaktype' => 'value',
                            'datavalue' => [
                                'type' => 'wikibase-entityid',
                                'value' => ['id' => 'Q5'],
                            ],
                        ],
                    ],
                    [
                        'rank' => 'normal',
                        'mainsnak' => [
                            'snaktype' => 'value',
                            'datavalue' => [
                                'type' => 'wikibase-entityid',
                                'value' => ['id' => 'Q215380'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resolved = $this->resolver()->resolve($entity);

        $this->assertSame('band', $resolved['type_id']);
    }

    public function test_a_disambiguation_page_is_not_a_span_type(): void
    {
        $resolved = $this->resolver()->resolve($this->entity(['Q4167410']));

        $this->assertSame('disambiguation', $resolved['status']);
    }

    private function resolver(): WikidataSpanTypeResolver
    {
        return new WikidataSpanTypeResolver(new WikimediaService());
    }

    /**
     * @param  list<string>  $instanceOf
     * @return array<string, mixed>
     */
    private function entity(array $instanceOf): array
    {
        return [
            'id' => 'Q1',
            'claims' => [
                'P31' => array_map(fn (string $id) => [
                    'rank' => 'normal',
                    'mainsnak' => [
                        'snaktype' => 'value',
                        'datavalue' => [
                            'type' => 'wikibase-entityid',
                            'value' => ['id' => $id],
                        ],
                    ],
                ], $instanceOf),
            ],
        ];
    }
}
