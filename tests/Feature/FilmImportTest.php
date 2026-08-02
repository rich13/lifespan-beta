<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\SpanType;
use App\Models\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FilmImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        
        // Create required span types
        SpanType::firstOrCreate(['type_id' => 'thing'], ['name' => 'Thing', 'description' => 'A thing']);
        SpanType::firstOrCreate(['type_id' => 'person'], ['name' => 'Person', 'description' => 'A person']);
        SpanType::firstOrCreate(['type_id' => 'connection'], ['name' => 'Connection', 'description' => 'A connection']);
        
        // Create required connection types
        ConnectionType::firstOrCreate(['type' => 'created'], [
            'forward_predicate' => 'created',
            'forward_description' => 'Created',
            'inverse_predicate' => 'was created by',
            'inverse_description' => 'Was created by',
            'constraint_type' => 'single'
        ]);
        
        ConnectionType::firstOrCreate(['type' => 'features'], [
            'forward_predicate' => 'features',
            'forward_description' => 'Features',
            'inverse_predicate' => 'is featured in',
            'inverse_description' => 'Is featured in',
            'constraint_type' => 'single'
        ]);
    }

    /** @test */
    public function it_can_search_for_films_by_director()
    {
        // Mock SPARQL query response
        Http::fake([
            'https://query.wikidata.org/sparql*' => Http::response([
                'results' => [
                    'bindings' => [
                        [
                            'film' => ['value' => 'http://www.wikidata.org/entity/Q12345'],
                            'filmLabel' => ['value' => 'Test Film 1'],
                            'releaseDate' => ['value' => '2020-01-15T00:00:00Z']
                        ],
                        [
                            'film' => ['value' => 'http://www.wikidata.org/entity/Q12346'],
                            'filmLabel' => ['value' => 'Test Film 2'],
                            'releaseDate' => ['value' => '2021-06-20T00:00:00Z']
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.import.film.search'), [
                'person_id' => 'Q67890',
                'role' => 'director'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'films' => [
                '*' => [
                    'id',
                    'title',
                    'entity_id'
                ]
            ]
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertCount(2, $response->json('films'));
    }

    /** @test */
    public function it_can_search_for_films_by_actor()
    {
        // Mock SPARQL query response
        Http::fake([
            'https://query.wikidata.org/sparql*' => Http::response([
                'results' => [
                    'bindings' => [
                        [
                            'film' => ['value' => 'http://www.wikidata.org/entity/Q12345'],
                            'filmLabel' => ['value' => 'Test Film 1'],
                            'releaseDate' => ['value' => '2020-01-15T00:00:00Z']
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.import.film.search'), [
                'person_id' => 'Q11111',
                'role' => 'actor'
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('films'));
    }

    /**
     * Get film entity response
     */
    protected function getFilmEntityResponse(): array
    {
        return [
            'entities' => [
                'Q12345' => [
                    'id' => 'Q12345',
                    'labels' => ['en' => ['value' => 'Test Film']],
                    'descriptions' => ['en' => ['value' => 'A test film']],
                    'claims' => [
                        'P31' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => ['id' => 'Q11424'] // film
                                    ]
                                ]
                            ]
                        ],
                        'P577' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => [
                                            'time' => '+2020-01-15T00:00:00Z',
                                            'precision' => 9 // day precision (9 or less = day)
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'P57' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => ['id' => 'Q67890']
                                    ]
                                ]
                            ]
                        ],
                        'P161' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => ['id' => 'Q11111']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'sitelinks' => [
                        'enwiki' => [
                            'site' => 'enwiki',
                            'title' => 'Test Film',
                            'url' => 'https://en.wikipedia.org/wiki/Test_Film'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get director entity response
     */
    protected function getDirectorEntityResponse(): array
    {
        return [
            'entities' => [
                'Q67890' => [
                    'id' => 'Q67890',
                    'labels' => ['en' => ['value' => 'Test Director']],
                    'descriptions' => ['en' => ['value' => 'A test director']],
                    'claims' => [
                        'P569' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => [
                                            'time' => '+1980-05-10T00:00:00Z',
                                            'precision' => 9 // day precision (9 or less = day)
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get actor entity response
     */
    protected function getActorEntityResponse(): array
    {
        return [
            'entities' => [
                'Q11111' => [
                    'id' => 'Q11111',
                    'labels' => ['en' => ['value' => 'Test Actor']],
                    'descriptions' => ['en' => ['value' => 'A test actor']],
                    'claims' => [
                        'P569' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => [
                                            'time' => '+1990-03-20T00:00:00Z',
                                            'precision' => 9 // day precision (9 or less = day)
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Wikipedia API response for extract
     */
    protected function getWikipediaExtractResponse(): array
    {
        return [
            'query' => [
                'pages' => [
                    [
                        'extract' => 'Test Film is a test film description.'
                    ]
                ]
            ]
        ];
    }
}

