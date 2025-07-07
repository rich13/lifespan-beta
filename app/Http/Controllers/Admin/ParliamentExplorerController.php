<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UKParliamentApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ParliamentExplorerController extends Controller
{
    protected UKParliamentApiService $parliamentService;

    public function __construct(UKParliamentApiService $parliamentService)
    {
        $this->middleware(['auth', 'admin']);
        $this->parliamentService = $parliamentService;
    }

    /**
     * Show the Parliament Explorer interface
     */
    public function index()
    {
        return view('admin.import.parliament.index');
    }

    /**
     * Search for members in the UK Parliament API
     */
    public function search(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'house' => 'nullable|in:1,2', // 1 = Commons, 2 = Lords
            'skip' => 'integer|min:0',
            'take' => 'integer|min:1|max:50'
        ]);

        $name = $request->input('name');
        $house = $request->input('house', '1'); // Default to Commons
        $skip = $request->input('skip', 0);
        $take = $request->input('take', 20);

        try {
            $results = $this->parliamentService->searchMembers([
                'Name' => $name,
                'House' => $house
            ], $skip, $take);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search members: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member details including synopsis
     */
    public function getMember(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer'
        ]);

        $memberId = $request->input('member_id');

        try {
            $memberData = $this->parliamentService->getMember($memberId);
            $synopsis = $this->parliamentService->getMemberSynopsis($memberId);
            
            if (empty($memberData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            $value = $memberData['value'] ?? [];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $memberId,
                    'name' => $value['nameDisplayAs'] ?? '',
                    'full_name' => $value['nameFullTitle'] ?? '',
                    'gender' => $value['gender'] ?? '',
                    'party' => $value['latestParty']['name'] ?? '',
                    'party_abbreviation' => $value['latestParty']['abbreviation'] ?? '',
                    'constituency' => $value['latestHouseMembership']['membershipFrom'] ?? '',
                    'membership_start' => $value['latestHouseMembership']['membershipStartDate'] ?? '',
                    'membership_end' => $value['latestHouseMembership']['membershipEndDate'] ?? '',
                    'membership_status' => $value['latestHouseMembership']['membershipStatus']['statusDescription'] ?? '',
                    'synopsis' => $synopsis,
                    'thumbnail_url' => $value['thumbnailUrl'] ?? '',
                    'raw_data' => $memberData
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get member data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run a SPARQL query against the UK Parliament endpoint
     */
    public function runSparqlQuery(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:5',
        ]);

        $sparql = $request->input('query');
        $endpoint = 'https://api.parliament.uk/sparql';

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/sparql-results+json',
            ])->get($endpoint, [
                'query' => $sparql
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'SPARQL endpoint error: ' . $response->status(),
                    'details' => $response->body(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'SPARQL query failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import a Parliament member as a person
     */
    public function importMember(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer'
        ]);

        $memberId = $request->input('member_id');

        try {
            // Get the member data
            $memberData = $this->parliamentService->getMember($memberId);
            $synopsis = $this->parliamentService->getMemberSynopsis($memberId);
            
            if (empty($memberData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            $value = $memberData['value'] ?? [];
            
            // Create a simple person span
            $user = Auth::user();
            $span = new \App\Models\Span();
            $span->name = $value['nameDisplayAs'] ?? 'Unknown Member';
            $span->type = 'person';
            $span->description = $synopsis ?: 'UK Parliament member';
            $span->user_id = $user->id;
            $span->access_level = 'public';
            
            // Add metadata
            $metadata = [
                'source' => 'UK Parliament API',
                'parliament_id' => $memberId,
                'party' => $value['latestParty']['name'] ?? null,
                'constituency' => $value['latestHouseMembership']['membershipFrom'] ?? null,
                'gender' => $value['gender'] ?? null,
                'parliament_api_data' => $memberData
            ];
            $span->metadata = $metadata;
            
            $span->save();

            Log::info('Parliament member imported', [
                'member_id' => $memberId,
                'span_id' => $span->id,
                'name' => $span->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member imported successfully',
                'data' => [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'parliament_id' => $memberId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import Parliament member', [
                'error' => $e->getMessage(),
                'member_id' => $memberId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import member: ' . $e->getMessage()
            ], 500);
        }
    }
} 