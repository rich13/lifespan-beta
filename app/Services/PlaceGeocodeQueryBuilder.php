<?php

namespace App\Services;

use App\Models\Span;

/**
 * Turns a messy place span name (and optional metadata) into structured
 * Nominatim search hints so auto/bulk geocoding has a better chance.
 */
class PlaceGeocodeQueryBuilder
{
    private const CONTINENTS = [
        'africa', 'antarctica', 'asia', 'europe', 'north america',
        'south america', 'australia', 'oceania', 'indian ocean',
        'atlantic ocean', 'pacific ocean', 'arctic ocean',
    ];

    private const COUNTRY_ALIASES = [
        'uk' => ['United Kingdom', 'gb'],
        'u.k.' => ['United Kingdom', 'gb'],
        'u.k' => ['United Kingdom', 'gb'],
        'gb' => ['United Kingdom', 'gb'],
        'great britain' => ['United Kingdom', 'gb'],
        'britain' => ['United Kingdom', 'gb'],
        'united kingdom' => ['United Kingdom', 'gb'],
        'england' => ['United Kingdom', 'gb'],
        'scotland' => ['United Kingdom', 'gb'],
        'wales' => ['United Kingdom', 'gb'],
        'northern ireland' => ['United Kingdom', 'gb'],
        'usa' => ['United States', 'us'],
        'us' => ['United States', 'us'],
        'u.s.' => ['United States', 'us'],
        'u.s.a.' => ['United States', 'us'],
        'u.s.a' => ['United States', 'us'],
        'america' => ['United States', 'us'],
        'united states' => ['United States', 'us'],
        'united states of america' => ['United States', 'us'],
        'ireland' => ['Ireland', 'ie'],
        'eire' => ['Ireland', 'ie'],
        'republic of ireland' => ['Ireland', 'ie'],
        'france' => ['France', 'fr'],
        'germany' => ['Germany', 'de'],
        'italy' => ['Italy', 'it'],
        'spain' => ['Spain', 'es'],
        'netherlands' => ['Netherlands', 'nl'],
        'holland' => ['Netherlands', 'nl'],
        'belgium' => ['Belgium', 'be'],
        'switzerland' => ['Switzerland', 'ch'],
        'austria' => ['Austria', 'at'],
        'portugal' => ['Portugal', 'pt'],
        'denmark' => ['Denmark', 'dk'],
        'sweden' => ['Sweden', 'se'],
        'norway' => ['Norway', 'no'],
        'finland' => ['Finland', 'fi'],
        'poland' => ['Poland', 'pl'],
        'canada' => ['Canada', 'ca'],
        'australia' => ['Australia', 'au'],
        'new zealand' => ['New Zealand', 'nz'],
        'japan' => ['Japan', 'jp'],
        'china' => ['China', 'cn'],
        'india' => ['India', 'in'],
        'egypt' => ['Egypt', 'eg'],
        'south africa' => ['South Africa', 'za'],
        'brazil' => ['Brazil', 'br'],
        'mexico' => ['Mexico', 'mx'],
        'russia' => ['Russia', 'ru'],
        'greece' => ['Greece', 'gr'],
        'turkey' => ['Turkey', 'tr'],
        'czech republic' => ['Czechia', 'cz'],
        'czechia' => ['Czechia', 'cz'],
    ];

    private const UK_NATIONS = [
        'england' => 'England',
        'scotland' => 'Scotland',
        'wales' => 'Wales',
        'northern ireland' => 'Northern Ireland',
    ];

    private const US_STATES = [
        'alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado',
        'connecticut', 'delaware', 'florida', 'georgia', 'hawaii', 'idaho',
        'illinois', 'indiana', 'iowa', 'kansas', 'kentucky', 'louisiana',
        'maine', 'maryland', 'massachusetts', 'michigan', 'minnesota',
        'mississippi', 'missouri', 'montana', 'nebraska', 'nevada',
        'new hampshire', 'new jersey', 'new mexico', 'new york',
        'north carolina', 'north dakota', 'ohio', 'oklahoma', 'oregon',
        'pennsylvania', 'rhode island', 'south carolina', 'south dakota',
        'tennessee', 'texas', 'utah', 'vermont', 'virginia', 'washington',
        'west virginia', 'wisconsin', 'wyoming', 'district of columbia',
    ];

    public function fromSpan(Span $span, ?string $overrideName = null): PlaceGeocodeQuery
    {
        $coordinates = $span->getCoordinates();

        return $this->fromName(
            $overrideName ?? (string) $span->name,
            $coordinates['latitude'] ?? null,
            $coordinates['longitude'] ?? null,
            $span->metadata['subtype'] ?? $span->subtype ?? null,
            $span->metadata['country'] ?? null,
        );
    }

