<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bands already create tracks. A corrected public figure needs the same link to stay valid.
     */
    public function up(): void
    {
        $row = DB::table('connection_types')->where('type', 'created')->first();
        if (! $row) {
            return;
        }

        $allowed = json_decode($row->allowed_span_types, true);
        if (! is_array($allowed)) {
            return;
        }

        $parents = $allowed['parent'] ?? [];
        if (! in_array('band', $parents, true)) {
            $parents[] = 'band';
        }

        $allowed['parent'] = array_values($parents);

        DB::table('connection_types')
            ->where('type', 'created')
            ->update([
                'allowed_span_types' => json_encode($allowed),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $row = DB::table('connection_types')->where('type', 'created')->first();
        if (! $row) {
            return;
        }

        $allowed = json_decode($row->allowed_span_types, true);
        if (! is_array($allowed)) {
            return;
        }

        $allowed['parent'] = array_values(array_filter(
            $allowed['parent'] ?? [],
            fn ($type) => $type !== 'band'
        ));

        DB::table('connection_types')
            ->where('type', 'created')
            ->update([
                'allowed_span_types' => json_encode($allowed),
                'updated_at' => now(),
            ]);
    }
};
