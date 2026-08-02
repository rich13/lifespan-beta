<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetObservation;
use App\Models\DatasetSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DatasetExplorerController extends Controller
{
    public function index(): View
    {
        $datasets = Dataset::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'value_label', 'unit', 'updated_at']);

        $defaultSeriesByDatasetId = DatasetSeries::query()
            ->whereIn('dataset_id', $datasets->pluck('id'))
            ->orderBy('series_key')
            ->get(['dataset_id', 'series_key'])
            ->groupBy('dataset_id')
            ->map(static fn (Collection $rows) => (string) $rows->first()->series_key);

        $datasets->each(function (Dataset $dataset) use ($defaultSeriesByDatasetId): void {
            $dataset->setAttribute('default_series_key', $defaultSeriesByDatasetId->get($dataset->id, ''));
        });

        $minT = DatasetObservation::query()->min('t_start');
        $chartYearMin = $minT !== null ? (int) floor((float) $minT) : 1900;
        $chartYearMax = (int) now()->year;
        if ($chartYearMin > $chartYearMax) {
            $chartYearMax = $chartYearMin;
        }

        return view('datasets.index', compact('datasets', 'chartYearMin', 'chartYearMax'));
    }

    public function show(Dataset $dataset): View
    {
        $defaultSeries = $dataset->series()->orderBy('series_key')->value('series_key') ?? '';
        $chartYearMin = $this->earliestObservationYearForDataset($dataset, $defaultSeries);
        $calendarYear = (int) now()->year;
        $chartYearMax = $calendarYear;
        if ($chartYearMin > $chartYearMax) {
            $chartYearMax = $chartYearMin;
        }

        return view('datasets.show', compact('dataset', 'defaultSeries', 'chartYearMin', 'chartYearMax'));
    }

    /**
     * Calendar year of the earliest observation for the default series, else any series on this dataset.
     */
    private function earliestObservationYearForDataset(Dataset $dataset, string $defaultSeriesKey): int
    {
        $minT = null;
        if ($defaultSeriesKey !== '') {
            $seriesId = DatasetSeries::query()
                ->where('dataset_id', $dataset->id)
                ->where('series_key', $defaultSeriesKey)
                ->value('id');
            if ($seriesId) {
                $minT = DatasetObservation::query()->where('series_id', $seriesId)->min('t_start');
            }
        }
        if ($minT === null) {
            $minT = DatasetObservation::query()
                ->whereHas('series', static function ($q) use ($dataset): void {
                    $q->where('dataset_id', $dataset->id);
                })
                ->min('t_start');
        }

        return $minT !== null ? (int) floor((float) $minT) : 1900;
    }

    public function points(Request $request, Dataset $dataset): JsonResponse
    {
        $seriesKey = (string) $request->query('series_key', '');
        if ($seriesKey === '') {
            return response()->json(['message' => 'series_key is required.'], 422);
        }

        $series = DatasetSeries::query()
            ->where('dataset_id', $dataset->id)
            ->where('series_key', $seriesKey)
            ->first();

        if (!$series) {
            return response()->json(['message' => 'Unknown series_key for this dataset.'], 404);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        $points = $this->mappedObservationPoints($series, $from, $to);

        return response()->json([
            'dataset' => [
                'slug' => $dataset->slug,
                'name' => $dataset->name,
                'value_label' => $dataset->value_label,
                'unit' => $dataset->unit,
                'attribution' => $dataset->attribution,
                'source_url' => $dataset->source_url,
            ],
            'series' => [
                'series_key' => $series->series_key,
                'label' => $series->label,
            ],
            'points' => $points->values(),
        ]);
    }

    /**
     * Multiple datasets on one time axis, same series_key per dataset (e.g. OWID_WRL).
     * Query: datasets=comma-separated-slugs, series_key, optional from, to (years).
     */
    public function combinedPoints(Request $request): JsonResponse
    {
        $raw = (string) $request->query('datasets', '');
        $slugs = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $slugs = array_unique($slugs);
        $slugs = array_slice($slugs, 0, 15);

        if ($slugs === []) {
            return response()->json([
                'message' => 'Pass at least one dataset slug in the datasets query parameter (comma-separated).',
            ], 422);
        }

        $seriesKey = trim((string) $request->query('series_key', 'OWID_WRL'));
        if ($seriesKey === '') {
            return response()->json(['message' => 'series_key cannot be empty.'], 422);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        $datasets = Dataset::query()->whereIn('slug', $slugs)->get()->keyBy('slug');

        $lines = [];
        foreach ($slugs as $slug) {
            $dataset = $datasets->get($slug);
            if (!$dataset) {
                $lines[] = [
                    'slug' => $slug,
                    'missing_dataset' => true,
                    'missing_series' => false,
                    'name' => null,
                    'value_label' => null,
                    'unit' => null,
                    'series' => null,
                    'points' => [],
                ];

                continue;
            }

            $series = DatasetSeries::query()
                ->where('dataset_id', $dataset->id)
                ->where('series_key', $seriesKey)
                ->first();

            if (!$series) {
                $lines[] = [
                    'slug' => $slug,
                    'missing_dataset' => false,
                    'missing_series' => true,
                    'name' => $dataset->name,
                    'value_label' => $dataset->value_label,
                    'unit' => $dataset->unit,
                    'series' => null,
                    'points' => [],
                ];

                continue;
            }

            $lines[] = [
                'slug' => $slug,
                'missing_dataset' => false,
                'missing_series' => false,
                'name' => $dataset->name,
                'value_label' => $dataset->value_label,
                'unit' => $dataset->unit,
                'series' => [
                    'series_key' => $series->series_key,
                    'label' => $series->label,
                ],
                'points' => $this->mappedObservationPoints($series, $from, $to)->values(),
            ];
        }

        return response()->json([
            'series_key' => $seriesKey,
            'lines' => $lines,
        ]);
    }

    /**
     * @return Collection<int, array{t_start: float, t_end: float, t_mid: float, value: float}>
     */
    private function mappedObservationPoints(DatasetSeries $series, mixed $from, mixed $to): Collection
    {
        $query = $series->observations()->orderBy('t_start');
        if ($from !== null && $from !== '' && is_numeric($from)) {
            $query->where('t_start', '>=', (float) $from);
        }
        if ($to !== null && $to !== '' && is_numeric($to)) {
            $query->where('t_start', '<=', (float) $to);
        }

        return $query->get(['t_start', 't_end', 'value'])->map(function ($row) {
            return [
                't_start' => (float) $row->t_start,
                't_end' => (float) $row->t_end,
                't_mid' => ((float) $row->t_start + (float) $row->t_end) / 2.0,
                'value' => (float) $row->value,
            ];
        });
    }
}
