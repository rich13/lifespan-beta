<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataFixerController extends Controller
{
    /**
     * Show the data fixer interface
     */
    public function index()
    {
        return view('admin.tools.fixer.index');
    }

    /**
     * Find spans with invalid date ranges (end before start)
     */
    public function findInvalidDateRanges(Request $request)
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0'
        ]);

        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);

        $invalidSpans = DB::table('spans')
            ->select([
                'id',
                'name',
                'type_id',
                'start_year',
                'start_month',
                'start_day',
                'end_year',
                'end_month',
                'end_day',
                'state',
                DB::raw("metadata->>'subtype' as subtype")
            ])
            ->whereNotNull('start_year')
            ->whereNotNull('end_year')
            ->whereRaw('end_year < start_year')
            ->orderBy('start_year', 'desc')
            ->orderBy('end_year', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $totalCount = DB::table('spans')
            ->whereNotNull('start_year')
            ->whereNotNull('end_year')
            ->whereRaw('end_year < start_year')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'spans' => $invalidSpans,
                'total_count' => $totalCount,
                'current_offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
    }

    /**
     * Fix a specific span's date range by setting end date to null
     */
    public function fixSpanDateRange(Request $request)
    {
        try {
            $request->validate([
                'span_id' => 'required|uuid|exists:spans,id'
            ]);

            $span = Span::find($request->span_id);
        
        if (!$span) {
            return response()->json([
                'success' => false,
                'message' => 'Span not found'
            ], 404);
        }

        // Check if this span actually has invalid dates
        if ($span->start_year === null || $span->end_year === null || $span->end_year >= $span->start_year) {
            return response()->json([
                'success' => false,
                'message' => 'Span does not have invalid date range'
            ], 400);
        }

        // Store the old values for potential rollback
        $oldEndYear = $span->end_year;
        $oldEndMonth = $span->end_month;
        $oldEndDay = $span->end_day;

        // Fix the dates
        $span->update([
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Span date range fixed successfully',
            'data' => [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'old_end_year' => $oldEndYear,
                'new_end_year' => null,
                'fixed_at' => now()
            ]
        ]);
        
        } catch (\Exception $e) {
            \Log::error('Data fixer error: ' . $e->getMessage(), [
                'span_id' => $request->span_id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fix span: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics about data issues
     */
    public function getStats()
    {
        $stats = [
            'invalid_date_ranges' => DB::table('spans')
                ->whereNotNull('start_year')
                ->whereNotNull('end_year')
                ->whereRaw('end_year < start_year')
                ->count(),
            
            'spans_with_null_start' => DB::table('spans')
                ->whereNull('start_year')
                ->whereNotNull('end_year')
                ->count(),
            
            'spans_with_null_end' => DB::table('spans')
                ->whereNotNull('start_year')
                ->whereNull('end_year')
                ->count(),
            
            'total_spans' => DB::table('spans')->count(),
            
            'spans_with_dates' => DB::table('spans')
                ->whereNotNull('start_year')
                ->orWhereNotNull('end_year')
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Find parents who died before their children were born
     */
    public function findParentsDiedBeforeChildren(Request $request)
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0'
        ]);

        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);

        // Find family connections where parent died before child was born
        $invalidFamilyConnections = DB::table('connections as c')
            ->join('spans as parent', 'c.parent_id', '=', 'parent.id')
            ->join('spans as child', 'c.child_id', '=', 'child.id')
            ->join('spans as cs', 'c.connection_span_id', '=', 'cs.id')
            ->select([
                'c.id as connection_id',
                'parent.id as parent_id',
                'parent.name as parent_name',
                'parent.start_year as parent_birth_year',
                'parent.end_year as parent_death_year',
                'child.id as child_id',
                'child.name as child_name',
                'child.start_year as child_birth_year',
                'child.end_year as child_death_year',
                'cs.start_year as relationship_start_year',
                'cs.end_year as relationship_end_year'
            ])
            ->where('c.type_id', 'family')
            ->whereNotNull('parent.end_year')  // Parent has death date
            ->whereNotNull('child.start_year') // Child has birth date
            ->whereRaw('parent.end_year < child.start_year') // Parent died before child born
            ->orderBy('parent.end_year', 'desc')
            ->orderBy('child.start_year', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $totalCount = DB::table('connections as c')
            ->join('spans as parent', 'c.parent_id', '=', 'parent.id')
            ->join('spans as child', 'c.child_id', '=', 'child.id')
            ->where('c.type_id', 'family')
            ->whereNotNull('parent.end_year')
            ->whereNotNull('child.start_year')
            ->whereRaw('parent.end_year < child.start_year')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'connections' => $invalidFamilyConnections,
                'total_count' => $totalCount,
                'current_offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
    }
}
