<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Console\Command;

class AnalysePlaqueResidencePatterns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     * - --limit= : Limit the number of plaque spans scanned (for testing)
     * - --only-matches=0|1 : When 1, only output rows where a residence match exists
     * - --coordinate-matches=0|1 : When 1, also treat residence places with identical coordinates as matches
     */
    protected $signature = 'blue-plaques:residence-patterns
        {--limit=0 : Limit number of plaque spans to scan}
        {--only-matches=1 : Only output rows where a matching residence exists}
        {--coordinate-matches=0 : Also match residence places that share identical coordinates with plaque locations}';

    /**
     * The console command description.
     */
    protected $description = 'Analyse plaques to find cases where featured people already have residence connections to the plaque location.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $onlyMatches = (bool) $this->option('only-matches');
        $coordinateMatches = (bool) $this->option('coordinate-matches');

        $query = Span::query()
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'plaque');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $plaques = $query->get();

        if ($plaques->isEmpty()) {
            $this->warn('No plaque spans found (type_id=thing, metadata subtype contains "plaque").');
            return 0;
        }

        $rows = [];
        $totalPlaques = 0;
        $totalMatches = 0;

        // Cache residence connections per person to avoid repeated queries
        $residencesByPerson = [];

        foreach ($plaques as $plaque) {
            $totalPlaques++;

            // Load all subject-side connections for this plaque once
            $plaque->loadMissing(['connectionsAsSubject.child']);
            $subjectConnections = $plaque->connectionsAsSubject;

            // People featured on this plaque: plaque (parent) --features--> person (child)
            $personConnections = $subjectConnections
                ->where('type_id', 'features')
                ->filter(function (Connection $connection) {
                    return $connection->child && $connection->child->type_id === 'person';
                });

            // Places where this plaque is located: plaque (parent) --located--> place (child)
            $locationConnections = $subjectConnections
                ->where('type_id', 'located')
                ->filter(function (Connection $connection) {
                    return $connection->child && $connection->child->type_id === 'place';
                });

            if ($personConnections->isEmpty() || $locationConnections->isEmpty()) {
                // Either no featured people or no locations; optionally record as non-match context
                if (!$onlyMatches) {
                    $rows[] = [
                        'plaque' => $plaque->name,
                        'plaque_slug' => $plaque->slug,
                        'person' => '(none)',
                        'person_slug' => null,
                        'place' => '(none)',
                        'place_slug' => null,
                        'has_residence' => 'no',
                        'residence_connection_ids' => '',
                    ];
                }
                continue;
            }

            foreach ($personConnections as $personConn) {
                $person = $personConn->child;
                if (!$person) {
                    continue;
                }
                
                // Load and cache this person's residence connections (to places)
                if (!array_key_exists($person->id, $residencesByPerson)) {
                    $residencesByPerson[$person->id] = $person->connectionsAsSubject()
                        ->where('type_id', 'residence')
                        ->whereHas('child', function ($query) {
                            $query->where('type_id', 'place');
                        })
                        ->with('child')
                        ->get();
                }

                $residenceConnections = $residencesByPerson[$person->id];

                foreach ($locationConnections as $locationConn) {
                    $place = $locationConn->child;
                    if (!$place) {
                        continue;
                    }
                    
                    // 1) Exact match: person already has a residence connection to this exact place span
                    $exactMatches = $residenceConnections->filter(function (Connection $connection) use ($place) {
                        return $connection->child_id === $place->id;
                    });

                    // 2) Optional coordinate-based matches: residence place has identical coordinates to plaque location
                    $coordinateBasedMatches = collect();
                    if ($coordinateMatches) {
                        $coordinateBasedMatches = $residenceConnections->filter(function (Connection $connection) use ($place) {
                            // Skip when it's already an exact match (counted above)
                            if ($connection->child_id === $place->id) {
                                return false;
                            }

                            $residencePlace = $connection->child;
                            return $this->coordinatesMatch($residencePlace, $place);
                        });
                    }

                    $hasMatch = $exactMatches->isNotEmpty() || $coordinateBasedMatches->isNotEmpty();

                    if ($hasMatch) {
                        $totalMatches++;
                    }

                    if ($onlyMatches && !$hasMatch) {
                        continue;
                    }

                    $matchType = 'none';
                    if ($exactMatches->isNotEmpty() && $coordinateBasedMatches->isNotEmpty()) {
                        $matchType = 'exact+coordinates';
                    } elseif ($exactMatches->isNotEmpty()) {
                        $matchType = 'exact';
                    } elseif ($coordinateBasedMatches->isNotEmpty()) {
                        $matchType = 'coordinates';
                    }

                    $rows[] = [
                        'plaque' => $plaque->name,
                        'plaque_slug' => $plaque->slug,
                        'person' => $person->name,
                        'person_slug' => $person->slug,
                        'place' => $place->name,
                        'place_slug' => $place->slug,
                        'has_residence' => $hasMatch ? 'yes' : 'no',
                        'match_type' => $matchType,
                        'residence_connection_ids' => $exactMatches
                            ->merge($coordinateBasedMatches)
                            ->pluck('id')
                            ->unique()
                            ->implode(','),
                    ];
                }
            }
        }

        if (empty($rows)) {
            $this->info('No plaque / person / place triples found that match the criteria.');
            return 0;
        }

        // Output as a console table for quick inspection
        $this->table(
            [
                'Plaque',
                'Plaque Slug',
                'Person',
                'Person Slug',
                'Place',
                'Place Slug',
                'Has Residence?',
                'Match Type',
                'Residence Connection IDs',
            ],
            $rows
        );

        $this->newLine();
        $this->info("Analysed {$totalPlaques} plaque spans.");
        $this->info("Found {$totalMatches} plaque / person / place combinations where a residence connection already exists.");

        return 0;
    }

    /**
     * Check whether two place spans have identical coordinates.
     *
     * This is deliberately strict (exact float comparison) so that it only
     * flags very strong candidates for "same physical place".
     */
    private function coordinatesMatch(?Span $a, ?Span $b): bool
    {
        if (!$a || !$b) {
            return false;
        }

        $aMeta = $a->metadata ?? [];
        $bMeta = $b->metadata ?? [];

        $aCoords = $aMeta['coordinates'] ?? null;
        $bCoords = $bMeta['coordinates'] ?? null;

        if (
            !is_array($aCoords) ||
            !is_array($bCoords) ||
            !isset($aCoords['latitude'], $aCoords['longitude']) ||
            !isset($bCoords['latitude'], $bCoords['longitude'])
        ) {
            return false;
        }

        $aLat = (float) $aCoords['latitude'];
        $aLng = (float) $aCoords['longitude'];
        $bLat = (float) $bCoords['latitude'];
        $bLng = (float) $bCoords['longitude'];

        return $aLat === $bLat && $aLng === $bLng;
    }
}
