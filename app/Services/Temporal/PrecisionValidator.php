<?php

namespace App\Services\Temporal;

class PrecisionValidator
{
    /**
     * Validate that a precision transition is valid
     */
    public function validatePrecisionTransition(
        ?string $fromPrecision,
        string $toPrecision,
        bool $isEndDate = false
    ): bool {
        // If there's no previous precision, any precision is valid
        if ($fromPrecision === null) {
            return true;
        }

        // Get the precision levels in order of specificity
        $precisionLevels = [
            TemporalPoint::PRECISION_YEAR,
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_DAY,
        ];

        $fromLevel = array_search($fromPrecision, $precisionLevels);
        $toLevel = array_search($toPrecision, $precisionLevels);

        if ($fromLevel === false || $toLevel === false) {
            throw new \InvalidArgumentException('Invalid precision level');
        }

        // For end dates, we can't increase precision beyond the start date's precision
        if ($isEndDate && $toLevel > $fromLevel) {
            return false;
        }

        return true;
    }

    /**
     * Validate that a span's precision combination is valid
     */
    public function validateSpanPrecisions(
        string $startPrecision,
        ?string $endPrecision
    ): bool {
        // If there's no end date, any start precision is valid
        if ($endPrecision === null) {
            return true;
        }

        // Get precision levels
        $precisionLevels = [
            TemporalPoint::PRECISION_YEAR,
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_DAY,
        ];

        // If start precision is invalid, consider it as the most specific level
        $startLevel = array_search($startPrecision, $precisionLevels);
        if ($startLevel === false) {
            $startLevel = count($precisionLevels) - 1;
        }

        // If end precision is invalid, consider it as the most specific level
        $endLevel = array_search($endPrecision, $precisionLevels);
        if ($endLevel === false) {
            $endLevel = count($precisionLevels) - 1;
        }

        // End date precision cannot be more specific than start date precision
        return $endLevel <= $startLevel;
    }

    /**
     * Get the common precision between two precisions
     */
    public function getCommonPrecision(string $precision1, string $precision2): string
    {
        $precisionLevels = [
            TemporalPoint::PRECISION_YEAR,
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_DAY,
        ];

        $level1 = array_search($precision1, $precisionLevels);
        $level2 = array_search($precision2, $precisionLevels);

        if ($level1 === false || $level2 === false) {
            throw new \InvalidArgumentException('Invalid precision level');
        }

        // Use the less specific precision
        return $precisionLevels[min($level1, $level2)];
    }
} 