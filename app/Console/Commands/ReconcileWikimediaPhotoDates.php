<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\WikimediaCommonsApiService;
use App\Services\WikimediaPhotoDateParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReconcileWikimediaPhotoDates extends Command
{
    protected $signature = 'wikimedia:reconcile-photo-dates {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Set Wikimedia photo span dates from Commons taken-date metadata';

    public function handle(WikimediaPhotoDateParser $parser, WikimediaCommonsApiService $commons): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $images = Span::query()
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->source', 'Wikimedia Commons')
            ->get();

        $this->info("Checking {$images->count()} Wikimedia Commons photo(s)");

        $updatedCount = 0;

        foreach ($images as $image) {
            $resolved = $this->resolveDate($image, $parser, $commons);

            if ($resolved['year'] === null) {
                continue;
            }

            if (
                (int) $image->start_year === $resolved['year']
                && $resolved['month'] === null
                && $image->start_month
            ) {
                continue;
            }

            if (
                (int) $image->start_year === $resolved['year']
                && $image->start_month === $resolved['month']
                && $image->start_day === $resolved['day']
            ) {
                continue;
            }

            $from = $this->formatDate($image->start_year, $image->start_month, $image->start_day);
            $to = $this->formatDate($resolved['year'], $resolved['month'], $resolved['day']);
            $this->line("{$image->slug}: {$from} → {$to}");

            if (! $dryRun) {
                $image->start_year = $resolved['year'];
                $image->start_month = $resolved['month'];
                $image->start_day = $resolved['day'];
                $image->end_year = $resolved['year'];
                $image->end_month = $resolved['month'];
                $image->end_day = $resolved['day'];
                $image->save();
            }

            $updatedCount++;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updatedCount} photo(s)");

        return self::SUCCESS;
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null}
     */
    private function resolveDate(Span $image, WikimediaPhotoDateParser $parser, WikimediaCommonsApiService $commons): array
    {
        $metadata = $image->metadata ?? [];
        $wikimediaId = $metadata['wikimedia_id'] ?? null;

        if ($wikimediaId) {
            Cache::forget('wikimedia_image_'.$wikimediaId);
            $remote = $commons->getImage((string) $wikimediaId);
            if (is_array($remote)) {
                return $parser->resolveFromSources([
                    'date' => $remote['metadata']['date'] ?? '',
                    'title' => $remote['title'] ?? ($metadata['title'] ?? $image->name),
                    'description' => $remote['metadata']['description'] ?? ($metadata['description'] ?? ''),
                    'uploaded_at' => $remote['timestamp'] ?? '',
                    'categories' => $remote['metadata']['categories'] ?? [],
                    'taken_on' => $remote['metadata']['taken_on'] ?? '',
                ]);
            }
        }

        return $parser->resolveFromSources([
            'date' => $metadata['date'] ?? '',
            'title' => $metadata['title'] ?? $image->name,
            'description' => $metadata['description'] ?? '',
            'uploaded_at' => $metadata['uploaded_at'] ?? '',
            'categories' => $metadata['categories'] ?? [],
            'taken_on' => $metadata['taken_on'] ?? '',
        ]);
    }

    private function formatDate(?int $year, ?int $month, ?int $day): string
    {
        if (! $year) {
            return '(none)';
        }
        if (! $month) {
            return (string) $year;
        }
        if (! $day) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
