<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaSearchController extends Controller
{
    /**
     * Search Wikipedia for pages matching the query
     */
    public function search(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2|max:200',
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $query = $validated['query'];
            $limit = $validated['limit'] ?? 10;

            Log::info('Wikipedia search started', [
                'query' => $query,
                'limit' => $limit
            ]);

            // Use Wikipedia's OpenSearch API
            // Wikipedia requires a User-Agent header as per their robot policy
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => config('app.user_agent')
                ])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action' => 'opensearch',
                    'format' => 'json',
                    'search' => $query,
                    'limit' => $limit,
                    'namespace' => 0, // Main namespace only (articles)
                    'redirects' => 'resolve'
                ]);

            if (!$response->successful()) {
                Log::error('Wikipedia API returned non-successful status', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Wikipedia search failed'
                ], 500);
            }

            $data = $response->json();
            
            Log::info('Wikipedia API response received', [
                'data_type' => gettype($data),
                'data_count' => is_array($data) ? count($data) : 0
            ]);
            
            // Wikipedia OpenSearch returns: [query, [titles], [descriptions], [urls]]
            if (!is_array($data) || count($data) < 4) {
                Log::error('Invalid Wikipedia response format', [
                    'data' => $data
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid Wikipedia response format'
                ], 500);
            }

            $titles = $data[1] ?? [];
            $descriptions = $data[2] ?? [];
            $urls = $data[3] ?? [];

            // Build results array
            $results = [];
            for ($i = 0; $i < count($titles); $i++) {
                $results[] = [
                    'title' => $titles[$i] ?? '',
                    'description' => $descriptions[$i] ?? '',
                    'url' => $urls[$i] ?? ''
                ];
            }

            Log::info('Wikipedia search completed', [
                'query' => $query,
                'results_count' => count($results)
            ]);

            return response()->json([
                'success' => true,
                'query' => $query,
                'results' => $results
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let Laravel handle validation exceptions
            throw $e;
        } catch (\Exception $e) {
            Log::error('Wikipedia search error', [
                'query' => $request->get('query', 'unknown'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while searching Wikipedia: ' . $e->getMessage()
            ], 500);
        }
    }
}
