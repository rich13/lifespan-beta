<?php

namespace App\Services;

use OpenAI\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

class AiYamlCreatorService
{
    private Client $openai;
    private string $model = 'gpt-4';

    public function __construct()
    {
        $apiKey = config('services.openai.api_key');
        if (!$apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $this->openai = \OpenAI::client($apiKey);
    }

    /**
     * Generate YAML for a person based on their name and optional disambiguation
     */
    public function generatePersonYaml(string $name, string $disambiguation = null): array
    {
        $cacheKey = $this->getCacheKey($name, $disambiguation);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $prompt = $this->buildPrompt($name, $disambiguation);
        
        try {
            $response = $this->openai->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3, // Lower temperature for more consistent output
                'max_tokens' => 2000
            ]);

            $yamlContent = $response->choices[0]->message->content;
            
            // Clean up the response - remove any markdown formatting
            $yamlContent = $this->cleanYamlResponse($yamlContent);
            
            $result = [
                'success' => true,
                'yaml' => $yamlContent,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'total_tokens' => $response->usage->totalTokens
                ]
            ];

            // Cache the result for 24 hours
            Cache::put($cacheKey, $result, now()->addHours(24));
            
            return $result;

        } catch (\Exception $e) {
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'name' => $name,
                'disambiguation' => $disambiguation
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate YAML: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build the user prompt for the AI
     */
    private function buildPrompt(string $name, ?string $disambiguation): string
    {
        $prompt = "Please create a biographical YAML record for: {$name}";
        
        if ($disambiguation) {
            $prompt .= " (disambiguation: {$disambiguation})";
        }
        
        $prompt .= "\n\nType: person";
        
        return $prompt;
    }

    /**
     * Get the system prompt that defines the YAML structure and rules
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a structured data assistant tasked with creating biographical YAML records for entities, using publicly verifiable information only (e.g. from Wikipedia, BBC, or similar sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent lifespans and biographical timelines. Include only information you can confirm from public sources. Do not include any information that is assumed, inferred, or hallucinated.

The YAML must be valid.

### Your task:

When given:
- name of the person,
- type: person,
- (optional) disambiguation hint (e.g. "the Today programme presenter"),

You must return a YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the person
- type: must be person
- state: always set to placeholder
- start: date of birth 
- end: use null if person is alive
- metadata:
  - gender: if publicly documented
  - birth_name: if different from name
  - occupation: summarised from public bios
  - nationality: publicly stated or inferred from citizenship
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Person_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — only include them if relevant data is available.

You may include the following groups:

- children: each entry includes:
  - name
  - type: person
  - start_date (if birthdate is known)

- education: each entry includes:
  - name of school/university
  - type: organisation
  - start_date and end_date (if available)

- employment: summarised roles at major employers (use one entry per organisation):
  - name of organisation
  - type: organisation
  - start_date / end_date (approximate if necessary)

- residence: notable known places of residence, each with:
  - name: place
  - type: place
  - start_date / end_date (if known)

- relationship: list of confirmed romantic relationships/marriages:
  - name
  - type: person
  - start_date / end_date (if available)
  - metadata.relationship: spouse (if married)

- parents: only include if both parent's full names are publicly known
  - name
  - type: person
  - metadata.relationship: mother or father

- has_role: specific roles held at organisations (this is distinct from employment):
  - name: <role title>
  - type: role
  - start_date: <YYYY or YYYY-MM>
  - end_date: <YYYY, YYYY-MM, or null>
  - metadata: {}  # leave empty
  - nested_connections:
    - 
      type: at_organisation
      direction: outgoing
      target_name: <Organisation Name>
      target_type: organisation

---

### Important Rules:

1. Only include information that is publicly verifiable
2. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
3. Set end to null for living people
4. Include connections only if you have reliable information
5. For has_role, focus on significant roles that are well-documented
6. Always quote dates in YAML format
7. Use proper YAML indentation
8. Include sources as a top-level field, not in metadata
9. Put has_role inside the connections block, not as a separate top-level field
10. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
11. If a value (such as a name, date, or organisation) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unnamed person", "unknown", or similar.

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Clean up the YAML response from the AI
     */
    private function cleanYamlResponse(string $response): string
    {
        // Remove markdown code blocks if present
        $response = preg_replace('/```yaml\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        
        // Remove any leading/trailing whitespace
        $response = trim($response);
        
        // Remove any explanatory text before or after the YAML
        $lines = explode("\n", $response);
        $yamlLines = [];
        $inYaml = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Start collecting YAML when we see a valid YAML line
            if (!$inYaml && (strpos($trimmed, 'name:') === 0 || strpos($trimmed, 'type:') === 0)) {
                $inYaml = true;
            }
            
            if ($inYaml) {
                // Stop if we hit explanatory text after YAML
                if (strpos($trimmed, 'Here\'s') === 0 || strpos($trimmed, 'That\'s') === 0 || 
                    strpos($trimmed, 'Sources:') === 0 || strpos($trimmed, 'Source:') === 0) {
                    break;
                }
                $yamlLines[] = $line;
            }
        }
        
        return implode("\n", $yamlLines);
    }

    /**
     * Generate a cache key for the request
     */
    private function getCacheKey(string $name, ?string $disambiguation): string
    {
        $key = 'ai_yaml_' . md5(strtolower($name));
        if ($disambiguation) {
            $key .= '_' . md5(strtolower($disambiguation));
        }
        return $key;
    }

    /**
     * Validate that the generated YAML is syntactically correct
     */
    public function validateYaml(string $yaml): array
    {
        try {
            $parsed = Yaml::parse($yaml);
            if ($parsed === false) {
                return [
                    'valid' => false,
                    'error' => 'Invalid YAML syntax'
                ];
            }
            
            return [
                'valid' => true,
                'parsed' => $parsed
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'YAML parsing error: ' . $e->getMessage()
            ];
        }
    }
} 