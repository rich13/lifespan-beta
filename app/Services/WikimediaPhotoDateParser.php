<?php

namespace App\Services;

/**
 * Resolve a photograph date from Wikimedia Commons metadata.
 *
 * Prefer explicit taken-date sources (Taken on templates, photograph-year
 * categories, a calendar Date field) over filenames. Commons |Date= and SDC
 * P571 often repeat a Flickr/upload timestamp; those are treated as untrusted.
 */
class WikimediaPhotoDateParser
{
    /**
     * @param  array{
     *     date?: string,
     *     title?: string,
     *     description?: string,
     *     uploaded_at?: string,
     *     categories?: list<string>,
     *     taken_on?: string
     * }  $sources
     * @return array{year: int|null, month: int|null, day: int|null}
     */
    public function resolveFromSources(array $sources): array
    {
        $dateString = (string) ($sources['date'] ?? '');
        $title = (string) ($sources['title'] ?? '');
        $description = (string) ($sources['description'] ?? '');
        $uploadedAt = (string) ($sources['uploaded_at'] ?? '');
        $categories = $sources['categories'] ?? [];
        $takenOn = (string) ($sources['taken_on'] ?? '');

        if ($takenOn !== '') {
            $fromTemplate = $this->parseDateString($takenOn);
            if ($fromTemplate['year'] !== null) {
                return $fromTemplate;
            }
        }

        $parsed = $this->parseDateString($dateString);
        if ($parsed['year'] !== null && ! $this->looksLikeUploadDate($dateString, $parsed, $uploadedAt, $title, $description)) {
            return $parsed;
        }

        $categoryYear = $this->yearFromPhotographCategories(is_array($categories) ? $categories : []);
        if ($categoryYear !== null) {
            return [
                'year' => $categoryYear,
                'month' => null,
                'day' => null,
            ];
        }

        $titleYears = $this->extractYears($title);
        if (count($titleYears) === 1) {
            return [
                'year' => $titleYears[0],
                'month' => null,
                'day' => null,
            ];
        }

        $descriptionYears = $this->extractYears($description);
        if (count($descriptionYears) === 1) {
            return [
                'year' => $descriptionYears[0],
                'month' => null,
                'day' => null,
            ];
        }

        return $parsed;
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null}
     */
    public function resolve(string $dateString, string $title = '', string $description = '', string $uploadedAt = ''): array
    {
        return $this->resolveFromSources([
            'date' => $dateString,
            'title' => $title,
            'description' => $description,
            'uploaded_at' => $uploadedAt,
        ]);
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null}
     */
    public function parseDateString(string $dateString): array
    {
        $date = [
            'year' => null,
            'month' => null,
            'day' => null,
        ];

        if ($dateString === '') {
            return $date;
        }

        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString, $matches)) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                $date['year'] = (int) $matches[1];
                $date['month'] = $month;
                $date['day'] = $day;

                return $date;
            }
        }

        if (preg_match('/\b((?:18|19|20)\d{2})\b/', $dateString, $matches)) {
            $date['year'] = (int) $matches[1];
        }

        return $date;
    }

    /**
     * @return list<int>
     */
    public function extractYears(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/\b((?:18|19|20)\d{2})\b/', $text, $matches);

        $currentYear = (int) date('Y');
        $years = [];
        foreach ($matches[1] as $year) {
            $year = (int) $year;
            if ($year >= 1800 && $year <= $currentYear) {
                $years[] = $year;
            }
        }

        return array_values(array_unique($years));
    }

    /**
     * @param  list<string>  $categories
     */
    public function yearFromPhotographCategories(array $categories): ?int
    {
        foreach ($categories as $category) {
            $category = trim((string) $category);
            if (preg_match('/^((?:18|19|20)\d{2})(?: portrait)? photographs$/i', $category, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/^Photographs taken in ((?:18|19|20)\d{2})$/i', $category, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  array{year: int|null, month: int|null, day: int|null}  $parsed
     */
    protected function looksLikeUploadDate(string $dateString, array $parsed, string $uploadedAt, string $title, string $description = ''): bool
    {
        $hintYears = $this->extractYears($title);
        if (count($hintYears) !== 1) {
            $hintYears = $this->extractYears($description);
        }

        $hasConflictingHint = count($hintYears) === 1
            && $parsed['year'] !== null
            && abs($hintYears[0] - $parsed['year']) > 1;

        if ($this->looksLikeTimestamp($dateString)) {
            return $hasConflictingHint;
        }

        if (! $hasConflictingHint || $parsed['month'] === null || $parsed['day'] === null) {
            return false;
        }

        if ($uploadedAt === '') {
            return true;
        }

        $upload = $this->parseDateString($uploadedAt);
        if ($upload['year'] === null || $upload['month'] === null || $upload['day'] === null) {
            return true;
        }

        try {
            $stated = new \DateTime(sprintf('%04d-%02d-%02d', $parsed['year'], $parsed['month'], $parsed['day']));
            $uploaded = new \DateTime(sprintf('%04d-%02d-%02d', $upload['year'], $upload['month'], $upload['day']));

            return abs((int) $stated->diff($uploaded)->days) <= 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function looksLikeTimestamp(string $dateString): bool
    {
        return (bool) preg_match('/\d{1,2}:\d{2}/', $dateString);
    }
}
