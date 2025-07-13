<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Span;
use App\Models\Connection;

class CheckConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connections:check {span_id?} {--type=created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check connections for a specific span or all connections of a type';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $spanId = $this->argument('span_id');
        $connectionType = $this->option('type');

        if ($spanId) {
            $this->checkSpanConnections($spanId, $connectionType);
        } else {
            $this->checkAllConnections($connectionType);
        }

        return 0;
    }

    private function checkSpanConnections(string $spanId, string $connectionType): void
    {
        $span = Span::find($spanId);
        if (!$span) {
            $this->error("Span not found: {$spanId}");
            return;
        }

        $this->info("Checking connections for span: {$span->name} (ID: {$span->id})");

        $connections = Connection::where('parent_id', $span->id)
            ->where('type_id', $connectionType)
            ->with('child')
            ->get();

        $this->info("Found {$connections->count()} '{$connectionType}' connections");

        if ($connections->count() > 0) {
            $this->info("\nConnections:");
            foreach ($connections as $connection) {
                $child = $connection->child;
                $this->line("- To: {$child->name} (ID: {$child->id}) - Created: {$connection->created_at}");
            }
        }

        // Check for duplicates
        $duplicates = $connections->groupBy('child_id')
            ->filter(function ($group) {
                return $group->count() > 1;
            });

        if ($duplicates->count() > 0) {
            $this->warn("\nFound duplicate connections:");
            foreach ($duplicates as $childId => $duplicateConnections) {
                $child = $duplicateConnections->first()->child;
                $this->warn("- Multiple connections to: {$child->name} (ID: {$child->id}) - Count: {$duplicateConnections->count()}");
                foreach ($duplicateConnections as $conn) {
                    $this->line("  Connection ID: {$conn->id}, Created: {$conn->created_at}");
                }
            }
        }
    }

    private function checkAllConnections(string $connectionType): void
    {
        $this->info("Checking all '{$connectionType}' connections...");

        $connections = Connection::where('type_id', $connectionType)
            ->with(['parent', 'child'])
            ->get();

        $this->info("Total '{$connectionType}' connections: {$connections->count()}");

        // Group by parent and child to find duplicates
        $duplicates = $connections->groupBy(function ($connection) {
            return $connection->parent_id . ':' . $connection->child_id;
        })->filter(function ($group) {
            return $group->count() > 1;
        });

        if ($duplicates->count() > 0) {
            $this->warn("\nFound {$duplicates->count()} groups with duplicate connections:");
            foreach ($duplicates as $key => $duplicateConnections) {
                $parent = $duplicateConnections->first()->parent;
                $child = $duplicateConnections->first()->child;
                $this->warn("- {$parent->name} -> {$child->name} (Count: {$duplicateConnections->count()})");
                foreach ($duplicateConnections as $conn) {
                    $this->line("  Connection ID: {$conn->id}, Created: {$conn->created_at}");
                }
            }
        } else {
            $this->info("No duplicate connections found");
        }
    }
}
