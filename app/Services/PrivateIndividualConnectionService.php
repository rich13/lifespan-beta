<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PrivateIndividualConnectionService
{
    /**
     * Person spans marked as private individuals.
     */
    public function privateIndividualsQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'person')
            ->whereRaw("metadata->>'subtype' = 'private_individual'");
    }

    /**
     * Private individuals who are themselves public/shared, or who have a non-private connection span.
     */
    public function individualsNeedingFixQuery(): Builder
    {
        return $this->privateIndividualsQuery()
            ->where(function (Builder $query) {
                $query->where('access_level', '!=', 'private')
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('connections as c')
                            ->join('spans as cs', 'cs.id', '=', 'c.connection_span_id')
                            ->where('cs.access_level', '!=', 'private')
                            ->where(function ($ends) {
                                $ends->whereColumn('c.parent_id', 'spans.id')
                                    ->orWhereColumn('c.child_id', 'spans.id');
                            });
                    });
            })
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return array{
     *     total_private_individuals: int,
     *     private_individuals_with_public_connections: int,
     *     total_public_connections: int,
     *     individuals_needing_fix: int
     * }
     */
    public function stats(): array
    {
        $totalPrivateIndividuals = $this->privateIndividualsQuery()->count();
        $individualsNeedingFix = $this->individualsNeedingFixQuery()->count();

        $publicConnectionCountSql = "
            SELECT COUNT(DISTINCT c.id)
            FROM connections c
            INNER JOIN spans cs ON cs.id = c.connection_span_id AND cs.access_level = 'public'
            WHERE EXISTS (
                SELECT 1
                FROM spans s
                WHERE s.id IN (c.parent_id, c.child_id)
                  AND s.type_id = 'person'
                  AND s.metadata->>'subtype' = 'private_individual'
            )
        ";

        $individualsWithPublicConnectionsSql = "
            SELECT COUNT(*)
            FROM spans s
            WHERE s.type_id = 'person'
              AND s.metadata->>'subtype' = 'private_individual'
              AND EXISTS (
                  SELECT 1
                  FROM connections c
                  INNER JOIN spans cs ON cs.id = c.connection_span_id AND cs.access_level = 'public'
                  WHERE c.parent_id = s.id OR c.child_id = s.id
              )
        ";

        return [
            'total_private_individuals' => $totalPrivateIndividuals,
            'private_individuals_with_public_connections' => (int) DB::selectOne($individualsWithPublicConnectionsSql)->count,
            'total_public_connections' => (int) DB::selectOne($publicConnectionCountSql)->count,
            'individuals_needing_fix' => $individualsNeedingFix,
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     scanned: int,
     *     offset: int,
     *     limit: int,
     *     total: int,
     *     has_more: bool
     * }
     */
    public function scanBatch(int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $total = $this->individualsNeedingFixQuery()->count();

        $publicConnectionCountSql = "
            (
                SELECT COUNT(*)
                FROM connections c
                INNER JOIN spans cs ON cs.id = c.connection_span_id
                WHERE cs.access_level != 'private'
                  AND (c.parent_id = spans.id OR c.child_id = spans.id)
            )
        ";

        $individuals = $this->individualsNeedingFixQuery()
            ->with('owner')
            ->addSelect('spans.*')
            ->addSelect(DB::raw($publicConnectionCountSql.' as public_connection_count'))
            ->offset($offset)
            ->limit($limit)
            ->get();

        $rows = $individuals->map(function (Span $individual) {
            return [
                'id' => $individual->id,
                'name' => $individual->name,
                'url' => route('spans.show', $individual),
                'access_level' => $individual->access_level,
                'public_connection_count' => (int) ($individual->public_connection_count ?? 0),
                'owner_name' => $individual->owner->name ?? 'Unknown',
                'description' => $individual->description,
            ];
        })->all();

        $scanned = count($rows);

        return [
            'rows' => $rows,
            'scanned' => $scanned,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($offset + $scanned) < $total,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function idsNeedingFix(): array
    {
        return $this->individualsNeedingFixQuery()
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * Make a private individual and their connection spans private.
     *
     * The other end of each connection (place, public figure, etc.) is left unchanged.
     *
     * @return array{
     *     status: string,
     *     individual_id: string,
     *     name: string,
     *     fixed_connections: int,
     *     individual_made_private: bool,
     *     error?: string
     * }
     */
    public function fixIndividual(Span $individual, PublicSpanCache $publicSpanCache): array
    {
        $metadata = $individual->metadata ?? [];
        $subtype = $metadata['subtype'] ?? null;

        if ($individual->type_id !== 'person' || $subtype !== 'private_individual') {
            return [
                'status' => 'error',
                'individual_id' => $individual->id,
                'name' => $individual->name,
                'fixed_connections' => 0,
                'individual_made_private' => false,
                'error' => "Span '{$individual->name}' is not a private individual.",
            ];
        }

        $fixedConnections = 0;
        $individualMadePrivate = false;
        $spanIdsToInvalidate = [(string) $individual->id];

        if ($individual->access_level !== 'private') {
            $individual->access_level = 'private';
            $individual->saveQuietly();
            $individualMadePrivate = true;
        }

        $connections = Connection::query()
            ->where(function ($query) use ($individual) {
                $query->where('parent_id', $individual->id)
                    ->orWhere('child_id', $individual->id);
            })
            ->with('connectionSpan')
            ->get();

        foreach ($connections as $connection) {
            $connectionSpan = $connection->connectionSpan;
            if (! $connectionSpan || $connectionSpan->access_level === 'private') {
                continue;
            }

            $connectionSpan->access_level = 'private';
            $connectionSpan->saveQuietly();
            $fixedConnections++;

            $spanIdsToInvalidate[] = (string) $connectionSpan->id;
            $spanIdsToInvalidate[] = (string) $connection->parent_id;
            $spanIdsToInvalidate[] = (string) $connection->child_id;
        }

        foreach (array_unique(array_filter($spanIdsToInvalidate)) as $spanId) {
            $publicSpanCache->invalidateSpan($spanId);
        }

        if ($fixedConnections === 0 && ! $individualMadePrivate) {
            return [
                'status' => 'skipped',
                'individual_id' => $individual->id,
                'name' => $individual->name,
                'fixed_connections' => 0,
                'individual_made_private' => false,
            ];
        }

        return [
            'status' => 'fixed',
            'individual_id' => $individual->id,
            'name' => $individual->name,
            'fixed_connections' => $fixedConnections,
            'individual_made_private' => $individualMadePrivate,
        ];
    }

    /**
     * @param  array<int, string>  $individualIds
     * @return array{
     *     processed: int,
     *     fixed_individuals: int,
     *     skipped: int,
     *     fixed_connections: int,
     *     errors: array<int, string>,
     *     current_item: string|null,
     *     fixed_ids: array<int, string>
     * }
     */
    public function fixIds(array $individualIds, PublicSpanCache $publicSpanCache): array
    {
        $processed = 0;
        $fixedIndividuals = 0;
        $skipped = 0;
        $fixedConnections = 0;
        $errors = [];
        $fixedIds = [];
        $currentItem = null;

        $individuals = Span::query()
            ->whereIn('id', $individualIds)
            ->get()
            ->keyBy('id');

        foreach ($individualIds as $individualId) {
            $individual = $individuals->get($individualId);
            if (! $individual) {
                $errors[] = "Private individual with ID {$individualId} not found.";
                $processed++;
                continue;
            }

            $currentItem = $individual->name;

            try {
                $result = $this->fixIndividual($individual, $publicSpanCache);
                $processed++;

                if ($result['status'] === 'error') {
                    $errors[] = $result['error'] ?? "Failed to fix {$individual->name}.";
                    continue;
                }

                if ($result['status'] === 'skipped') {
                    $skipped++;
                    continue;
                }

                $fixedIndividuals++;
                $fixedConnections += $result['fixed_connections'];
                $fixedIds[] = $individual->id;
            } catch (\Throwable $e) {
                $processed++;
                $errors[] = "Failed to fix connections for {$individual->name}: ".$e->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'fixed_individuals' => $fixedIndividuals,
            'skipped' => $skipped,
            'fixed_connections' => $fixedConnections,
            'errors' => $errors,
            'current_item' => $currentItem,
            'fixed_ids' => $fixedIds,
        ];
    }
}
