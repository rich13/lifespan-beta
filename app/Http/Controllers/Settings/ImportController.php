<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TwitterArchiveImporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    protected $twitterImporter;
    
    public function __construct(TwitterArchiveImporterService $twitterImporter)
    {
        $this->middleware('auth');
        $this->twitterImporter = $twitterImporter;
    }
    
    /**
     * Show Twitter import page
     */
    public function showTwitter()
    {
        return view('settings.import.twitter');
    }
    
    /**
     * Handle Twitter archive upload
     */
    public function uploadTwitter(Request $request)
    {
        $request->validate([
            'tweets_file' => 'required|file|max:50000' // 50MB max, no MIME restriction
        ]);
        
        try {
            $file = $request->file('tweets_file');
            
            // Validate file extension more strictly
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension !== 'js') {
                return response()->json([
                    'success' => false,
                    'message' => 'File must have a .js extension (got: .' . $extension . ')'
                ], 400);
            }
            
            $path = $file->store('twitter-imports', 'local');
            $fullPath = storage_path('app/' . $path);
            
            // Check if file was stored successfully
            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store uploaded file'
                ], 400);
            }
            
            // Parse tweets
            $data = $this->twitterImporter->parseTweetsFile($fullPath);
            $tweets = $this->twitterImporter->extractTweets($data);
            
            // Get full tweet details for frontend display
            $tweetDetails = array_map(function ($tweet) {
                return [
                    'id_str' => $tweet['id_str'] ?? null,
                    'date' => $tweet['created_at'] ?? null,
                    'full_text' => $tweet['full_text'] ?? '',
                    'likes' => $tweet['favorite_count'] ?? 0,
                    'retweets' => $tweet['retweet_count'] ?? 0,
                ];
            }, $tweets);
            
            // Store full tweets in session for later import
            session([
                'twitter_import_path' => $path,
                'twitter_import_tweets_count' => count($tweets),
                'twitter_import_tweets' => $tweets // Store full tweet objects
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'total_tweets' => count($tweets),
                'all_tweets' => $tweetDetails // Send simplified version to frontend
            ]);
        } catch (\Exception $e) {
            \Log::error('Twitter import upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error parsing file: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Import a single tweet
     */
    public function importTweet(Request $request)
    {
        $request->validate([
            'tweet' => 'required|array'
        ]);

        $user = auth()->user();
        if (!$user || !$user->personalSpan) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or has no personal span'
            ], 401);
        }

        try {
            $tweet = $request->input('tweet');
            
            // Create note from tweet
            $note = $this->twitterImporter->createNoteFromTweet($user, $tweet);
            
            return response()->json([
                'success' => true,
                'message' => 'Tweet imported successfully',
                'note_id' => $note->id,
                'note_name' => $note->name,
                'note_slug' => $note->slug,
                'note_url' => route('spans.show', $note->slug)
            ]);
        } catch (\Exception $e) {
            \Log::error('Twitter tweet import error', [
                'error' => $e->getMessage(),
                'tweet_id' => $request->input('tweet.id_str'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error importing tweet: ' . $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Import tweets in batch
     */
    public function importTwitterBatch(Request $request)
    {
        $request->validate([
            'start_index' => 'required|integer|min:0',
            'limit' => 'required|integer|min:1|max:100'
        ]);
        
        try {
            $path = session('twitter_import_path');
            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No import session found. Please upload file again.'
                ], 400);
            }
            
            // Parse tweets
            $data = $this->twitterImporter->parseTweetsFile(storage_path('app/' . $path));
            $tweets = $this->twitterImporter->extractTweets($data);
            
            // Import batch
            $result = $this->twitterImporter->importTweets(
                Auth::user(),
                $tweets,
                $request->input('start_index'),
                $request->input('limit')
            );
            
            return response()->json([
                'success' => true,
                'imported' => $result['total_imported'],
                'errors' => $result['total_errors'],
                'next_start_index' => $result['next_start_index'],
                'total_tweets' => count($tweets),
                'error_details' => $result['errors']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error importing tweets: ' . $e->getMessage()
            ], 500);
        }
    }
}
