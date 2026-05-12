<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('span_epistemic_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('span_id');
            $table->bigInteger('effective_year');
            $table->smallInteger('effective_month');
            $table->smallInteger('effective_day');
            $table->json('payload');
            $table->timestamps();

            $table->foreign('span_id')->references('id')->on('spans')->cascadeOnDelete();
            $table->index(
                ['span_id', 'effective_year', 'effective_month', 'effective_day', 'created_at'],
                'span_epistemic_revisions_lookup'
            );
        });

        Schema::create('connection_epistemic_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->bigInteger('effective_year');
            $table->smallInteger('effective_month');
            $table->smallInteger('effective_day');
            $table->json('payload');
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
            $table->index(
                ['connection_id', 'effective_year', 'effective_month', 'effective_day', 'created_at'],
                'connection_epistemic_revisions_lookup'
            );
        });

        $this->backfillFromVersionHistory();
        $this->backfillSpansWithoutRevisions();
        $this->backfillConnectionsWithoutRevisions();
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_epistemic_revisions');
        Schema::dropIfExists('span_epistemic_revisions');
    }

    private function backfillFromVersionHistory(): void
    {
        if (! Schema::hasTable('span_versions') || ! Schema::hasTable('connection_versions')) {
            return;
        }

        DB::table('span_versions')
            ->join('spans', 'spans.id', '=', 'span_versions.span_id')
            ->select('span_versions.*')
            ->orderBy('span_versions.id')
            ->chunk(500, function ($rows): void {
                $now = now();
                $inserts = [];
                foreach ($rows as $row) {
                    $created = $row->created_at ? \Carbon\Carbon::parse($row->created_at) : $now;
                    $inserts[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'span_id' => $row->span_id,
                        'effective_year' => $created->year,
                        'effective_month' => $created->month,
                        'effective_day' => $created->day,
                        'payload' => json_encode($this->spanPayloadFromVersionRow($row)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($inserts !== []) {
                    DB::table('span_epistemic_revisions')->insert($inserts);
                }
            });

        DB::table('connection_versions')
            ->join('connections', 'connections.id', '=', 'connection_versions.connection_id')
            ->select('connection_versions.*')
            ->orderBy('connection_versions.id')
            ->chunk(500, function ($rows): void {
                $now = now();
                $inserts = [];
                foreach ($rows as $row) {
                    $created = $row->created_at ? \Carbon\Carbon::parse($row->created_at) : $now;
                    $inserts[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'connection_id' => $row->connection_id,
                        'effective_year' => $created->year,
                        'effective_month' => $created->month,
                        'effective_day' => $created->day,
                        'payload' => json_encode($this->connectionPayloadFromVersionRow($row)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($inserts !== []) {
                    DB::table('connection_epistemic_revisions')->insert($inserts);
                }
            });
    }

    /**
     * @param  object  $row  span_versions row
     */
    private function spanPayloadFromVersionRow(object $row): array
    {
        return [
            'name' => $row->name,
            'slug' => $row->slug,
            'type_id' => $row->type_id,
            'is_personal_span' => (bool) ($row->is_personal_span ?? false),
            'parent_id' => $row->parent_id,
            'root_id' => $row->root_id,
            'start_year' => $row->start_year !== null ? (int) $row->start_year : null,
            'start_month' => $row->start_month !== null ? (int) $row->start_month : null,
            'start_day' => $row->start_day !== null ? (int) $row->start_day : null,
            'end_year' => $row->end_year !== null ? (int) $row->end_year : null,
            'end_month' => $row->end_month !== null ? (int) $row->end_month : null,
            'end_day' => $row->end_day !== null ? (int) $row->end_day : null,
            'start_precision' => $row->start_precision,
            'end_precision' => $row->end_precision,
            'state' => $row->state,
            'description' => $row->description,
            'notes' => $row->notes,
            'metadata' => $this->decodeJson($row->metadata ?? null) ?? [],
            'sources' => $this->decodeJson($row->sources ?? null) ?? [],
            'permissions' => isset($row->permissions) ? (int) $row->permissions : 0,
            'permission_mode' => $row->permission_mode ?? 'inherit',
            'access_level' => $row->access_level,
            'filter_type' => $row->filter_type,
            'filter_criteria' => $this->decodeJson($row->filter_criteria ?? null),
            'is_predefined' => (bool) ($row->is_predefined ?? false),
        ];
    }

    /**
     * @param  object  $row  connection_versions row
     */
    private function connectionPayloadFromVersionRow(object $row): array
    {
        return [
            'parent_id' => $row->parent_id,
            'child_id' => $row->child_id,
            'type_id' => $row->type_id,
            'connection_span_id' => $row->connection_span_id,
            'metadata' => $this->decodeJson($row->metadata ?? null) ?? [],
        ];
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    private function backfillSpansWithoutRevisions(): void
    {
        DB::table('spans')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('span_epistemic_revisions')
                    ->whereColumn('span_epistemic_revisions.span_id', 'spans.id');
            })
            ->orderBy('id')
            ->chunk(500, function ($spans): void {
                $now = now();
                $inserts = [];
                foreach ($spans as $span) {
                    $created = $span->created_at ? \Carbon\Carbon::parse($span->created_at) : $now;
                    $inserts[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'span_id' => $span->id,
                        'effective_year' => $created->year,
                        'effective_month' => $created->month,
                        'effective_day' => $created->day,
                        'payload' => json_encode($this->spanPayloadFromSpanRow($span)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($inserts !== []) {
                    DB::table('span_epistemic_revisions')->insert($inserts);
                }
            });
    }

    /**
     * @param  object  $span  spans row
     */
    private function spanPayloadFromSpanRow(object $span): array
    {
        return [
            'name' => $span->name,
            'slug' => $span->slug,
            'type_id' => $span->type_id,
            'is_personal_span' => (bool) ($span->is_personal_span ?? false),
            'parent_id' => $span->parent_id,
            'root_id' => $span->root_id,
            'start_year' => $span->start_year !== null ? (int) $span->start_year : null,
            'start_month' => $span->start_month !== null ? (int) $span->start_month : null,
            'start_day' => $span->start_day !== null ? (int) $span->start_day : null,
            'end_year' => $span->end_year !== null ? (int) $span->end_year : null,
            'end_month' => $span->end_month !== null ? (int) $span->end_month : null,
            'end_day' => $span->end_day !== null ? (int) $span->end_day : null,
            'start_precision' => $span->start_precision,
            'end_precision' => $span->end_precision,
            'state' => $span->state,
            'description' => $span->description,
            'notes' => $span->notes,
            'metadata' => $this->decodeJson($span->metadata ?? null) ?? [],
            'sources' => $this->decodeJson($span->sources ?? null) ?? [],
            'permissions' => isset($span->permissions) ? (int) $span->permissions : 0,
            'permission_mode' => $span->permission_mode ?? 'inherit',
            'access_level' => $span->access_level,
            'filter_type' => $span->filter_type,
            'filter_criteria' => $this->decodeJson($span->filter_criteria ?? null),
            'is_predefined' => (bool) ($span->is_predefined ?? false),
        ];
    }

    private function backfillConnectionsWithoutRevisions(): void
    {
        DB::table('connections')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('connection_epistemic_revisions')
                    ->whereColumn('connection_epistemic_revisions.connection_id', 'connections.id');
            })
            ->orderBy('id')
            ->chunk(500, function ($connections): void {
                $now = now();
                $inserts = [];
                foreach ($connections as $c) {
                    $created = $c->created_at ? \Carbon\Carbon::parse($c->created_at) : $now;
                    $inserts[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'connection_id' => $c->id,
                        'effective_year' => $created->year,
                        'effective_month' => $created->month,
                        'effective_day' => $created->day,
                        'payload' => json_encode($this->connectionPayloadFromConnectionRow($c)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($inserts !== []) {
                    DB::table('connection_epistemic_revisions')->insert($inserts);
                }
            });
    }

    /**
     * @param  object  $c  connections row
     */
    private function connectionPayloadFromConnectionRow(object $c): array
    {
        return [
            'parent_id' => $c->parent_id,
            'child_id' => $c->child_id,
            'type_id' => $c->type_id,
            'connection_span_id' => $c->connection_span_id,
            'metadata' => $this->decodeJson($c->metadata ?? null) ?? [],
        ];
    }
};
