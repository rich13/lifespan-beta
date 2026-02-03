<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListPlaquePersonPlaceCandidates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     * - --limit= : Limit the number of plaque spans scanned (for testing)
     * - --only-lived-here=0|1 : When 1, only output plaques whose description contains "lived here"
     * - --only-residence-match=0|1 : When 1, only output plaques where a person→residence→place matches a plaque location
     * - --create-missing-residences=0|1 : When 1, create residence connections where missing and extracted dates exist
     * - --dry-run=1|0 : When creating, 1 = only list what would be created (default), 0 = apply changes
     * - --create-limit=0 : When creating, cap the number of residence connections to create (0 = no limit, for testing)
     * - --user= : Owner user ID for created spans (default: system@lifespan.app)
     */
    protected $signature = 'blue-plaques:list-plaque-person-place
        {--limit=0 : Limit number of plaque spans to scan}
        {--only-lived-here=0 : Only include plaques whose description contains "lived here"}
        {--only-residence-match=0 : Only include plaques where a person residence matches a plaque location}
        {--create-missing-residences=0 : Create residence connections where missing and description has extracted dates}
        {--dry-run=1 : When creating, 1=preview only, 0=apply}
        {--create-limit=0 : When creating, max number of residence connections to create (0=no limit)}
        {--user= : Owner user ID for created connection spans}';

    /**
     * The console command description.
     */
    protected $description = 'List plaques that have both a featured person and a location (plaque → person and plaque → place connections).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $onlyLivedHere = (bool) $this->option('only-lived-here');
        $onlyResidenceMatch = (bool) $this->option('only-residence-match');
        $createMissingResidences = (bool) $this->option('create-missing-residences');
        $dryRun = (bool) $this->option('dry-run');
        $createLimit = (int) $this->option('create-limit');

        // Coloured symbols for console output
        $tick = '<fg=green>✓</>';
        $cross = '<fg=red>✗</>';

        $query = Span::query()
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'plaque')
            ->with([
                'connectionsAsSubject.child' => function ($q) {
                    $q->select('id', 'name', 'type_id');
                },
                'connectionsAsSubject.connectionSpan',
                'connectionsAsObject.parent' => function ($q) {
                    $q->select('id', 'name', 'type_id', 'metadata');
                },
                'connectionsAsObject.connectionSpan',
            ]);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $plaques = $query->get();

        if ($plaques->isEmpty()) {
            $this->warn('No plaque spans found (type_id=thing, metadata subtype contains "plaque").');
            return 0;
        }

        $lines = [];
        $totalPlaques = 0;
        $totalCandidatePlaques = 0;
        $totalConnections = 0;
        /** @var array<int, array{person: Span, place: Span, start_year: int, end_year: int, plaque_name: string}> */
        $toCreate = [];
        $toCreateKeys = [];

        foreach ($plaques as $plaque) {
            $totalPlaques++;

            // Track how many lines we had before this plaque so we can
            // insert a separating blank line between groups.
            $linesBeforePlaque = count($lines);

            $subjectConnections = $plaque->connectionsAsSubject;
            $objectConnections = $plaque->connectionsAsObject;

            // People featured on this plaque: plaque (parent) --features--> person (child)
            $personConnections = $subjectConnections->filter(function ($connection) {
                return $connection->type_id === 'features'
                    && $connection->child
                    && $connection->child->type_id === 'person';
            });

            // Places where this plaque is located: plaque (parent) --located--> place (child)
            $locationConnections = $subjectConnections->filter(function ($connection) {
                return $connection->type_id === 'located'
                    && $connection->child
                    && $connection->child->type_id === 'place';
            });

            // 1) Plaque description contains "lived" (here / in / at etc.)?
            $description = $plaque->description ?? '';
            $hasLivedPhrase = preg_match('/\blived\s+(?:here|in|at)\b/i', $description);
            if (!$hasLivedPhrase) {
                $hasLivedPhrase = stripos($description, 'lived') !== false;
            }
            $symbol = $hasLivedPhrase ? $tick : $cross;

            $livedSnippet = $this->extractLivedSnippet($description);
            $extractedDates = $this->extractLivedDatesFromDescription($description);

            if ($onlyLivedHere && !$hasLivedPhrase) {
                // Skip this plaque entirely if we're only interested in "lived" plaques
                continue;
            }

            if ($personConnections->isEmpty() || $locationConnections->isEmpty()) {
                continue;
            }

            $personIds = $personConnections->pluck('child_id')->filter()->unique()->values();
            $placeIds = $locationConnections->pluck('child_id')->filter()->unique()->values();

            // Pre-calculate whether ANY person→residence→place exists matching this plaque's locations
            $hasAnyResidenceMatch = false;
            $residenceConnections = collect();
            if ($personIds->isNotEmpty() && $placeIds->isNotEmpty()) {
                $residenceConnections = Connection::query()
                    ->where('type_id', 'residence')
                    ->whereIn('parent_id', $personIds)
                    ->whereIn('child_id', $placeIds)
                    ->with('connectionSpan')
                    ->get()
                    ->groupBy(function (Connection $connection) {
                        return $connection->parent_id . '|' . $connection->child_id;
                    });

                $hasAnyResidenceMatch = $residenceConnections->isNotEmpty();
            }

            if ($onlyResidenceMatch && !$hasAnyResidenceMatch) {
                continue;
            }

            $totalCandidatePlaques++;

            // Example: "✓ Plaque Name [description contains 'lived']: \"...lived here 1837-1842...\""
            $lines[] = sprintf(
                "%s %s [description contains 'lived']%s",
                $symbol,
                $plaque->name,
                $livedSnippet !== '' ? ': "' . $livedSnippet . '"' : ''
            );

            // Extracted start/end dates from description (for creating residence connections later)
            $datesSymbol = $extractedDates !== null ? $tick : $cross;
            $datesText = $extractedDates !== null
                ? sprintf('%d - %d', $extractedDates['start_year'], $extractedDates['end_year'])
                : '[none]';
            $lines[] = sprintf(
                '%s %s [extracted dates from description]: %s',
                $datesSymbol,
                $plaque->name,
                $datesText
            );

            // 1) Plaque FEATURES Person connections
            foreach ($personConnections as $personConn) {
                $person = $personConn->child;
                if (!$person) {
                    continue;
                }

                $dateRange = $this->formatConnectionDateRange($personConn);
                $lines[] = sprintf(
                    '%s %s --features--> %s %s',
                    $tick,
                    $plaque->name,
                    $person->name,
                    $dateRange
                );
                $totalConnections++;
            }

            // 2) Plaque LOCATED Place connections
            foreach ($locationConnections as $locationConn) {
                $place = $locationConn->child;
                if (!$place) {
                    continue;
                }

                $dateRange = $this->formatConnectionDateRange($locationConn);
                $lines[] = sprintf(
                    '%s %s --located--> %s %s',
                    $tick,
                    $plaque->name,
                    $place->name,
                    $dateRange
                );
                $totalConnections++;
            }

            // 3) Person RESIDENCE Place connections (person →residence→ place)
            if ($personIds->isNotEmpty() && $placeIds->isNotEmpty()) {
                foreach ($personConnections as $personConn) {
                    $person = $personConn->child;
                    if (!$person) {
                        continue;
                    }

                    foreach ($locationConnections as $locationConn) {
                        $place = $locationConn->child;
                        if (!$place) {
                            continue;
                        }

                        $key = $person->id . '|' . $place->id;
                        $matchingResidences = $residenceConnections->get($key, collect());
                        $hasResidence = $matchingResidences->isNotEmpty();
                        $symbolRes = $hasResidence ? $tick : $cross;

                        $dateRange = $this->formatConnectionDateRange($matchingResidences->first());

                        // Example: "✓ Charles Darwin --residence--> Gower Street, London [1837 - 1842]"
                        $lines[] = sprintf(
                            '%s %s --residence--> %s %s',
                            $symbolRes,
                            $person->name,
                            $place->name,
                            $dateRange
                        );
                        $totalConnections++;

                        // Collect for --create-missing-residences: missing residence + extracted dates (dedupe by person|place)
                        if ($createMissingResidences && !$hasResidence && $extractedDates !== null) {
                            $createKey = $person->id . '|' . $place->id;
                            if (!isset($toCreateKeys[$createKey])) {
                                $toCreateKeys[$createKey] = true;
                                $toCreate[] = [
                                    'person' => $person,
                                    'place' => $place,
                                    'start_year' => $extractedDates['start_year'],
                                    'end_year' => $extractedDates['end_year'],
                                    'plaque_name' => $plaque->name,
                                ];
                            }
                        }
                    }
                }
            }

            // 4) Photo FEATURES Plaque connections (photo is parent, plaque is child)
            $photoFeatureConnections = $objectConnections->filter(function ($connection) {
                if ($connection->type_id !== 'features' || !$connection->parent) {
                    return false;
                }

                $parent = $connection->parent;
                $metadata = $parent->metadata ?? [];
                $subtype = $metadata['subtype'] ?? null;

                return $parent->type_id === 'thing' && $subtype === 'photo';
            });

            foreach ($photoFeatureConnections as $photoConn) {
                $photo = $photoConn->parent;
                if (!$photo) {
                    continue;
                }

                $dateRange = $this->formatConnectionDateRange($photoConn);
                $lines[] = sprintf(
                    '%s %s <--features-- %s %s',
                    $tick,
                    $plaque->name,
                    $photo->name,
                    $dateRange
                );
                $totalConnections++;

                // 5) Person <--features-- Photo-of-plaque connections
                // Photo (parent) --features--> Person (child) where the person is
                // one of the people featured on this plaque.
                if ($personIds->isNotEmpty()) {
                    $photoPersonConnections = $photo->connectionsAsSubject()
                        ->where('type_id', 'features')
                        ->whereIn('child_id', $personIds)
                        ->with(['child', 'connectionSpan'])
                        ->get();

                    // Map existing photo→person connections by person ID for quick lookup
                    $photoPersonMap = [];
                    foreach ($photoPersonConnections as $ppConn) {
                        if ($ppConn->child) {
                            $photoPersonMap[$ppConn->child_id] = true;
                        }
                    }

                    // For each person featured on the plaque, report whether a photo→person
                    // "features" connection exists (tick) or not (cross).
                    foreach ($personConnections as $personConn) {
                        $person = $personConn->child;
                        if (!$person) {
                            continue;
                        }

                        $hasPhotoPersonFeature = isset($photoPersonMap[$person->id]);
                        $symbol = $hasPhotoPersonFeature ? $tick : $cross;

                        $ppConn = $photoPersonConnections->firstWhere('child_id', $person->id);
                        $dateRange = $this->formatConnectionDateRange($ppConn);

                        $lines[] = sprintf(
                            '%s %s <--features-- %s %s',
                            $symbol,
                            $person->name,
                            $photo->name,
                            $dateRange
                        );
                        $totalConnections++;
                    }
                }
            }

            // If we added any lines for this plaque, add a blank line to
            // visually separate this group from the next one.
            if (count($lines) > $linesBeforePlaque) {
                $lines[] = '';
            }
        }

        if (empty($lines)) {
            $this->info('No plaques found that have both a featured person and a location.');
            return 0;
        }

        foreach ($lines as $line) {
            $this->line($line);
        }

        $this->newLine();
        $this->info("Analysed {$totalPlaques} plaque spans.");
        $this->info("Found {$totalCandidatePlaques} plaques that have both a featured person and a location.");
        $this->info("Emitted {$totalConnections} connection statements around those plaques (features / located / photo–plaque / photo–person).");

        if ($createMissingResidences && !empty($toCreate)) {
            $toCreateCapped = $createLimit > 0 ? array_slice($toCreate, 0, $createLimit) : $toCreate;
            $totalCandidates = count($toCreate);
            if ($createLimit > 0 && $totalCandidates > $createLimit) {
                $this->newLine();
                $this->comment("Create limit: {$createLimit} of {$totalCandidates} candidate(s) will be used.");
            }

            if ($dryRun) {
                $this->newLine();
                $this->warn('Would create ' . count($toCreateCapped) . ' residence connection(s) (use --dry-run=0 to apply):');
                foreach ($toCreateCapped as $item) {
                    $this->line(sprintf(
                        '  %s --residence--> %s [%d - %d] (from plaque: %s)',
                        $item['person']->name,
                        $item['place']->name,
                        $item['start_year'],
                        $item['end_year'],
                        $item['plaque_name']
                    ));
                }
                return 0;
            }

            $user = $this->resolveUser($this->option('user'));
            if ($user === null) {
                return 1;
            }

            $this->newLine();
            $created = 0;
            $skipped = 0;

            Connection::$skipCacheClearingDuringImport = true;

            try {
                foreach ($toCreateCapped as $item) {
                    $person = $item['person'];
                    $place = $item['place'];

                    $existing = Connection::query()
                        ->where('type_id', 'residence')
                        ->where('parent_id', $person->id)
                        ->where('child_id', $place->id)
                        ->exists();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    DB::transaction(function () use ($item, $user, &$created) {
                        $person = $item['person'];
                        $place = $item['place'];

                        $connectionSpan = Span::create([
                            'name' => $person->name . ' lived in ' . $place->name,
                            'type_id' => 'connection',
                            'owner_id' => $user->id,
                            'updater_id' => $user->id,
                            'access_level' => 'public',
                            'state' => 'complete',
                            'start_year' => $item['start_year'],
                            'end_year' => $item['end_year'],
                            'start_precision' => 'year',
                            'end_precision' => 'year',
                        ]);

                        Connection::create([
                            'type_id' => 'residence',
                            'parent_id' => $person->id,
                            'child_id' => $place->id,
                            'connection_span_id' => $connectionSpan->id,
                        ]);

                        $created++;
                        $this->line(sprintf(
                            '  <fg=green>Created</> %s --residence--> %s [%d - %d]',
                            $person->name,
                            $place->name,
                            $item['start_year'],
                            $item['end_year']
                        ));
                    });
                }

                $this->newLine();
                $this->info("Created {$created} residence connection(s)." . ($skipped > 0 ? " Skipped {$skipped} (already existed)." : ''));
            } finally {
                Connection::$skipCacheClearingDuringImport = false;
            }
        }

        return 0;
    }

    private function resolveUser(?string $userId): ?User
    {
        if ($userId !== null && $userId !== '') {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");
                return null;
            }
            return $user;
        }

        return User::firstOrCreate(
            ['email' => 'system@lifespan.app'],
            [
                'name' => 'System',
                'password' => bcrypt(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Extract start and end years from description text after "lived".
     * Looks for patterns like "lived here 1868-1873" or "lived here 1874 to 1895".
     * Returns ['start_year' => int, 'end_year' => int] or null if no match.
     */
    private function extractLivedDatesFromDescription(string $description): ?array
    {
        if ($description === '') {
            return null;
        }

        $pos = stripos($description, 'lived');
        if ($pos === false) {
            return null;
        }

        $afterLived = substr($description, $pos);

        // Match YYYY-YYYY or YYYY to YYYY (with optional spaces/dash)
        if (preg_match('/\b(\d{4})\s*(?:-|to)\s*(\d{4})\b/i', $afterLived, $m)) {
            $startYear = (int) $m[1];
            $endYear = (int) $m[2];
            if ($startYear >= 1 && $startYear <= 9999 && $endYear >= 1 && $endYear <= 9999) {
                return [
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ];
            }
        }

        return null;
    }

    /**
     * Extract a short snippet of description text around the first "lived" phrase
     * (e.g. "lived here", "lived in", "lived at"). Returns empty string if no match.
     */
    private function extractLivedSnippet(string $description): string
    {
        if ($description === '') {
            return '';
        }

        $pos = stripos($description, 'lived');
        if ($pos === false) {
            return '';
        }

        $before = 25;
        $after = 100;
        $start = max(0, $pos - $before);
        $len = min(mb_strlen($description) - $start, $pos - $start + $after);
        $snippet = mb_substr($description, $start, $len);

        if ($start > 0) {
            $snippet = '...' . ltrim($snippet, " \t\n\r\0\x0B.,;:!?");
        }
        if ($start + $len < mb_strlen($description)) {
            $snippet = rtrim($snippet, " \t\n\r\0\x0B");
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * Format start/end dates for a connection as "[start - end]" using its connection span.
     * Returns "[date - date]", "[date - ]" for ongoing, or "[]" when absent.
     */
    private function formatConnectionDateRange(?Connection $connection): string
    {
        if (!$connection) {
            return '[]';
        }

        $start = $connection->formatted_start_date;
        $end = $connection->formatted_end_date;

        if ($start === null && $end === null) {
            return '[]';
        }

        return sprintf('[%s - %s]', $start ?? '?', $end ?? '');
    }
}

