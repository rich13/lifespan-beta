<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Log;

class WikipediaImportService
{
    public function __construct(
        private readonly WikimediaService $wikimediaService
    ) {}

    /**
     * Process a single public figure span: fetch Wikipedia data and update the span.
     * Returns ['success' => bool, 'message' => string, 'data' => array?].
     */
    public function processSpan(Span $span): array
    {
        if ($span->type_id !== 'person' ||
            !isset($span->metadata['subtype']) ||
            $span->metadata['subtype'] !== 'public_figure') {
            return ['success' => false, 'message' => 'This span is not a public figure.'];
        }

        try {
            $result = $this->wikimediaService->getDescriptionForSpan($span);

            if (!$result) {
                return ['success' => false, 'message' => 'No suitable description found on Wikipedia for this person.'];
            }

            $description = $result['description'];
            $wikipediaUrl = $result['wikipedia_url'] ?? null;
            $dates = $result['dates'] ?? null;

            $updateData = ['description' => $description];
            $startDateImproved = false;
            $endDateImproved = false;
            $hadNoStart = !$span->start_year;
            $hadNoEnd = !$span->end_year;

            if ($dates) {
                if ($dates['start_year']) {
                    if (!$span->start_year) {
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                        $startDateImproved = true;
                    } elseif ($this->shouldImproveDate($span, 'start', $dates)) {
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                        $startDateImproved = true;
                    }
                }

                if ($dates['end_year']) {
                    if (!$span->end_year) {
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                        $endDateImproved = true;
                    } elseif ($this->shouldImproveDate($span, 'end', $dates)) {
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                        $endDateImproved = true;
                    }
                }
            }

            $span->update($updateData);

            if ($wikipediaUrl) {
                $currentSources = $span->sources ?? [];
                $wikipediaUrlExists = false;
                foreach ($currentSources as $source) {
                    if (is_string($source) && str_contains($source, 'wikipedia.org')) {
                        $wikipediaUrlExists = true;
                        break;
                    }
                    if (is_array($source) && isset($source['url']) && str_contains($source['url'], 'wikipedia.org')) {
                        $wikipediaUrlExists = true;
                        break;
                    }
                }
                if (!$wikipediaUrlExists) {
                    $currentSources[] = [
                        'title' => 'Wikipedia',
                        'url' => $wikipediaUrl,
                        'type' => 'web',
                        'added_by' => 'wikipedia_bulk_import',
                    ];
                    $span->update(['sources' => $currentSources]);
                }
            } else {
                $currentNotes = $span->notes ?? '';
                $skipNote = "\n\n[Skipped Wikipedia import - no Wikipedia page found]";
                $span->update(['notes' => $currentNotes . $skipNote]);
            }

            if ($dates && (($dates['start_precision'] ?? '') === 'year' || ($dates['start_precision'] ?? '') === 'month' ||
                ($dates['end_precision'] ?? '') === 'year' || ($dates['end_precision'] ?? '') === 'month')) {
                $currentNotes = $span->notes ?? '';
                $dateNote = "\n\n[Wikipedia import complete - dates available with limited precision]";
                $span->update(['notes' => $currentNotes . $dateNote]);
            }

            $datesAdded = ($hadNoStart && ($dates['start_year'] ?? null)) || ($hadNoEnd && ($dates['end_year'] ?? null));

            Log::info('Wikipedia bulk import processed person', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'description_added' => !empty($description),
                'wikipedia_source_added' => !empty($wikipediaUrl),
                'dates_added' => $datesAdded,
                'dates_improved' => $startDateImproved || $endDateImproved,
            ]);

            return [
                'success' => true,
                'message' => 'Person processed successfully.',
                'data' => [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'description' => $description,
                    'wikipedia_url' => $wikipediaUrl,
                    'description_added' => !empty($description),
                    'wikipedia_source_added' => !empty($wikipediaUrl),
                    'dates_added' => $datesAdded,
                    'dates_improved' => $startDateImproved || $endDateImproved,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Wikipedia bulk import failed for person', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process person: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Skip a person (add note that they were not found).
     */
    public function skipSpan(Span $span): void
    {
        $currentNotes = $span->notes ?? '';
        $skipNote = "\n\n[Skipped Wikipedia import - not found on Wikipedia]";
        $span->update(['notes' => $currentNotes . $skipNote]);
    }

    private function shouldImproveDate(Span $span, string $dateType, array $newDates): bool
    {
        $prefix = $dateType === 'start' ? 'start' : 'end';
        $currentYear = $span->{$prefix . '_year'};
        $currentMonth = $span->{$prefix . '_month'};
        $currentDay = $span->{$prefix . '_day'};
        $currentPrecision = $span->{$prefix . '_precision'};

        $newYear = $newDates[$prefix . '_year'];
        $newMonth = $newDates[$prefix . '_month'];
        $newDay = $newDates[$prefix . '_day'];
        $newPrecision = $newDates[$prefix . '_precision'] ?? 'year';

        if ($currentYear !== $newYear) {
            return false;
        }

        $has01_01Problem = ($currentMonth === 1 && $currentDay === 1);
        $currentPrecisionLevel = $this->getPrecisionLevel($currentPrecision);
        $newPrecisionLevel = $this->getPrecisionLevel($newPrecision);

        return $has01_01Problem || $newPrecisionLevel > $currentPrecisionLevel;
    }

    private function getPrecisionLevel(?string $precision): int
    {
        return match ($precision) {
            'year' => 1,
            'month' => 2,
            'day' => 3,
            default => 0,
        };
    }
}
