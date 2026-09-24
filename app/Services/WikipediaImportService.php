<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            if ($this->shouldCorrectType($span, $result)) {
                return $this->importCorrectedType($span, $result);
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
            $span->refresh();
            $facts = $this->applyPersonFacts($span, $result);

            Log::info('Wikipedia bulk import processed person', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'description_added' => !empty($description),
                'wikipedia_source_added' => !empty($wikipediaUrl),
                'dates_added' => $datesAdded,
                'dates_improved' => $startDateImproved || $endDateImproved,
                'facts' => $facts,
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
                    'facts' => $facts,
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

    /**
     * Change the span type when Wikidata names this same item as something other than a person.
     *
     * @param  array<string, mixed>  $result
     */
    private function shouldCorrectType(Span $span, array $result): bool
    {
        $classification = $result['classification'] ?? null;
        if (! is_array($classification) || ($classification['status'] ?? '') !== 'matched') {
            return false;
        }

        $typeId = $classification['type_id'] ?? null;
        if (! is_string($typeId) || $typeId === '' || $typeId === $span->type_id) {
            return false;
        }

        return $this->wikimediaService->recordedNameMatches($span->name, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function importCorrectedType(Span $span, array $result): array
    {
        $classification = $result['classification'];
        $previousType = $span->type_id;
        $metadata = $span->metadata ?? [];
        $metadata['type_corrected_from'] = $previousType;
        $metadata['type_corrected_by'] = 'wikipedia';
        if (! empty($classification['via'])) {
            $metadata['wikidata_type'] = $classification['via'];
        }
        if (($metadata['subtype'] ?? null) === 'public_figure') {
            unset($metadata['subtype']);
        }
        if (! empty($classification['subtype'])) {
            $metadata['subtype'] = $classification['subtype'];
        }

        $dates = $result['dates'] ?? null;
        $updateData = [
            'type_id' => $classification['type_id'],
            'description' => $result['description'],
            'metadata' => $metadata,
        ];
        $startDateImproved = false;
        $endDateImproved = false;
        $hadNoStart = ! $span->start_year;
        $hadNoEnd = ! $span->end_year;

        if (is_array($dates)) {
            if (! empty($dates['start_year'])) {
                if (! $span->start_year || $this->shouldImproveDate($span, 'start', $dates)) {
                    $updateData['start_year'] = $dates['start_year'];
                    $updateData['start_month'] = $dates['start_month'];
                    $updateData['start_day'] = $dates['start_day'];
                    $updateData['start_precision'] = $dates['start_precision'];
                    $startDateImproved = (bool) $span->start_year;
                }
            }
            if (! empty($dates['end_year'])) {
                if (! $span->end_year || $this->shouldImproveDate($span, 'end', $dates)) {
                    $updateData['end_year'] = $dates['end_year'];
                    $updateData['end_month'] = $dates['end_month'];
                    $updateData['end_day'] = $dates['end_day'];
                    $updateData['end_precision'] = $dates['end_precision'];
                    $endDateImproved = (bool) $span->end_year;
                }
            }
        }

        $span->update($updateData);
        $this->rememberWikipediaSource($span, $result['wikipedia_url'] ?? null);

        $span->refresh();
        $facts = $this->applyPersonFacts($span, $result, false);
        $facts['type_corrected'] = true;
        $facts['type_id'] = $classification['type_id'];
        $facts['previous_type'] = $previousType;

        $datesAdded = ($hadNoStart && ($dates['start_year'] ?? null)) || ($hadNoEnd && ($dates['end_year'] ?? null));

        Log::info('Wikipedia import corrected span type', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'from' => $previousType,
            'to' => $classification['type_id'],
            'via' => $classification['via'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => "Corrected {$span->name} from {$previousType} to {$classification['type_id']}.",
            'data' => [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'description' => $result['description'],
                'wikipedia_url' => $result['wikipedia_url'] ?? null,
                'description_added' => ! empty($result['description']),
                'wikipedia_source_added' => ! empty($result['wikipedia_url']),
                'dates_added' => $datesAdded,
                'dates_improved' => $startDateImproved || $endDateImproved,
                'type_corrected' => true,
                'type_id' => $classification['type_id'],
                'previous_type' => $previousType,
                'facts' => $facts,
            ],
        ];
    }

    private function rememberWikipediaSource(Span $span, ?string $wikipediaUrl): void
    {
        if (! $wikipediaUrl) {
            return;
        }

        $currentSources = $span->sources ?? [];
        foreach ($currentSources as $source) {
            if (is_string($source) && str_contains($source, 'wikipedia.org')) {
                return;
            }
            if (is_array($source) && isset($source['url']) && str_contains($source['url'], 'wikipedia.org')) {
                return;
            }
        }

        $currentSources[] = [
            'title' => 'Wikipedia',
            'url' => $wikipediaUrl,
            'type' => 'web',
            'added_by' => 'wikipedia_bulk_import',
        ];
        $span->update(['sources' => $currentSources]);
    }

    /**
     * Fill empty person fields from the Wikipedia summary and Wikidata entity.
     * Does not replace a value that is already set.
     * Personal fields are omitted when the span has just been corrected away from a person.
     *
     * @return array<string, mixed>
     */
    private function applyPersonFacts(Span $span, array $result, bool $personalFields = true): array
    {
        $metadata = $span->metadata ?? [];
        $applied = [];

        if ($personalFields) {
            $birthName = $this->limitedText($result['birth_name'] ?? null);
            if ($birthName && empty($metadata['birth_name']) && strcasecmp($birthName, trim($span->name)) !== 0) {
                $metadata['birth_name'] = $birthName;
                $applied['birth_name'] = $birthName;
            }

            $occupation = $this->limitedText($result['occupation'] ?? null);
            if ($occupation && empty($metadata['occupation'])) {
                $metadata['occupation'] = $occupation;
                $applied['occupation'] = $occupation;
            }

            $nationality = $this->limitedText($result['nationality'] ?? null);
            if ($nationality && empty($metadata['nationality'])) {
                $metadata['nationality'] = $nationality;
                $applied['nationality'] = $nationality;
            }

            $gender = $result['gender'] ?? null;
            if (in_array($gender, ['male', 'female', 'other'], true) && empty($metadata['gender'])) {
                $metadata['gender'] = $gender;
                $applied['gender'] = $gender;
            }
        }

        $wikidataId = $result['wikidata_id'] ?? null;
        if (is_string($wikidataId) && $wikidataId !== '' && empty($metadata['wikidata_id'])) {
            $metadata['wikidata_id'] = $wikidataId;
            $applied['wikidata_id'] = $wikidataId;
        }

        $sources = $span->sources ?? [];
        $website = $result['official_website'] ?? null;
        if (is_string($website) && $website !== '' && ! $this->sourcesContainUrl($sources, $website)) {
            $sources[] = [
                'title' => 'Official website',
                'url' => $website,
                'type' => 'web',
                'added_by' => 'wikipedia_bulk_import',
            ];
            $applied['official_website'] = $website;
        }

        $span->update([
            'metadata' => $metadata,
            'sources' => $sources,
        ]);

        $image = $result['image'] ?? null;
        if (is_array($image) && ! empty($image['original_url'])) {
            $applied['image_added'] = $this->attachLeadImage($span, $image, $result['wikipedia_url'] ?? null);
        }

        return $applied;
    }

    /**
     * @param  array{original_url: string, thumbnail_url?: string}  $image
     */
    private function attachLeadImage(Span $span, array $image, ?string $wikipediaUrl): bool
    {
        if (! $span->owner_id || $this->hasFeaturedPhoto($span)) {
            return false;
        }

        $originalUrl = $image['original_url'];
        $thumbnailUrl = $image['thumbnail_url'] ?? $originalUrl;
        $photo = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->where('metadata->original_url', $originalUrl)
            ->first();

        if (! $photo) {
            $photoName = 'Photo of '.$span->name;
            $photo = app(ImprovementCreationPolicy::class)->createChild($span, [
                'name' => $photoName,
                'slug' => $this->uniqueSlug($photoName),
                'short_id' => Span::generateUniqueShortId(),
                'type_id' => 'thing',
                'description' => 'Lead image from the Wikipedia article on '.$span->name,
                'metadata' => [
                    'subtype' => 'photo',
                    'thumbnail_url' => $thumbnailUrl,
                    'medium_url' => $originalUrl,
                    'large_url' => $originalUrl,
                    'original_url' => $originalUrl,
                    'source' => 'Wikipedia',
                    'requires_attribution' => true,
                ],
                'sources' => $wikipediaUrl ? [[
                    'title' => 'Wikipedia',
                    'url' => $wikipediaUrl,
                    'type' => 'web',
                    'added_by' => 'wikipedia_bulk_import',
                ]] : [],
                'owner_id' => $span->owner_id,
                'updater_id' => $span->updater_id ?? $span->owner_id,
                'access_level' => 'public',
                'state' => 'placeholder',
            ]);
            if (! $photo) {
                return false;
            }
        }

        if ($this->photoFeaturesSpan($photo, $span)) {
            return false;
        }

        $connectionName = $photo->name.' features '.$span->name;
        $connectionSpan = app(ImprovementCreationPolicy::class)->createChild($span, [
            'name' => $connectionName,
            'slug' => $this->uniqueSlug($connectionName),
            'short_id' => Span::generateUniqueShortId(),
            'type_id' => 'connection',
            'owner_id' => $span->owner_id,
            'updater_id' => $span->updater_id ?? $span->owner_id,
            'access_level' => 'public',
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'features',
                'timeless' => true,
            ],
        ]);
        if (! $connectionSpan) {
            return false;
        }

        $connection = new Connection();
        $connection->forceFill([
            'id' => (string) Str::uuid(),
            'parent_id' => $photo->id,
            'child_id' => $span->id,
            'type_id' => 'features',
            'connection_span_id' => $connectionSpan->id,
        ]);
        $connection->save();

        return true;
    }

    private function hasFeaturedPhoto(Span $span): bool
    {
        return Connection::where('type_id', 'features')
            ->where('child_id', $span->id)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->exists();
    }

    private function photoFeaturesSpan(Span $photo, Span $span): bool
    {
        return Connection::where('type_id', 'features')
            ->where('parent_id', $photo->id)
            ->where('child_id', $span->id)
            ->exists();
    }

    /**
     * @param  list<mixed>  $sources
     */
    private function sourcesContainUrl(array $sources, string $url): bool
    {
        foreach ($sources as $source) {
            if (is_string($source) && $source === $url) {
                return true;
            }
            if (is_array($source) && ($source['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }

    private function limitedText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 255);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'wikipedia-photo';
        $slug = $base;
        $counter = 1;
        while (Span::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$counter;
        }

        return $slug;
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
