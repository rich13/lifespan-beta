<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create materialized view for connections
        DB::statement('
            CREATE MATERIALIZED VIEW span_connections AS
            SELECT 
                s.id as span_id,
                COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            \'id\', c.id,
                            \'type\', c.type_id,
                            \'connected_span_id\', 
                            CASE 
                                WHEN c.parent_id = s.id THEN c.child_id 
                                ELSE c.parent_id 
                            END,
                            \'role\',
                            CASE 
                                WHEN c.parent_id = s.id THEN \'parent\'
                                ELSE \'child\'
                            END,
                            \'connection_span_id\', c.connection_span_id,
                            \'metadata\', COALESCE(c.metadata, \'{}\')
                        )
                    ) FILTER (WHERE c.id IS NOT NULL),
                    \'[]\'::jsonb
                ) as connections
            FROM spans s
            LEFT JOIN connections c ON (s.id = c.parent_id OR s.id = c.child_id)
            GROUP BY s.id
        ');

        // Create index on span_id for faster lookups
        DB::statement('CREATE UNIQUE INDEX span_connections_span_id_idx ON span_connections (span_id)');

        // Create trigger function to refresh materialized view
        DB::statement('
            CREATE OR REPLACE FUNCTION sync_span_connections() RETURNS TRIGGER AS $$
            BEGIN
                -- Refresh the materialized view for affected spans
                REFRESH MATERIALIZED VIEW CONCURRENTLY span_connections;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger
        DB::statement('
            CREATE TRIGGER connection_sync_trigger
            AFTER INSERT OR UPDATE OR DELETE ON connections
            FOR EACH ROW
            EXECUTE FUNCTION sync_span_connections();
        ');
    }

    public function down(): void
    {
        // Drop trigger
        DB::statement('DROP TRIGGER IF EXISTS connection_sync_trigger ON connections');
        
        // Drop function
        DB::statement('DROP FUNCTION IF EXISTS sync_span_connections');
        
        // Drop materialized view
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS span_connections');
    }
}; 