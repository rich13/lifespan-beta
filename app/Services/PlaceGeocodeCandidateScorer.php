<?php

namespace App\Services;

/**
 * Scores Nominatim search hits against a cleaned place query so we can
 * auto-accept a match only when ambiguity is low.
 */
class PlaceGeocodeCandidateScorer
{
    public const AUTO_ACCEPT_MIN_SCORE = 0.72;

    public const AUTO_ACCEPT_MARGIN = 0.15;

    /** Same-name OSM ways within this distance are treated as one street, not two places */
    public const DUPLICATE_MAX_METRES = 250;

    /** OSM types that are usually the intended result for an address span */
    private const ADDRESS_PREFERRED_TYPES = [
        'house', 'building', 'residential', 'yes', 'apartments', 'detached',
        'terrace', 'semidetached_house', 'office', 'address',
    ];

    /** OSM types that are usually the wrong POI when geocoding a dwelling */
    private const ADDRESS_PENALISED_TYPES = [
        'post_box', 'telephone', 'atm', 'waste_basket', 'bench', 'toilets',
        'parking', 'bus_stop', 'traffic_signals', 'give_way', 'crossing',
        'street_lamp', 'surveillance',
    ];

    private const LOCALITY_PREFERRED_TYPES = [
        'city', 'town', 'village', 'hamlet', 'suburb', 'neighbourhood',
        'quarter', 'city_district', 'borough', 'administrative', 'county',
        'state', 'country', 'island',
    ];

