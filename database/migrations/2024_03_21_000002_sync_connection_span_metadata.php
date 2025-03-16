<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Get all connection spans and their associated connections
        $connectionSpans = DB::table('spans as s')
            ->join('connections as c', 's.id', '=', 'c.connection_span_id')
            ->select([
                's.id as span_id',
                's.metadata as current_metadata',
                'c.type_id as connection_type'
            ])
            ->where('s.type_id', 'connection')
            ->get();

        // Update each connection span's metadata
        foreach ($connectionSpans as $span) {
            $metadata = json_decode($span->current_metadata, true) ?? [];
            
            // Update the connection_type in metadata
            $metadata['connection_type'] = $span->connection_type;
            
            DB::table('spans')
                ->where('id', $span->span_id)
                ->update(['metadata' => json_encode($metadata)]);
        }
    }

    public function down()
    {
        // We can't restore the original metadata since it wasn't stored
        // The down migration is empty as this is a one-way change
    }
}; 