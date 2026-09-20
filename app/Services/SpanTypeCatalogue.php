<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpanTypeCatalogue
{
    public const CACHE_KEY = 'span_type_catalogue';

    /**
     * Span types stay in cache until an admin edits them; a week is a safety net
     * if a migration updates span_types without going through Eloquent.
     */
    private const CACHE_TTL_SECONDS = 604800;

    private const MODAL_EXCLUDED_TYPE_IDS = ['connection', 'note', 'set'];

    /**
     * Types shown in the New span modal (type_id + name only).
     *
     * @return Collection<int, object{type_id: string, name: string}>
     */
    public function forNewSpanModal(): Collection
    {
        return collect($this->payload()['modal'])
            ->map(fn (array $type) => (object) $type);
    }

    /**
     * Type ids marked timeless in span type metadata.
     *
     * @return list<string>
     */
    public function timelessTypeIds(): array
    {
        return $this->payload()['timeless'];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{modal: list<array{type_id: string, name: string}>, timeless: list<string>}
     */
    private function payload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $rows = DB::table('span_types')
                ->orderBy('name')
                ->get([
                    'type_id',
                    'name',
                    DB::raw("metadata->>'timeless' as timeless"),
                ]);

            $modal = [];
            $timeless = [];

            foreach ($rows as $row) {
                if ($row->timeless === 'true') {
                    $timeless[] = $row->type_id;
                }

                if (in_array($row->type_id, self::MODAL_EXCLUDED_TYPE_IDS, true)) {
                    continue;
                }

                $modal[] = [
                    'type_id' => $row->type_id,
                    'name' => $row->name,
                ];
            }

            return [
                'modal' => $modal,
                'timeless' => $timeless,
            ];
        });
    }
}
