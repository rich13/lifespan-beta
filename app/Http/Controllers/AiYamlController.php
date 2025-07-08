<?php

namespace App\Http\Controllers;

use App\Services\AiYamlCreatorService;
use App\Services\SlackNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiYamlController extends Controller
{
    private ?AiYamlCreatorService $aiService = null;
    private SlackNotificationService $slackService;

    public function __construct(SlackNotificationService $slackService)
    {
        $this->slackService = $slackService;
        // Apply admin middleware only to show and getPlaceholderSpans methods
        $this->middleware('admin')->only(['show', 'getPlaceholderSpans']);
    }

    /**
     * Get the AI service, creating it only when needed
     */
    private function getAiService(): AiYamlCreatorService
    {
        if ($this->aiService === null) {
            $this->aiService = app(AiYamlCreatorService::class);
        }
        return $this->aiService;
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
            $aiService = $this->getAiService();
            $result = $aiService->generatePersonYaml(
                $validated['name'],
                $validated['disambiguation'] ?? null
            );

            if (!$result['success']) {
                // Send Slack notification for failed AI generation
                $this->slackService->notifyAiYamlGenerated($validated['name'], false, $result['error']);
                
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

            // Send Slack notification for successful AI generation
            $this->slackService->notifyAiYamlGenerated($validated['name'], true);

            // Validate the generated YAML
            $validation = $aiService->validateYaml($result['yaml']);
            
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

            // Send Slack notification for failed AI generation
            $this->slackService->notifyAiYamlGenerated($validated['name'], false, $e->getMessage());

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

    /**
     * Get placeholder spans that are not connections
     */
    public function getPlaceholderSpans()
    {
        try {
            $placeholders = \App\Models\Span::where('state', 'placeholder')
                ->where('type_id', 'person') // Only show person spans for now
                ->orderBy('created_at', 'desc')
                ->limit(20) // Limit to 20 most recent
                ->get(['id', 'name', 'type_id', 'created_at'])
                ->map(function ($span) {
                    return [
                        'id' => $span->id,
                        'name' => $span->name,
                        'type_id' => $span->type_id,
                        'created_at' => $span->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'placeholders' => $placeholders
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching placeholder spans', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch placeholder spans: ' . $e->getMessage()
            ], 500);
        }
    }
} 