    public function fromName(
        string $name,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $subtype = null,
        ?string $countryHint = null,
    ): PlaceGeocodeQuery {
        $original = trim($name);
        [$houseName, $remainder] = $this->extractHouseName($original);
        $normalised = $this->stripNarrative($remainder);
        $normalised = trim(preg_replace('/\s+/', ' ', $normalised) ?? $normalised, " ,");

        if ($this->isContinent($normalised) || $this->isContinent($original)) {
            return new PlaceGeocodeQuery(
                originalName: $original,
                cleanedName: $normalised,
                primaryName: $normalised,
                subtype: $subtype,
                latitude: $latitude,
                longitude: $longitude,
                shouldSkip: true,
                skipReason: 'Continents and oceans are too broad to geocode usefully',
            );
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $normalised)), fn ($part) => $part !== ''));

        $country = $countryHint ? ($this->matchCountry($countryHint)[0] ?? $countryHint) : null;
        $countryCode = $countryHint ? ($this->matchCountry($countryHint)[1] ?? null) : null;
        $region = null;
        $locality = null;
        $postalCode = null;
        $outwardCode = null;
        $leftovers = [];

        foreach ($parts as $part) {
            [$partPostal, $partOutward, $remainder] = $this->extractPostcodeFromPart($part);
            if ($partPostal && !$postalCode) {
                $postalCode = $partPostal;
            }
            if ($partOutward && !$outwardCode) {
                $outwardCode = $partOutward;
            }
            $part = $remainder;

            if ($part === '') {
                continue;
            }

            $nation = $this->matchUkNation($part);
            if ($nation !== null) {
                $region = $region ?? $nation;
                $country = $country ?? 'United Kingdom';
                $countryCode = $countryCode ?? 'gb';
                continue;
            }

            $matchedCountry = $this->matchCountry($part);
            if ($matchedCountry !== null) {
                $country = $country ?? $matchedCountry[0];
                $countryCode = $countryCode ?? $matchedCountry[1];
                if (isset(self::UK_NATIONS[strtolower($part)]) && $region === null) {
                    $region = self::UK_NATIONS[strtolower($part)];
                }
                continue;
            }

            $state = $this->matchUsState($part);
            if ($state !== null) {
                $region = $region ?? $state;
                $country = $country ?? 'United States';
                $countryCode = $countryCode ?? 'us';
                continue;
            }

            $leftovers[] = $part;
        }

        if ($outwardCode && !$country) {
            $country = 'United Kingdom';
            $countryCode = 'gb';
        }

        $houseNumber = null;
        $street = null;
        $containment = [];

        if (isset($leftovers[0], $leftovers[1])
            && preg_match('/^\d+[A-Za-z]?$/', $leftovers[0])
            && $this->looksLikeStreetName($leftovers[1])) {
            $houseNumber = $leftovers[0];
            $street = $leftovers[1];
            $primary = "{$houseNumber} {$street}";
            $containment = array_slice($leftovers, 2);
        } else {
            $primary = $leftovers[0] ?? ($houseName ?? $normalised);
            [$houseNumber, $street] = $this->parseStreetLine($primary);
            if ($street !== null) {
                $containment = array_slice($leftovers, 1);
            } elseif (isset($leftovers[1])) {
                [$houseNumberFromNext, $streetFromNext] = $this->parseStreetLine($leftovers[1]);
                if ($streetFromNext !== null) {
                    $houseNumber = $houseNumberFromNext;
                    $street = $streetFromNext;
                    $primary = $leftovers[1];
                    $containment = array_slice($leftovers, 2);
                } else {
                    $containment = array_slice($leftovers, 1);
                }
            }
        }

        if ($locality === null && isset($containment[0])) {
            $locality = $containment[0];
        }
        if ($region === null && isset($containment[1])) {
            $region = $containment[1];
        }

        $cleanedParts = array_filter([
            $houseName,
            $houseNumber && $street ? "{$houseNumber} {$street}" : $primary,
            $locality,
            $region,
            $country,
        ], fn ($value) => $value !== null && $value !== '');
        $cleanedName = implode(', ', array_unique($cleanedParts));

        return new PlaceGeocodeQuery(
            originalName: $original,
            cleanedName: $cleanedName,
            primaryName: $primary,
            houseName: $houseName,
            houseNumber: $houseNumber,
            street: $street,
            locality: $locality,
            region: $region,
            country: $country,
            countryCode: $countryCode,
            postalCode: $postalCode,
            outwardCode: $outwardCode,
            subtype: $subtype,
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    private function stripNarrative(string $name): string
    {
        $name = preg_replace('/\bpartly on the site of\b/i', '', $name) ?? $name;
        $name = preg_replace('/\bon the site of\b/i', '', $name) ?? $name;
        $name = preg_replace('/\bformerly\b/i', '', $name) ?? $name;
        $name = preg_replace('/\s+\/\s+/', ', ', $name) ?? $name;

        return trim($name, " ,;:-");
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function extractHouseName(string $name): array
    {
        if (preg_match('/^"([^"]+)"\s*(?:\([^)]+\))?\s*,?\s*(.*)$/s', $name, $matches)
            || preg_match("/^'([^']+)'\s*(?:\([^)]+\))?\s*,?\s*(.*)$/s", $name, $matches)) {
            $houseName = trim($matches[1]);
            $rest = trim($matches[2], " ,");

            return [$houseName !== '' ? $houseName : null, $rest !== '' ? $rest : $houseName];
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*,?\s*(.*)$/s', $name, $matches)) {
            $before = trim($matches[1], " ,'\"");
            $inside = trim($matches[2]);
            $after = trim($matches[3], " ,");
            if ($this->parseStreetLine($inside)[1] !== null) {
                $rest = trim(implode(', ', array_filter([$inside, $after])), ' ,');

                return [$before !== '' ? $before : null, $rest];
            }
        }

        return [null, $name];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string} postal, outward, remainder
     */
    private function extractPostcodeFromPart(string $part): array
    {
        $postal = null;
        $outward = null;

        if (preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?)\s*(\d[A-Z]{2})\b/i', $part, $matches)) {
            $postal = strtoupper($matches[1] . ' ' . $matches[2]);
            $outward = strtoupper($matches[1]);
            $part = trim(str_replace($matches[0], '', $part), " ,");

            return [$postal, $outward, $part];
        }

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)\s+(.+)$/i', $part, $matches)
            && $this->looksLikeUkOutward($matches[1])) {
            $outward = strtoupper($matches[1]);
            $part = trim($matches[2]);

            return [null, $outward, $part];
        }

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)$/i', $part, $matches)
            && $this->looksLikeUkOutward($matches[1])) {
            return [null, strtoupper($matches[1]), ''];
        }

        if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $part, $matches)) {
            $postal = $matches[1];
            $part = trim(str_replace($matches[0], '', $part), " ,");

            return [$postal, null, $part];
        }

        return [null, null, $part];
    }

    private function looksLikeUkOutward(string $code): bool
    {
        return (bool) preg_match('/^[A-Z]{1,2}\d[A-Z\d]?$/i', $code)
            && !in_array(strtolower($code), ['uk', 'us', 'gb'], true);
    }

    /**
     * @return array{0: ?string, 1: ?string} house number, street
     */
    private function parseStreetLine(string $line): array
    {
        $line = trim($line);
        if (preg_match('/^(\d+[A-Za-z]?)\s+(.+)$/', $line, $matches)
            && $this->looksLikeStreetName($matches[2])) {
            return [$matches[1], $matches[2]];
        }

        if (preg_match('/^(\d+[A-Za-z]?)\s*,\s*(.+)$/', $line, $matches)
            && $this->looksLikeStreetName($matches[2])) {
            return [$matches[1], $matches[2]];
        }

        if ($this->looksLikeStreetName($line) && preg_match('/\b(road|street|lane|grove|place|square|terrace|avenue|gardens|walk|hill|way|row|close|court|crescent|parade|mews|gate)\b/i', $line)) {
            return [null, $line];
        }

        return [null, null];
    }

    private function looksLikeStreetName(string $line): bool
    {
        if (strlen($line) > 80) {
            return false;
        }

        return (bool) preg_match('/\b(road|street|st|lane|grove|place|square|terrace|avenue|ave|gardens|walk|hill|way|row|close|court|crescent|parade|mews|gate|villas|cottages|buildings|yard|wharf|embankment|bridge)\b/i', $line);
    }

    private function isContinent(string $name): bool
    {
        return in_array(strtolower(trim($name)), self::CONTINENTS, true);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function matchCountry(string $part): ?array
    {
        $key = strtolower(trim($part, ' .'));

        return self::COUNTRY_ALIASES[$key] ?? null;
    }

    private function matchUkNation(string $part): ?string
    {
        $key = strtolower(trim($part));
        // "England" is both a nation and a country alias; treat as nation so we keep it as region.
        return self::UK_NATIONS[$key] ?? null;
    }

    private function matchUsState(string $part): ?string
    {
        $key = strtolower(trim($part));
        if (!in_array($key, self::US_STATES, true)) {
            return null;
        }

        return mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');
    }
}
