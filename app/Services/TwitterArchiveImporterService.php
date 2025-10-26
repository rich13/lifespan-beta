<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TwitterArchiveImporterService
{
    /**
     * Parse Twitter archive JS file
     */
    public function parseTweetsFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new \Exception('Failed to read file');
        }
        
        // Remove the JavaScript wrapper: window.YTD.tweets.partN = [ ... ]
        // Handle various whitespace and format variations
        $content = preg_replace('/^[\s\n\r]*window\.YTD\.tweets\.part\d+[\s\n\r]*=[\s\n\r]*/m', '', $content);
        
        // Remove trailing semicolon and whitespace
        $content = rtrim($content, ";\n\r\t ");
        
        // Parse JSON
        $data = json_decode($content, true);
        
        if ($data === null) {
            $jsonError = json_last_error_msg();
            throw new \Exception('Invalid JSON format: ' . $jsonError);
        }
        
        if (!is_array($data)) {
            throw new \Exception('Invalid Twitter archive format: expected array');
        }
        
        return $data;
    }
    
    /**
     * Extract tweets from parsed data
     */
    public function extractTweets(array $data): array
    {
        $tweets = [];
        
        foreach ($data as $item) {
            if (isset($item['tweet'])) {
                $tweets[] = $item['tweet'];
            }
        }
        
        return $tweets;
    }
    
    /**
     * Preview tweets before importing
     */
    public function previewTweets(array $tweets, int $limit = 5): array
    {
        $previews = [];
        $count = 0;
        
        foreach ($tweets as $tweet) {
            if ($count >= $limit) break;
            
            try {
                $previews[] = [
                    'id' => $tweet['id_str'] ?? null,
                    'date' => $tweet['created_at'] ?? null,
                    'text' => substr($tweet['full_text'] ?? '', 0, 100) . (strlen($tweet['full_text'] ?? '') > 100 ? '...' : ''),
                    'full_text' => $tweet['full_text'] ?? '',
                    'likes' => $tweet['favorite_count'] ?? 0,
                    'retweets' => $tweet['retweet_count'] ?? 0,
                ];
                $count++;
            } catch (\Exception $e) {
                \Log::warning('Error previewing tweet', [
                    'tweet_id' => $tweet['id_str'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $previews;
    }
    
    /**
     * Import tweets as notes for a user
     */
    public function importTweets(User $user, array $tweets, int $startIndex = 0, int $limit = 50): array
    {
        $imported = [];
        $errors = [];
        
        $tweetsToImport = array_slice($tweets, $startIndex, $limit);
        
        foreach ($tweetsToImport as $tweet) {
            try {
                $importedNote = $this->createNoteFromTweet($user, $tweet);
                $imported[] = [
                    'id' => $tweet['id_str'],
                    'note_id' => $importedNote->id,
                    'text' => substr($tweet['full_text'] ?? '', 0, 50),
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $tweet['id_str'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_imported' => count($imported),
            'total_errors' => count($errors),
            'next_start_index' => $startIndex + $limit
        ];
    }
    
    /**
     * Create a note span from a tweet
     */
    public function createNoteFromTweet(User $user, array $tweet): Span
    {
        // Parse tweet date with better error handling
        // Frontend sends 'date' field, but raw tweets have 'created_at'
        $tweetDateString = $tweet['date'] ?? $tweet['created_at'] ?? null;
        
        if (!$tweetDateString) {
            throw new \Exception('Tweet missing date field. Available keys: ' . implode(', ', array_keys($tweet)));
        }
        
        try {
            // Twitter uses RFC2822 format: "Thu Feb 23 16:55:44 +0000 2023"
            // strtotime handles this format reliably
            $timestamp = strtotime($tweetDateString);
            if ($timestamp === false) {
                throw new \Exception('strtotime failed to parse: ' . $tweetDateString);
            }
            $tweetDate = Carbon::createFromTimestamp($timestamp);
            
            \Log::info('Successfully parsed tweet date', [
                'tweet_id' => $tweet['id_str'] ?? 'unknown',
                'date_string' => $tweetDateString,
                'parsed_date' => $tweetDate->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse tweet date: ' . $e->getMessage());
        }
        
        // Build tags from metadata
        $tags = [];
        if ($tweet['retweeted'] ?? false) {
            $tags[] = '#retweet';
        }
        if (($tweet['favorite_count'] ?? 0) > 0) {
            $tags[] = '#liked:' . $tweet['favorite_count'];
        }
        if (($tweet['retweet_count'] ?? 0) > 0) {
            $tags[] = '#shared:' . $tweet['retweet_count'];
        }
        if (!empty($tweet['entities']['hashtags'] ?? [])) {
            foreach ($tweet['entities']['hashtags'] as $hashtag) {
                $tags[] = '#' . $hashtag['text'];
            }
        }
        $tagsString = implode(' ', $tags);
        
        // Create note span
        $note = new Span([
            'name' => $user->personalSpan->name . ' tweet ' . substr(Str::uuid(), 0, 8),
            'type_id' => 'note',
            'description' => $tweet['full_text'] ?? '',
            'notes' => $tagsString ?: null,
            'state' => 'complete',
            'access_level' => 'private',
            'start_year' => $tweetDate->year,
            'start_month' => $tweetDate->month,
            'start_day' => $tweetDate->day,
            'end_year' => $tweetDate->year,
            'end_month' => $tweetDate->month,
            'end_day' => $tweetDate->day,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'metadata' => [
                'twitter_id' => $tweet['id_str'] ?? null,
                'twitter_source' => $tweet['source'] ?? null,
                'twitter_language' => $tweet['lang'] ?? 'en',
                'import_source' => 'twitter_archive',
                'tweet_date' => $tweetDate->format('Y-m-d')
            ]
        ]);
        
        $note->save();
        
        // Create "created" connection with same date
        $connectionSpan = new Span([
            'name' => $user->personalSpan->name . ' created ' . $note->name,
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'private',
            'start_year' => $tweetDate->year,
            'start_month' => $tweetDate->month,
            'start_day' => $tweetDate->day,
            'end_year' => $tweetDate->year,
            'end_month' => $tweetDate->month,
            'end_day' => $tweetDate->day,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'metadata' => [
                'connection_type' => 'created',
                'timeless' => false
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);
        
        $connectionSpan->save();
        
        Connection::create([
            'parent_id' => $user->personalSpan->id,
            'child_id' => $note->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id
        ]);
        
        return $note;
    }
}
