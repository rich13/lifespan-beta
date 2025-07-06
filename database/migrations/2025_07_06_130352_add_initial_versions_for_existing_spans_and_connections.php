<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Span;
use App\Models\Connection;
use App\Models\SpanVersion;
use App\Models\ConnectionVersion;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add initial versions for spans that don't have any versions
        $spansWithoutVersions = Span::whereNotExists(function ($query) {
            $query->select(\DB::raw(1))
                  ->from('span_versions')
                  ->whereColumn('span_versions.span_id', 'spans.id');
        })->get();

        foreach ($spansWithoutVersions as $span) {
            // Prepare version data
            $versionData = [
                'span_id' => $span->id,
                'version_number' => 1,
                'changed_by' => $span->owner_id ?? $span->updater_id ?? 1, // Fallback to user ID 1 if no owner
                'change_summary' => 'Initial version',
                'name' => $span->name,
                'slug' => $span->slug,
                'type_id' => $span->type_id,
                'is_personal_span' => $span->is_personal_span,
                'parent_id' => $span->parent_id,
                'root_id' => $span->root_id,
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'start_precision' => $span->start_precision,
                'end_precision' => $span->end_precision,
                'state' => $span->state,
                'description' => $span->description,
                'notes' => $span->notes,
                'metadata' => $span->metadata,
                'sources' => $span->sources,
                'permissions' => $span->permissions,
                'permission_mode' => $span->permission_mode,
                'access_level' => $span->access_level,
                'filter_type' => $span->filter_type,
                'filter_criteria' => $span->filter_criteria,
                'is_predefined' => $span->is_predefined,
                'created_at' => $span->created_at,
                'updated_at' => $span->created_at, // Use created_at as the version timestamp
            ];

            SpanVersion::create($versionData);
        }

        // Add initial versions for connections that don't have any versions
        $connectionsWithoutVersions = Connection::whereNotExists(function ($query) {
            $query->select(\DB::raw(1))
                  ->from('connection_versions')
                  ->whereColumn('connection_versions.connection_id', 'connections.id');
        })->get();

        foreach ($connectionsWithoutVersions as $connection) {
            // Get user ID from connection span or connected spans
            $userId = null;
            if ($connection->connectionSpan && $connection->connectionSpan->owner_id) {
                $userId = $connection->connectionSpan->owner_id;
            } elseif ($connection->subject && $connection->subject->owner_id) {
                $userId = $connection->subject->owner_id;
            } elseif ($connection->object && $connection->object->owner_id) {
                $userId = $connection->object->owner_id;
            } else {
                $userId = 1; // Fallback to user ID 1
            }

            // Prepare version data
            $versionData = [
                'connection_id' => $connection->id,
                'version_number' => 1,
                'changed_by' => $userId,
                'change_summary' => 'Initial version',
                'parent_id' => $connection->parent_id,
                'child_id' => $connection->child_id,
                'type_id' => $connection->type_id,
                'connection_span_id' => $connection->connection_span_id,
                'metadata' => $connection->metadata,
                'created_at' => $connection->created_at,
                'updated_at' => $connection->created_at, // Use created_at as the version timestamp
            ];

            ConnectionVersion::create($versionData);
        }

        // Log the results
        \Log::info('Migration: Added initial versions', [
            'spans_processed' => $spansWithoutVersions->count(),
            'connections_processed' => $connectionsWithoutVersions->count(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all version 1 records that have 'Initial version' as change_summary
        SpanVersion::where('version_number', 1)
                  ->where('change_summary', 'Initial version')
                  ->delete();

        ConnectionVersion::where('version_number', 1)
                        ->where('change_summary', 'Initial version')
                        ->delete();
    }
};
