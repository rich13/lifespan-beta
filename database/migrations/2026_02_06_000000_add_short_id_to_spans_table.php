<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SHORT_ID_LENGTH = 8;

    /**
     * Base62 alphabet (0-9, A-Z, a-z).
     */
    private const BASE62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->string('short_id', self::SHORT_ID_LENGTH)->nullable()->unique()->after('slug');
        });

        $this->backfillShortIds();

        Schema::table('spans', function (Blueprint $table) {
            $table->string('short_id', self::SHORT_ID_LENGTH)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropColumn('short_id');
        });
    }

    private function backfillShortIds(): void
    {
        $used = DB::table('spans')->whereNotNull('short_id')->pluck('short_id')->flip()->all();
        $spans = DB::table('spans')->whereNull('short_id')->orderBy('id')->get();

        foreach ($spans as $span) {
            $shortId = $this->generateUniqueShortId($used);
            $used[$shortId] = true;
            DB::table('spans')->where('id', $span->id)->update(['short_id' => $shortId]);
        }

        \Log::info('Migration: Backfilled short_id for spans', [
            'count' => $spans->count(),
            'migration' => 'add_short_id_to_spans_table',
        ]);
    }

    private function generateUniqueShortId(array &$used): string
    {
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $id = '';
            $alphabet = self::BASE62;
            $len = strlen($alphabet);
            for ($j = 0; $j < self::SHORT_ID_LENGTH; $j++) {
                $id .= $alphabet[random_int(0, $len - 1)];
            }
            if (! isset($used[$id])) {
                return $id;
            }
        }
        throw new \RuntimeException('Could not generate unique short_id after ' . $maxAttempts . ' attempts');
    }
};
