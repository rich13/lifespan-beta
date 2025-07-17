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
     * Improve existing YAML for a person using AI
     */
    public function improvePersonYaml(string $name, string $existingYaml, string $disambiguation = null): array
    {
        $cacheKey = $this->getCacheKey($name, $disambiguation) . '_improve_' . md5($existingYaml);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $prompt = $this->buildImprovePrompt($name, $existingYaml, $disambiguation);
        
        try {
            $response = $this->openai->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getImproveSystemPrompt()
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
            Log::error('OpenAI API error during improvement', [
                'error' => $e->getMessage(),
                'name' => $name,
                'disambiguation' => $disambiguation
            ]);

            return [
                'success' => false,
                'error' => 'Failed to improve YAML: ' . $e->getMessage()
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
     * Build the user prompt for improving existing YAML
     */
    private function buildImprovePrompt(string $name, string $existingYaml, ?string $disambiguation): string
    {
        $prompt = "Please improve and expand the following biographical YAML record for: {$name}";
        
        if ($disambiguation) {
            $prompt .= " (disambiguation: {$disambiguation})";
        }
        
        $prompt .= "\n\nExisting YAML:\n```yaml\n{$existingYaml}\n```\n\nPlease enhance this YAML by adding missing information, correcting any errors, and expanding with additional biographical details while preserving all existing UUIDs and IDs.";
        
        return $prompt;
    }

    /**
     * Get the system prompt for improving existing YAML
     */
    private function getImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive biographical research assistant tasked with improving and expanding existing YAML records for public figures, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent lifespans and biographical timelines. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing biographical details, career milestones, education, residences, and notable relationships that are publicly documented but not yet included.

### Your task:

When given:
- name of the person,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the Radiohead drummer"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional biographical details

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
  - subtype: must be public_figure
  - gender: if publicly documented
  - birth_name: if different from name
  - occupation: primary occupation as a single string (e.g. "Musician, Composer")
  - nationality: publicly stated or inferred from citizenship
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Person_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant relationships, career connections, and life events that are publicly documented.

You may include the following groups:

- children: each entry includes:
  - name
  - type: person
  - start (if birthdate is known)

- education: each entry includes:
  - name of school/university
  - type: organisation
  - start and end (if available)

- employment: comprehensive career history including all significant employers and positions (excluding band memberships):
  - name of organisation
  - type: organisation
  - start / end (use exact dates when available, approximate if necessary)
  - metadata: include role/title if different from organisation name

- membership: band memberships and group affiliations (for musicians, use this instead of employment):
  - name of band/organisation
  - type: organisation
  - start / end (use exact dates when available, approximate if necessary)
  - metadata: include role/instrument if different from band name

- residence: places where the person has lived (include significant residences with dates):
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when they moved there)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still there)
  - metadata: {}

- relationship: list of confirmed romantic relationships/marriages:
  - name
  - type: person
  - start / end (if available)
  - metadata.relationship: spouse (if married)

- parents: only include if both parent's full names are publicly known
  - name
  - type: person
  - metadata.relationship: mother or father

- has_role: specific roles held at organisations (this is distinct from employment):
  - name: <role title>
  - type: role
  - start: <YYYY or YYYY-MM>
  - end: <YYYY, YYYY-MM, or null>
  - metadata: {}  # leave empty
  - nested_connections:
    - 
      type: at_organisation
      direction: outgoing
      target_name: <Organisation Name>
      target_type: organisation

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant biographical details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Set end to null for living people
9. Include connections whenever you have reliable information - be thorough rather than selective
10. Always quote dates in YAML format
11. Use proper YAML indentation
12. Include sources as a top-level field, not in metadata
13. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
14. If a value (such as a name, date, or organisation) is not publicly known, do not include that field or connection at all
15. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt that defines the YAML structure and rules
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive biographical research assistant tasked with creating detailed YAML records for public figures, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent lifespans and biographical timelines. Your goal is to capture as much accurate, verifiable information as possible about the person's life, career, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant biographical details, career milestones, education, residences, and notable relationships that are publicly documented.

### Your task:

When given:
- name of the person,
- type: person,
- (optional) disambiguation hint (e.g. "the Radiohead drummer"),

You must return a comprehensive YAML block matching the structure and constraints described below.

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
  - subtype: must be public_figure
  - gender: if publicly documented
  - birth_name: if different from name
  - occupation: primary occupation as a single string (e.g. "Musician, Composer")
  - nationality: publicly stated or inferred from citizenship
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Person_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant relationships, career connections, and life events that are publicly documented.

You may include the following groups:

- children: each entry includes:
  - name
  - type: person
  - start (if birthdate is known)

- education: each entry includes:
  - name of school/university
  - type: organisation
  - start and end (if available)

- employment: comprehensive career history including all significant employers and positions (excluding band memberships):
  - name of organisation
  - type: organisation
  - start / end (use exact dates when available, approximate if necessary)
  - metadata: include role/title if different from organisation name

- membership: band memberships and group affiliations (for musicians, use this instead of employment):
  - name of band/organisation
  - type: organisation
  - start / end (use exact dates when available, approximate if necessary)
  - metadata: include role/instrument if different from band name

- residence: places where the person has lived (include significant residences with dates):
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when they moved there)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still there)
  - metadata: {}

- relationship: list of confirmed romantic relationships/marriages:
  - name
  - type: person
  - start / end (if available)
  - metadata.relationship: spouse (if married)

- parents: only include if both parent's full names are publicly known
  - name
  - type: person
  - metadata.relationship: mother or father

- has_role: specific roles held at organisations (this is distinct from employment):
  - name: <role title>
  - type: role
  - start: <YYYY or YYYY-MM>
  - end: <YYYY, YYYY-MM, or null>
  - metadata: {}  # leave empty
  - nested_connections:
    - 
      type: at_organisation
      direction: outgoing
      target_name: <Organisation Name>
      target_type: organisation



---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant biographical details, career milestones, education, and residences that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Set end to null for living people
5. Include connections whenever you have reliable information - be thorough rather than selective
6. For has_role, include all significant roles that are well-documented
7. Always quote dates in YAML format
8. Use proper YAML indentation
9. Include sources as a top-level field, not in metadata
10. Put has_role inside the connections block, not as a separate top-level field
11. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
12. If a value (such as a name, date, or organisation) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unnamed person", "unknown", or similar.
13. Research thoroughly - read full source material, not just summaries or introductions

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