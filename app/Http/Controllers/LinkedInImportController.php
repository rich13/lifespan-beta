<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Services\LinkedInImportService;
use Carbon\Carbon;

class LinkedInImportController extends Controller
{
    protected LinkedInImportService $linkedInService;

    public function __construct(LinkedInImportService $linkedInService)
    {
        $this->middleware(['auth']);
        $this->linkedInService = $linkedInService;
    }

    /**
     * Show the LinkedIn import settings page
     */
    public function index()
    {
        return view('settings.import.linkedin.index');
    }

    /**
     * Preview LinkedIn CSV data
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048', // 2MB max
        ]);

        try {
            $file = $request->file('csv_file');
            $user = Auth::user();
            
            $previewData = $this->linkedInService->previewCsv($file, $user);
            
            return response()->json([
                'success' => true,
                'preview' => $previewData
            ]);
            
        } catch (\Exception $e) {
            Log::error('LinkedIn CSV preview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import LinkedIn CSV data
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'update_existing' => 'boolean',
        ]);

        try {
            $file = $request->file('csv_file');
            $updateExisting = $request->boolean('update_existing', false);
            
            $user = Auth::user();
            
            $result = $this->linkedInService->importCsv($file, $user, $updateExisting);
            
            return response()->json([
                'success' => true,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('LinkedIn import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }


} 