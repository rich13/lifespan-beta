<?php

namespace App\Services\Datasets;

use App\Models\Dataset;
use App\Models\DatasetObservation;
use App\Models\DatasetSeries;
use InvalidArgumentException;

class OwidGrapherCsvImporter
{
    /**
     * Import OWID grapher-style CSV: Entity, Code, Year, plus one value column.
     * Maps each calendar year Y to the global interval [Y, Y+1) on the decimal-year axis.
     *
     * @return array{series_count: int, observation_count: int}
     */
    public function import(Dataset $dataset, string $absolutePath): array
    {
        if (!is_readable($absolutePath)) {
            throw new InvalidArgumentException('CSV file is not readable.');
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException('Could not open CSV file.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || count($header) < 4) {
                throw new InvalidArgumentException('CSV must have a header row with at least four columns (Entity, Code, Year, value).');
            }

            $header = array_map(fn ($h) => is_string($h) ? trim($h) : '', $header);
            $lower = array_map('strtolower', $header);

            $entityIdx = array_search('entity', $lower, true);
            $codeIdx = array_search('code', $lower, true);
            $yearIdx = array_search('year', $lower, true);

            if ($entityIdx === false || $codeIdx === false || $yearIdx === false) {
                throw new InvalidArgumentException('CSV must include Entity, Code, and Year columns (OWID grapher format).');
            }

            $valueIdx = null;
            foreach ($header as $i => $_) {
                if ($i !== $entityIdx && $i !== $codeIdx && $i !== $yearIdx) {
                    $valueIdx = $i;
                    break;
                }
            }

            if ($valueIdx === null) {
                throw new InvalidArgumentException('CSV must include a value column after Year.');
            }

            $seriesIds = [];
            $observationBuffer = [];
            $observationCount = 0;
            $batchSize = 750;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < max($entityIdx, $codeIdx, $yearIdx, $valueIdx) + 1) {
                    continue;
                }

                $entity = trim((string) ($row[$entityIdx] ?? ''));
                $code = trim((string) ($row[$codeIdx] ?? ''));
                $yearRaw = trim((string) ($row[$yearIdx] ?? ''));
                $valueRaw = trim((string) ($row[$valueIdx] ?? ''));

                if ($entity === '' || $code === '' || $yearRaw === '' || $valueRaw === '') {
                    continue;
                }

                if (!preg_match('/^-?\d+$/', $yearRaw)) {
                    continue;
                }

                $year = (int) $yearRaw;
                if (!is_numeric($valueRaw)) {
                    continue;
                }

                $value = (float) $valueRaw;

                if (!isset($seriesIds[$code])) {
                    $series = DatasetSeries::query()->firstOrCreate(
                        [
                            'dataset_id' => $dataset->id,
                            'series_key' => $code,
                        ],
                        [
                            'label' => $entity !== '' ? $entity : $code,
                            'metadata' => null,
                        ]
                    );
                    $seriesIds[$code] = $series->id;
                }

                $seriesId = $seriesIds[$code];
                $tStart = (float) $year;
                $tEnd = (float) ($year + 1);

                $observationBuffer[] = [
                    'series_id' => $seriesId,
                    't_start' => $tStart,
                    't_end' => $tEnd,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($observationBuffer) >= $batchSize) {
                    $this->flushObservations($observationBuffer);
                    $observationCount += count($observationBuffer);
                    $observationBuffer = [];
                }
            }

            if ($observationBuffer !== []) {
                $this->flushObservations($observationBuffer);
                $observationCount += count($observationBuffer);
            }

            if ($observationCount === 0) {
                throw new InvalidArgumentException('No observations were imported. Check the file format and data rows.');
            }

            return [
                'series_count' => count($seriesIds),
                'observation_count' => $observationCount,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function flushObservations(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DatasetObservation::query()->upsert(
            $rows,
            ['series_id', 't_start'],
            ['t_end', 'value', 'updated_at']
        );
    }
}
