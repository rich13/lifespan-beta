<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $views = $this->captureDependentViews();
        $this->dropCapturedViews($views);

        DB::statement('ALTER TABLE spans ALTER COLUMN start_year TYPE BIGINT');
        DB::statement('ALTER TABLE spans ALTER COLUMN end_year TYPE BIGINT');

        DB::statement('ALTER TABLE span_versions ALTER COLUMN start_year TYPE BIGINT');
        DB::statement('ALTER TABLE span_versions ALTER COLUMN end_year TYPE BIGINT');

        $this->recreateCapturedViews($views);
    }

    public function down(): void
    {
        $views = $this->captureDependentViews();
        $this->dropCapturedViews($views);

        DB::statement('ALTER TABLE spans ALTER COLUMN start_year TYPE INTEGER');
        DB::statement('ALTER TABLE spans ALTER COLUMN end_year TYPE INTEGER');

        DB::statement('ALTER TABLE span_versions ALTER COLUMN start_year TYPE INTEGER');
        DB::statement('ALTER TABLE span_versions ALTER COLUMN end_year TYPE INTEGER');

        $this->recreateCapturedViews($views);
    }

    private function captureDependentViews(): array
    {
        $namesInDropOrder = ['temporal_connections', 'spans_with_dates'];
        $captured = [];

        foreach ($namesInDropOrder as $name) {
            $row = DB::selectOne(
                "SELECT viewname, definition FROM pg_views WHERE schemaname = 'public' AND viewname = ?",
                [$name]
            );

            if ($row) {
                $captured[] = [
                    'name' => $row->viewname,
                    'definition' => $row->definition,
                ];
            }
        }

        return $captured;
    }

    private function dropCapturedViews(array $views): void
    {
        foreach ($views as $view) {
            DB::statement(sprintf('DROP VIEW IF EXISTS "%s"', $view['name']));
        }
    }

    private function recreateCapturedViews(array $views): void
    {
        foreach (array_reverse($views) as $view) {
            DB::statement(sprintf('CREATE VIEW "%s" AS %s', $view['name'], $view['definition']));
        }
    }
};
