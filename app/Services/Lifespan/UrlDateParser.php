<?php

namespace App\Services\Lifespan;

class UrlDateParser
{
    public function parseAnchor(string $value): ?array
    {
        if (!preg_match('/^-?\d+(?:-\d{2})?(?:-\d{2})?$/', $value)) {
            return null;
        }

        $parts = explode('-', ltrim($value, '-'));
        $negative = str_starts_with($value, '-');
        $yearRaw = $parts[0] ?? null;
        if ($yearRaw === null || $yearRaw === '') {
            return null;
        }

        $year = (int) $yearRaw;
        if ($negative) {
            $year *= -1;
        }

        $month = isset($parts[1]) ? (int) $parts[1] : 1;
        $day = isset($parts[2]) ? (int) $parts[2] : 1;

        if ($month < 1 || $month > 12) {
            return null;
        }

        $daysInMonth = $this->daysInMonth($year, $month);
        if ($day < 1 || $day > $daysInMonth) {
            return null;
        }

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'iso' => sprintf('%d-%02d-%02d', $year, $month, $day),
        ];
    }

    private function daysInMonth(int $year, int $month): int
    {
        if (in_array($month, [1, 3, 5, 7, 8, 10, 12], true)) {
            return 31;
        }

        if (in_array($month, [4, 6, 9, 11], true)) {
            return 30;
        }

        return $this->isLeapYear($year) ? 29 : 28;
    }

    private function isLeapYear(int $year): bool
    {
        if ($year % 400 === 0) {
            return true;
        }
        if ($year % 100 === 0) {
            return false;
        }

        return $year % 4 === 0;
    }
}
