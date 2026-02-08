<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Span;
use App\Models\Connection;

class DbCheckCommand extends Command
{
    protected $signature = 'db:check
                            {--fail : Exit with code 1 if any issues found (for CI/scripts)}';

    protected $description = 'Check database connection and run integrity checks (spans short_id, connections, orphans)';

    private bool $hasIssues = false;

    public function handle(): int
    {
        $this->info('Database integrity check');
        $this->newLine();

        $this->checkConnection();
        $this->checkMigrations();
        $this->checkSpansShortId();
        $this->checkDuplicateShortIds();
        $this->checkOrphanedConnections();
        $this->checkConnectionSpanType();

        $this->newLine();
        if ($this->hasIssues) {
            $this->warn('One or more issues were found. Review the output above.');
            return $this->option('fail') ? 1 : 0;
        }
        $this->info('All checks passed. Database looks healthy.');
        return 0;
    }

    private function checkConnection(): void
    {
        $this->info('1. Database connection');
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $this->line('   <info>✓</info> Connected');
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }

    private function checkMigrations(): void
    {
        $this->info('2. Migrations');
        try {
            Artisan::call('migrate:status', ['--no-ansi' => true]);
            $output = trim(Artisan::output());
            $hasPending = str_contains($output, 'Pending');
            if ($hasPending) {
                $this->line('   <comment>!</comment> Pending migrations (run: php artisan migrate)');
                $this->hasIssues = true;
            } else {
                $this->line('   <info>✓</info> No pending migrations');
            }
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }

    private function checkSpansShortId(): void
    {
        $this->info('3. Spans with null short_id');
        try {
            $count = Span::whereNull('short_id')->count();
            if ($count > 0) {
                $this->line('   <error>✗</error> ' . $count . ' span(s) have null short_id (violates NOT NULL constraint)');
                $this->hasIssues = true;
            } else {
                $this->line('   <info>✓</info> All spans have short_id');
            }
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }

    private function checkDuplicateShortIds(): void
    {
        $this->info('4. Duplicate short_id');
        try {
            $dupes = DB::table('spans')
                ->select('short_id')
                ->whereNotNull('short_id')
                ->groupBy('short_id')
                ->havingRaw('count(*) > 1')
                ->pluck('short_id');
            if ($dupes->isNotEmpty()) {
                $this->line('   <error>✗</error> Duplicate short_id(s): ' . $dupes->take(10)->implode(', ') . ($dupes->count() > 10 ? ' (' . $dupes->count() . ' total)' : ''));
                $this->hasIssues = true;
            } else {
                $this->line('   <info>✓</info> No duplicate short_id');
            }
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }

    private function checkOrphanedConnections(): void
    {
        $this->info('5. Orphaned connections (connection_span_id points to missing span)');
        try {
            $count = Connection::whereNotNull('connection_span_id')
                ->whereDoesntHave('connectionSpan')
                ->count();
            if ($count > 0) {
                $this->line('   <error>✗</error> ' . $count . ' connection(s) reference a non-existent span. Fix: php artisan connections:cleanup');
                $this->hasIssues = true;
            } else {
                $this->line('   <info>✓</info> No orphaned connections');
            }
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }

    private function checkConnectionSpanType(): void
    {
        $this->info('6. connection_span_id type (must be type_id = connection)');
        try {
            $bad = DB::table('connections as c')
                ->join('spans as s', 's.id', '=', 'c.connection_span_id')
                ->where('s.type_id', '!=', 'connection')
                ->count();
            if ($bad > 0) {
                $this->line('   <error>✗</error> ' . $bad . ' connection(s) have connection_span_id pointing to a span that is not type_id=connection');
                $this->hasIssues = true;
            } else {
                $this->line('   <info>✓</info> All connection spans have correct type');
            }
        } catch (\Throwable $e) {
            $this->line('   <error>✗</error> ' . $e->getMessage());
            $this->hasIssues = true;
        }
    }
}
