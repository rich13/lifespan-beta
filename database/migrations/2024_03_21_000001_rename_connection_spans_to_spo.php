<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Get all connection spans and their related data
        $connectionSpans = DB::table('spans as s')
            ->join('connections as c', 's.id', '=', 'c.connection_span_id')
            ->join('spans as parent', 'c.parent_id', '=', 'parent.id')
            ->join('spans as child', 'c.child_id', '=', 'child.id')
            ->join('connection_types as ct', 'c.type_id', '=', 'ct.type')
            ->select([
                's.id as span_id',
                'parent.name as subject_name',
                'ct.forward_predicate',
                'child.name as object_name'
            ])
            ->where('s.type_id', 'connection')
            ->get();

        // Update each connection span's name
        foreach ($connectionSpans as $span) {
            $newName = "{$span->subject_name} {$span->forward_predicate} {$span->object_name}";
            
            DB::table('spans')
                ->where('id', $span->span_id)
                ->update(['name' => $newName]);
        }
    }

    public function down()
    {
        // We can't restore the original names since they weren't stored
        // The down migration is empty as this is a one-way change
    }
}; 