<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Span;
use Illuminate\Support\Facades\DB;

class CheckPhotoSpans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check photo spans and identify duplicates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking photo spans...');

        $totalPhotos = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->count();

        $photosWithFlickrId = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereNotNull(DB::raw("metadata->>'flickr_id'"))
            ->count();

        $photosWithoutFlickrId = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereNull(DB::raw("metadata->>'flickr_id'"))
            ->count();

        $this->info("Total photo spans: {$totalPhotos}");
        $this->info("With flickr_id: {$photosWithFlickrId}");
        $this->info("Without flickr_id: {$photosWithoutFlickrId}");

        if ($photosWithoutFlickrId > 0) {
            $this->info("\nPhoto spans without flickr_id:");
            $spans = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'photo')
                ->whereNull(DB::raw("metadata->>'flickr_id'"))
                ->orderBy('owner_id')
                ->orderBy('created_at')
                ->get(['id', 'name', 'owner_id', 'created_at']);

            foreach ($spans as $span) {
                $this->line("ID: {$span->id}, Name: {$span->name}, Owner: {$span->owner_id}, Created: {$span->created_at}");
            }
        }

        // Check for duplicates by name and owner
        $this->info("\nChecking for duplicates by name and owner...");
        $duplicates = DB::select("
            SELECT name, owner_id, COUNT(*) as count
            FROM spans 
            WHERE type_id = 'thing' 
              AND metadata->>'subtype' = 'photo'
            GROUP BY name, owner_id 
            HAVING COUNT(*) > 1
            ORDER BY count DESC, name
        ");

        if (empty($duplicates)) {
            $this->info("No duplicates found by name and owner");
        } else {
            $this->info("Found " . count($duplicates) . " groups with duplicate names:");
            foreach ($duplicates as $dup) {
                $this->line("Name: '{$dup->name}', Owner: {$dup->owner_id}, Count: {$dup->count}");
            }
        }

        return 0;
    }
}
