<?php

namespace App\Http\Controllers;

use App\Services\AiYamlCreatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiYamlController extends Controller
{
    private AiYamlCreatorService $aiService;

    public function __construct(AiYamlCreatorService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Generate YAML for a person using AI
     */
    public function generatePersonYaml(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'disambiguation' => 'nullable|string|max:500'
        ]);

        try {
            $result = $this->aiService->generatePersonYaml(
                $validated['name'],
                $validated['disambiguation'] ?? null
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

            // Validate the generated YAML
            $validation = $this->aiService->validateYaml($result['yaml']);
            
            return response()->json([
                'success' => true,
                'yaml' => $result['yaml'],
                'valid' => $validation['valid'],
                'usage' => $result['usage'] ?? null,
                'validation_error' => $validation['valid'] ? null : $validation['error']
            ]);

        } catch (\Exception $e) {
            Log::error('AI YAML generation error', [
                'error' => $e->getMessage(),
                'name' => $validated['name'],
                'disambiguation' => $validated['disambiguation'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate YAML: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the AI YAML generator interface
     */
    public function show()
    {
        return view('ai-yaml-generator.index');
    }
} 