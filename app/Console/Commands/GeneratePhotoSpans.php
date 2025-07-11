<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GeneratePhotoSpans extends Command
{
    protected $signature = 'spans:generate-photos {--count=10 : Number of photo spans to generate}';
    protected $description = 'Generate sample photo spans for testing temporal functionality';

    public function handle(): void
    {
        $count = $this->option('count');
        $user = User::first();
        
        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return;
        }

        $this->info("Generating {$count} photo spans...");

        $samplePhotos = [
            ['name' => 'Sunset at the Beach', 'year' => 2023, 'month' => 7, 'day' => 15],
            ['name' => 'Mountain Hiking Trip', 'year' => 2023, 'month' => 8, 'day' => 22],
            ['name' => 'Birthday Celebration', 'year' => 2023, 'month' => 9, 'day' => 10],
            ['name' => 'Coffee Shop Visit', 'year' => 2023, 'month' => 10, 'day' => 5],
            ['name' => 'Autumn Leaves', 'year' => 2023, 'month' => 11, 'day' => 12],
            ['name' => 'Winter Snow', 'year' => 2023, 'month' => 12, 'day' => 25],
            ['name' => 'New Year Fireworks', 'year' => 2024, 'month' => 1, 'day' => 1],
            ['name' => 'Spring Flowers', 'year' => 2024, 'month' => 4, 'day' => 15],
            ['name' => 'Summer Picnic', 'year' => 2024, 'month' => 6, 'day' => 20],
            ['name' => 'City Skyline', 'year' => 2024, 'month' => 8, 'day' => 8],
        ];

        for ($i = 0; $i < min($count, count($samplePhotos)); $i++) {
            $photo = $samplePhotos[$i];
            
            $span = new Span([
                'name' => $photo['name'],
                'type_id' => 'thing',
                'start_year' => $photo['year'],
                'start_month' => $photo['month'],
                'start_day' => $photo['day'],
                'end_year' => $photo['year'],
                'end_month' => $photo['month'],
                'end_day' => $photo['day'],
                'start_precision' => 'day',
                'end_precision' => 'day',
                'access_level' => 'public',
                'state' => 'published',
                'description' => "A photo taken on {$photo['month']}/{$photo['day']}/{$photo['year']}",
                'metadata' => [
                    'subtype' => 'photo',
                    'image_url' => "https://picsum.photos/400/300?random=" . ($i + 1),
                    'thumbnail_url' => "https://picsum.photos/200/150?random=" . ($i + 1),
                    'camera' => 'Sample Camera',
                    'location' => 'Sample Location',
                    'tags' => ['sample', 'photo', 'test']
                ],
                'owner_id' => $user->id,
                'updater_id' => $user->id,
            ]);
            
            $span->save();
            
            $this->line("Created photo span: {$span->name} ({$span->id})");
        }

        $this->info("Successfully generated " . min($count, count($samplePhotos)) . " photo spans!");
    }
} 