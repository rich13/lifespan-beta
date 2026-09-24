<?php

namespace App\Services;

/**
 * A cleaned geocoding query with structured hints derived from a place name
 * (and optional span metadata / coordinates).
 */
class PlaceGeocodeQuery
{
    public function __construct(
        public readonly string $originalName,
        public readonly string $cleanedName,
        public readonly string $primaryName,
        public readonly ?string $houseName = null,
        public readonly ?string $houseNumber = null,
        public readonly ?string $street = null,
        public readonly ?string $locality = null,
        public readonly ?string $region = null,
        public readonly ?string $country = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $outwardCode = null,
        public readonly ?string $subtype = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly bool $shouldSkip = false,
        public readonly string $skipReason = '',
    ) {
    }

    public function looksLikeAddress(): bool
    {
        if (in_array($this->subtype, ['address', 'building_property'], true)) {
            return true;
        }

        return $this->houseNumber !== null || $this->street !== null || $this->houseName !== null;
    }

    public function looksLikeLondon(): bool
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            if ($this->latitude >= 51.28 && $this->latitude <= 51.70
                && $this->longitude >= -0.55 && $this->longitude <= 0.35) {
                return true;
            }
        }

        $haystack = strtolower(implode(' ', array_filter([
            $this->originalName,
            $this->cleanedName,
            $this->locality,
            $this->region,
        ])));

        if (str_contains($haystack, 'london')) {
            return true;
        }

        $londonAreas = [
            'enfield', 'islington', 'camden', 'hackney', 'chelsea', 'kensington',
            'westminster', 'hampstead', 'greenwich', 'walthamstow', 'anerley',
            'palmers green', 'highbury', 'fulham', 'hammersmith', 'southwark',
            'lambeth', 'tower hamlets', 'newham', 'barnet', 'brent', 'ealing',
            'richmond', 'wandsworth', 'lewisham', 'bromley', 'croydon',
            'harrow', 'hounslow', 'kingston', 'merton', 'redbridge', 'sutton',
            'waltham forest', 'city of london', 'fitzrovia', 'soho', 'mayfair',
            'belgravia', 'knightsbridge', 'notting hill', 'clapham', 'brixton',
            'peckham', 'deptford', 'poplar', 'shoreditch', 'hoxton', 'dalston',
            'stoke newington', 'muswell hill', 'finchley', 'hendon', 'willesden',
            'kilburn', 'maida vale', 'paddington', 'bayswater', 'notting hill',
            'chiswick', 'hammersmith', 'putney', 'wimbledon', 'dulwich',
            'sydenham', 'crystal palace', 'penge', 'beckenham',
        ];
        foreach ($londonAreas as $area) {
            if (str_contains($haystack, $area)) {
                return true;
            }
        }

        $outward = strtoupper((string) $this->outwardCode);
        if ($outward === '') {
            return false;
        }

        $londonPrefixes = ['E', 'EC', 'N', 'NW', 'SE', 'SW', 'W', 'WC', 'BR', 'CR', 'EN', 'HA', 'IG', 'KT', 'RM', 'SM', 'TW', 'UB', 'WD'];
        foreach ($londonPrefixes as $prefix) {
            if ($outward === $prefix || str_starts_with($outward, $prefix) && strlen($outward) <= strlen($prefix) + 2) {
                if (in_array($prefix, ['E', 'N', 'W'], true)) {
                    // E/N/W also match other UK areas (e.g. EX, NE, WR) — only accept London-shaped codes.
                    if (preg_match('/^(E|EC|N|NW|SE|SW|W|WC)\d/', $outward)) {
                        return true;
                    }
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Free-text Nominatim queries to try, most specific first.
     *
     * @return list<string>
     */
    public function searchStrings(): array
    {
        $queries = [];

        if ($this->houseNumber && $this->street && $this->locality) {
            $queries[] = "{$this->houseNumber} {$this->street}, {$this->locality}";
            if ($this->country) {
                $queries[] = "{$this->houseNumber} {$this->street}, {$this->locality}, {$this->country}";
            }
            array_push($queries, ...$this->londonStreetQueries());
        } elseif ($this->houseNumber && $this->street) {
            $line = "{$this->houseNumber} {$this->street}";
            if ($this->country) {
                $queries[] = "{$line}, {$this->country}";
            }
            $queries[] = $line;
            array_push($queries, ...$this->londonStreetQueries());
        }

        if ($this->houseName && $this->street && $this->locality) {
            $queries[] = "{$this->houseName}, {$this->street}, {$this->locality}";
        } elseif ($this->houseName && $this->street) {
            $queries[] = "{$this->houseName}, {$this->street}";
        }

        if ($this->houseName && $this->locality) {
            $queries[] = "{$this->houseName}, {$this->locality}";
        } elseif ($this->houseName && $this->region) {
            $queries[] = "{$this->houseName}, {$this->region}";
        }

        if ($this->street && $this->locality && !$this->houseNumber) {
            $queries[] = "{$this->street}, {$this->locality}";
            array_push($queries, ...$this->londonStreetQueries());
        }

        if ($this->primaryName && $this->region && $this->country
            && $this->primaryName !== $this->region) {
            $queries[] = "{$this->primaryName}, {$this->region}, {$this->country}";
        }

        if ($this->primaryName && $this->country && $this->primaryName !== $this->country) {
            $queries[] = "{$this->primaryName}, {$this->country}";
        }

        if ($this->cleanedName !== '') {
            $queries[] = $this->cleanedName;
        }

        $unique = [];
        foreach ($queries as $query) {
            $query = trim(preg_replace('/\s+/', ' ', $query) ?? $query);
            $query = trim($query, " ,");
            if ($query === '') {
                continue;
            }
            $key = strtolower($query);
            if (!isset($unique[$key])) {
                $unique[$key] = $query;
            }
        }

        return array_values(array_slice($unique, 0, 6));
    }

    /**
     * Neighbourhood names such as Anerley are often absent from the OSM address,
     * while the same house is found as "{number} {street}, London".
     *
     * @return list<string>
     */
    private function londonStreetQueries(): array
    {
        if (!$this->looksLikeLondon() || $this->street === null || $this->street === '') {
            return [];
        }

        $line = trim(($this->houseNumber ? $this->houseNumber.' ' : '').$this->street);
        $queries = ["{$line}, London"];
        if ($this->locality && strtolower($this->locality) !== 'london') {
            array_unshift($queries, "{$line}, {$this->locality}, London");
        }

        return $queries;
    }

    /**
     * Structured Nominatim search parameters when we have enough parts.
     *
     * @return array<string, string>
     */
    public function structuredParams(): array
    {
        $params = [];

        if ($this->houseNumber && $this->street) {
            $params['street'] = "{$this->houseNumber} {$this->street}";
        } elseif ($this->street) {
            $params['street'] = $this->street;
        }

        if ($this->locality) {
            $params['city'] = $this->locality;
        } elseif (!$this->looksLikeAddress() && $this->primaryName !== '') {
            $params['city'] = $this->primaryName;
        }

        if ($this->region) {
            $ukNations = ['England', 'Scotland', 'Wales', 'Northern Ireland'];
            if (in_array($this->region, $ukNations, true) || $this->countryCode === 'us') {
                $params['state'] = $this->region;
            } else {
                $params['county'] = $this->region;
            }
        }

        if ($this->country) {
            $params['country'] = $this->country;
        }

        if ($this->postalCode) {
            $params['postalcode'] = $this->postalCode;
        }

        if ($this->countryCode) {
            $params['countrycodes'] = $this->countryCode;
        }

        $meaningful = array_intersect_key($params, array_flip(['street', 'city', 'country']));
        if (count($meaningful) < 2 && !isset($params['street'])) {
            return [];
        }

        return $params;
    }
}