    /**
     * @param list<array<string, mixed>> $rawResults Nominatim JSON objects
     * @return list<array{raw: array<string, mixed>, score: float, reasons: list<string>}>
     */
    public function score(array $rawResults, PlaceGeocodeQuery $query): array
    {
        $scored = [];
        foreach ($rawResults as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            [$score, $reasons] = $this->scoreOne($raw, $query);
            $scored[] = [
                'raw' => $raw,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $this->collapseNearDuplicates($scored, $query);
    }

    /**
     * @param list<array{raw: array<string, mixed>, score: float, reasons: list<string>}> $scored
     * @return array{raw: array<string, mixed>, score: float, reasons: list<string>}|null
     */
    public function pickAutoAccept(array $scored, PlaceGeocodeQuery $query): ?array
    {
        if ($scored === []) {
            return null;
        }

        $best = $scored[0];
        if ($best['score'] < self::AUTO_ACCEPT_MIN_SCORE) {
            return null;
        }

        $second = $scored[1]['score'] ?? 0.0;
        if (count($scored) > 1 && ($best['score'] - $second) < self::AUTO_ACCEPT_MARGIN) {
            return null;
        }

        if ($query->looksLikeAddress() && $this->isPenalisedAddressType($best['raw'])) {
            return null;
        }

        return $best;
    }

    /**
     * OSM often splits one street into several nearby ways. Keep one representative.
     *
     * @param list<array{raw: array<string, mixed>, score: float, reasons: list<string>}> $scored
     * @return list<array{raw: array<string, mixed>, score: float, reasons: list<string>}>
     */
    public function collapseNearDuplicates(array $scored, PlaceGeocodeQuery $query): array
    {
        $kept = [];
        foreach ($scored as $item) {
            $merged = false;
            foreach ($kept as $index => $existing) {
                if (!$this->isNearDuplicate($existing['raw'], $item['raw'])) {
                    continue;
                }
                if ($this->preferCandidate($item, $existing, $query)) {
                    $kept[$index] = $item;
                }
                $merged = true;
                break;
            }
            if (!$merged) {
                $kept[] = $item;
            }
        }

        usort($kept, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values($kept);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function isNearDuplicate(array $a, array $b): bool
    {
        $nameA = $this->normaliseForCompare((string) ($a['name'] ?? ''));
        $nameB = $this->normaliseForCompare((string) ($b['name'] ?? ''));
        if ($nameA === '' || $nameA !== $nameB) {
            return false;
        }

        $classA = strtolower((string) ($a['class'] ?? ''));
        $classB = strtolower((string) ($b['class'] ?? ''));
        if ($classA === '' || $classA !== $classB) {
            return false;
        }

        $numberA = strtolower((string) (($a['address']['house_number'] ?? '')));
        $numberB = strtolower((string) (($b['address']['house_number'] ?? '')));
        if ($numberA !== '' && $numberB !== '' && $numberA !== $numberB) {
            return false;
        }

        if (!isset($a['lat'], $a['lon'], $b['lat'], $b['lon'])) {
            return false;
        }

        return $this->haversineMetres(
            (float) $a['lat'],
            (float) $a['lon'],
            (float) $b['lat'],
            (float) $b['lon']
        ) <= self::DUPLICATE_MAX_METRES;
    }

    /**
     * @param array{raw: array<string, mixed>, score: float, reasons: list<string>} $challenger
     * @param array{raw: array<string, mixed>, score: float, reasons: list<string>} $incumbent
     */
    private function preferCandidate(array $challenger, array $incumbent, PlaceGeocodeQuery $query): bool
    {
        $postcodeChallenger = $this->postcodeMatchStrength($challenger['raw'], $query);
        $postcodeIncumbent = $this->postcodeMatchStrength($incumbent['raw'], $query);
        if ($postcodeChallenger !== $postcodeIncumbent) {
            return $postcodeChallenger > $postcodeIncumbent;
        }

        return $challenger['score'] > $incumbent['score'];
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function postcodeMatchStrength(array $raw, PlaceGeocodeQuery $query): int
    {
        $address = is_array($raw['address'] ?? null) ? $raw['address'] : [];
        $haystack = strtoupper(implode(' ', array_filter([
            (string) ($raw['display_name'] ?? ''),
            (string) ($address['postcode'] ?? ''),
        ])));

        if ($query->postalCode) {
            $expected = strtoupper(trim(preg_replace('/\s+/', ' ', $query->postalCode) ?? $query->postalCode));
            $compact = str_replace(' ', '', $expected);
            $haystackCompact = str_replace(' ', '', $haystack);
            if (str_contains($haystack, $expected) || ($compact !== '' && str_contains($haystackCompact, $compact))) {
                return 2;
            }
        }

        if ($query->outwardCode) {
            $outward = strtoupper($query->outwardCode);
            if (preg_match('/\b' . preg_quote($outward, '/') . '\b/', $haystack)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{0: float, 1: list<string>}
     */
    private function scoreOne(array $raw, PlaceGeocodeQuery $query): array
    {
        $score = 0.35;
        $reasons = [];
        $type = strtolower((string) ($raw['type'] ?? ''));
        $class = strtolower((string) ($raw['class'] ?? ''));
        $display = (string) ($raw['display_name'] ?? '');
        $name = (string) ($raw['name'] ?? ($raw['namedetails']['name'] ?? ''));
        $address = is_array($raw['address'] ?? null) ? $raw['address'] : [];

        $nameSimilarity = $this->bestNameSimilarity($query, $name, $display, $address);
        $score += $nameSimilarity * 0.35;
        if ($nameSimilarity >= 0.8) {
            $reasons[] = 'name match';
        }

        if ($query->looksLikeAddress()) {
            if (in_array($type, self::ADDRESS_PREFERRED_TYPES, true) || $class === 'building' || $class === 'place') {
                $score += 0.22;
                $reasons[] = 'address-like type';
            }
            if ($this->isPenalisedAddressType($raw)) {
                $score -= 0.45;
                $reasons[] = 'unlikely poi for an address';
            }
            if ($class === 'highway' && !in_array($type, ['residential', 'living_street', 'pedestrian'], true)) {
                $score -= 0.08;
            }
            if ($query->houseNumber && $this->houseNumberMatches($query->houseNumber, $raw, $address, $display)) {
                $score += 0.18;
                $reasons[] = 'house number';
            } elseif ($query->houseNumber && in_array($type, self::ADDRESS_PREFERRED_TYPES, true)) {
                $score -= 0.05;
            }
        } else {
            if (in_array($type, self::LOCALITY_PREFERRED_TYPES, true) || $class === 'boundary') {
                $score += 0.2;
                $reasons[] = 'locality type';
            }
            if ($this->isPenalisedAddressType($raw)) {
                $score -= 0.3;
            }
        }

        if ($query->locality && $this->haystackContains($display, $query->locality)) {
            $score += 0.12;
            $reasons[] = 'locality';
        }

        if ($query->region && $this->haystackContains($display, $query->region)) {
            $score += 0.08;
            $reasons[] = 'region';
        }

        if ($query->country && $this->haystackContains($display, $query->country)) {
            $score += 0.08;
            $reasons[] = 'country';
        } elseif ($query->countryCode === 'gb' && $this->haystackContains($display, 'united kingdom')) {
            $score += 0.08;
            $reasons[] = 'country';
        }

        $postcodeStrength = $this->postcodeMatchStrength($raw, $query);
        if ($postcodeStrength >= 2) {
            $score += 0.16;
            $reasons[] = 'postcode';
        } elseif ($postcodeStrength === 1) {
            $score += 0.06;
            $reasons[] = 'outward code';
        }

        if ($query->latitude !== null && $query->longitude !== null) {
            $lat = isset($raw['lat']) ? (float) $raw['lat'] : null;
            $lon = isset($raw['lon']) ? (float) $raw['lon'] : null;
            if ($lat !== null && $lon !== null) {
                $metres = $this->haversineMetres($query->latitude, $query->longitude, $lat, $lon);
                if ($metres <= 150) {
                    $score += 0.2;
                    $reasons[] = 'very close to known coordinates';
                } elseif ($metres <= 600) {
                    $score += 0.1;
                    $reasons[] = 'near known coordinates';
                } elseif ($metres > 5000) {
                    $score -= 0.25;
                    $reasons[] = 'far from known coordinates';
                }
            }
        }

        if (isset($raw['extratags']['admin_level']) && !$query->looksLikeAddress()) {
            $score += 0.05;
        }

        $importance = (float) ($raw['importance'] ?? 0);
        $score += min(0.08, $importance * 0.1);

        return [max(0, min(1, $score)), $reasons];
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function isPenalisedAddressType(array $raw): bool
    {
        $type = strtolower((string) ($raw['type'] ?? ''));

        return in_array($type, self::ADDRESS_PENALISED_TYPES, true);
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $address
     */
    private function houseNumberMatches(string $expected, array $raw, array $address, string $display): bool
    {
        $expected = strtolower($expected);
        $candidates = [
            $address['house_number'] ?? null,
            $raw['name'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && strtolower($candidate) === $expected) {
                return true;
            }
            if (is_string($candidate) && preg_match('/\b' . preg_quote($expected, '/') . '\b/i', $candidate)) {
                return true;
            }
        }

        return (bool) preg_match('/\b' . preg_quote($expected, '/') . '\b/', $display);
    }

    /**
     * @param array<string, mixed> $address
     */
    private function bestNameSimilarity(PlaceGeocodeQuery $query, string $name, string $display, array $address): float
    {
        $targets = array_filter([
            $query->houseName,
            $query->street ? trim(($query->houseNumber ? $query->houseNumber . ' ' : '') . $query->street) : null,
            $query->primaryName,
            $query->cleanedName,
        ]);

        $candidates = array_filter([
            $name,
            explode(',', $display)[0] ?? null,
            $address['road'] ?? null,
            isset($address['house_number'], $address['road'])
                ? $address['house_number'] . ' ' . $address['road']
                : null,
        ]);

        $best = 0.0;
        foreach ($targets as $target) {
            foreach ($candidates as $candidate) {
                $best = max($best, $this->similarity((string) $target, (string) $candidate));
            }
        }

        return $best;
    }

    private function similarity(string $a, string $b): float
    {
        $a = $this->normaliseForCompare($a);
        $b = $this->normaliseForCompare($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        if (str_contains($b, $a) || str_contains($a, $b)) {
            return 0.9;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    private function normaliseForCompare(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace("/[^a-z0-9\s]/", '', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function haystackContains(string $haystack, string $needle): bool
    {
        return str_contains($this->normaliseForCompare($haystack), $this->normaliseForCompare($needle));
    }

    private function haversineMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
