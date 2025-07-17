<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DebugSpanConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:span-connections {span1} {span2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print all connections (with type, start/end date, and names) between two spans, given their slugs or IDs.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $span1Input = $this->argument('span1');
        $span2Input = $this->argument('span2');

        $span1 = null;
        $span2 = null;
        if (Str::isUuid($span1Input)) {
            $span1 = Span::where('id', $span1Input)->first();
        }
        if (!$span1) {
            $span1 = Span::where('slug', $span1Input)->first();
        }
        if (Str::isUuid($span2Input)) {
            $span2 = Span::where('id', $span2Input)->first();
        }
        if (!$span2) {
            $span2 = Span::where('slug', $span2Input)->first();
        }

        if (!$span1 || !$span2) {
            $this->error('Could not find both spans.');
            return 1;
        }

        $this->info("Span 1: {$span1->name} ({$span1->id})");
        $this->info("Span 2: {$span2->name} ({$span2->id})");

        $connections = Connection::where(function($q) use ($span1, $span2) {
            $q->where('parent_id', $span1->id)->where('child_id', $span2->id);
        })->orWhere(function($q) use ($span1, $span2) {
            $q->where('parent_id', $span2->id)->where('child_id', $span1->id);
        })->get();

        if ($connections->isEmpty()) {
            $this->warn('No connections found between these spans.');
            return 0;
        }

        foreach ($connections as $conn) {
            $type = $conn->type_id;
            $start = $conn->start_date ? $conn->start_date : 'NULL';
            $end = $conn->end_date ? $conn->end_date : 'NULL';
            $this->line("Connection: {$span1->name} ↔ {$span2->name} | Type: $type | Start: $start | End: $end | Connection ID: {$conn->id}");
        }

        return 0;
    }
} 