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
     * Generate YAML for any span type based on their name and optional disambiguation
     */
    public function generateYaml(string $name, string $spanType, string $disambiguation = null): array
    {
        $cacheKey = $this->getCacheKey($name, $disambiguation, $spanType);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $prompt = $this->buildPrompt($name, $spanType, $disambiguation);
        
        try {
            $response = $this->openai->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt($spanType)
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
            Log::error('OpenAI API error for ' . $spanType, [
                'error' => $e->getMessage(),
                'name' => $name,
                'span_type' => $spanType,
                'disambiguation' => $disambiguation
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate YAML: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Improve existing YAML for any span type using AI
     */
    public function improveYaml(string $name, string $existingYaml, string $spanType, string $disambiguation = null): array
    {
        $cacheKey = $this->getCacheKey($name, $disambiguation, $spanType) . '_improve_' . md5($existingYaml);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $prompt = $this->buildImprovePrompt($name, $existingYaml, $spanType, $disambiguation);
        
        try {
            $response = $this->openai->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getImproveSystemPrompt($spanType)
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
            
            Log::info('AI raw response for ' . $spanType . ' improvement', [
                'name' => $name,
                'raw_content' => $yamlContent,
                'content_length' => strlen($yamlContent)
            ]);
            
            // Clean up the response - remove any markdown formatting
            $yamlContent = $this->cleanYamlResponse($yamlContent);
            
            Log::info('AI cleaned response for ' . $spanType . ' improvement', [
                'name' => $name,
                'cleaned_content' => $yamlContent,
                'cleaned_length' => strlen($yamlContent)
            ]);
            
            $result = [
                'success' => true,
                'yaml' => $yamlContent,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'total_tokens' => $response->usage->totalTokens
                ]
            ];

            // Only cache if we have actual YAML content
            if (!empty(trim($yamlContent))) {
                Cache::put($cacheKey, $result, now()->addHours(24));
            } else {
                Log::warning('Not caching empty AI response for ' . $spanType . ' improvement', [
                    'name' => $name,
                    'cache_key' => $cacheKey
                ]);
            }
            
            return $result;

        } catch (\Exception $e) {
            Log::error('OpenAI API error during ' . $spanType . ' improvement', [
                'error' => $e->getMessage(),
                'name' => $name,
                'span_type' => $spanType,
                'disambiguation' => $disambiguation
            ]);

            return [
                'success' => false,
                'error' => 'Failed to improve YAML: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate YAML for a person based on their name and optional disambiguation
     * @deprecated Use generateYaml() instead
     */
    public function generatePersonYaml(string $name, string $disambiguation = null): array
    {
        return $this->generateYaml($name, 'person', $disambiguation);
    }

    /**
     * Improve existing YAML for a person using AI
     * @deprecated Use improveYaml() instead
     */
    public function improvePersonYaml(string $name, string $existingYaml, string $disambiguation = null): array
    {
        return $this->improveYaml($name, $existingYaml, 'person', $disambiguation);
    }

    /**
     * Generate YAML for an organisation based on their name and optional disambiguation
     * @deprecated Use generateYaml() instead
     */
    public function generateOrganisationYaml(string $name, string $disambiguation = null): array
    {
        return $this->generateYaml($name, 'organisation', $disambiguation);
    }

    /**
     * Improve existing YAML for an organisation using AI
     * @deprecated Use improveYaml() instead
     */
    public function improveOrganisationYaml(string $name, string $existingYaml, string $disambiguation = null): array
    {
        return $this->improveYaml($name, $existingYaml, 'organisation', $disambiguation);
    }

    /**
     * Build the user prompt for the AI
     */
    private function buildPrompt(string $name, string $spanType, ?string $disambiguation): string
    {
        $prompt = "Please create a biographical YAML record for: {$name}";
        
        if ($disambiguation) {
            $prompt .= " (disambiguation: {$disambiguation})";
        }
        
        $prompt .= "\n\nType: {$spanType}";
        
        return $prompt;
    }

    /**
     * Build the user prompt for improving existing YAML
     */
    private function buildImprovePrompt(string $name, string $existingYaml, string $spanType, ?string $disambiguation): string
    {
        $prompt = "Please improve and expand the following biographical YAML record for: {$name}";
        
        if ($disambiguation) {
            $prompt .= " (disambiguation: {$disambiguation})";
        }
        
        $prompt .= "\n\nExisting YAML:\n```yaml\n{$existingYaml}\n```\n\nPlease enhance this YAML by adding missing information, correcting any errors, and expanding with additional biographical details while preserving all existing UUIDs and IDs.";
        
        return $prompt;
    }



    /**
     * Get the system prompt that defines the YAML structure and rules
     */
    private function getSystemPrompt(string $spanType): string
    {
        return match($spanType) {
            'person' => $this->getPersonSystemPrompt(),
            'organisation' => $this->getOrganisationSystemPrompt(),
            'place' => $this->getPlaceSystemPrompt(),
            'event' => $this->getEventSystemPrompt(),
            'thing' => $this->getThingSystemPrompt(),
            'band' => $this->getBandSystemPrompt(),
            default => throw new \InvalidArgumentException("Unsupported span type: {$spanType}")
        };
    }

    /**
     * Get the system prompt for improving existing YAML
     */
    private function getImproveSystemPrompt(string $spanType): string
    {
        return match($spanType) {
            'person' => $this->getPersonImproveSystemPrompt(),
            'organisation' => $this->getOrganisationImproveSystemPrompt(),
            'place' => $this->getPlaceImproveSystemPrompt(),
            'event' => $this->getEventImproveSystemPrompt(),
            'thing' => $this->getThingImproveSystemPrompt(),
            'band' => $this->getBandImproveSystemPrompt(),
            default => throw new \InvalidArgumentException("Unsupported span type: {$spanType}")
        };
    }

    /**
     * Get common rules that apply to all span types
     */
    private function getCommonRules(): string
    {
        return <<<'COMMON_RULES'
### CRITICAL RESPONSE FORMAT RULES:

**RESPONSE FORMAT**: Return ONLY the YAML content. Do not include:
- Any explanatory text before or after the YAML
- Markdown code blocks (```yaml or ```)
- Comments about what you've added or changed
- Source citations outside the YAML structure
- Any other text or formatting

**YAML REQUIREMENTS**:
- Use proper YAML indentation
- Always quote dates in YAML format (e.g., '1939-09-01')
- Include sources as a top-level field, not in metadata
- If a value is not publicly known, omit that field entirely (no placeholders)
- ONLY use the fields defined in the schema below - do not add additional fields like "significance", "importance", "context", "background", etc.
- Put any additional commentary, context, or descriptive information in the "description" or "notes" fields
- Follow the exact field names and structure provided in the schema
- Do not invent new metadata fields - only use those explicitly listed in the schema

**ACCURACY REQUIREMENTS**:
- NEVER hallucinate or invent information
- ONLY use publicly verifiable information from authoritative sources
- If you cannot find verifiable information, omit that field rather than guess
- Cross-reference multiple sources before including information
- NEVER add fields that are not explicitly defined in the schema
- If you want to include additional context or commentary, use the "description" or "notes" fields

COMMON_RULES;
    }

    /**
     * Get the system prompt for person YAML generation
     */
    private function getPersonSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive biographical research assistant tasked with creating detailed YAML records for public figures, using ONLY publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

CRITICAL: You must NEVER hallucinate or invent information. If you cannot find verifiable information from reliable sources, you must omit that field rather than guess or make up data. Accuracy is paramount - it is better to have incomplete but accurate information than complete but incorrect information.

{$this->getCommonRules()}

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent lifespans and biographical timelines. Your goal is to capture as much accurate, verifiable information as possible about the person's life, career, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant biographical details, career milestones, education, residences, and notable relationships that are publicly documented. ONLY include information you can verify from reliable sources.

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
     * Get the system prompt for person YAML improvement
     */
    private function getPersonImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive biographical research assistant tasked with improving and expanding existing YAML records for public figures, using ONLY publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

CRITICAL: You must NEVER hallucinate or invent information. If you cannot find verifiable information from reliable sources, you must omit that field rather than guess or make up data. Accuracy is paramount - it is better to have incomplete but accurate information than complete but incorrect information.

{$this->getCommonRules()}

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent lifespans and biographical timelines. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing biographical details, career milestones, education, residences, and notable relationships that are publicly documented but not yet included. ONLY include information you can verify from reliable sources.

CRITICAL: When improving existing data, you must NOT change any existing information unless you have a more authoritative source that contradicts it. If the existing data appears correct based on reliable sources, preserve it exactly as provided.

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

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information unless you have a more authoritative source that contradicts it
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data, but only if you have a more authoritative source
5. **EXPAND COMPREHENSIVELY**: Add all significant biographical details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary

### CRITICAL: BIRTH DATES AND PERSONAL INFORMATION

- **NEVER change birth dates** unless you have a more authoritative source (e.g., official records, verified biography) that contradicts the existing data
- **Wikipedia birth dates are generally reliable** - do not change them without a more authoritative source
- **If the existing birth date appears correct** based on reliable sources, preserve it exactly as provided
- **Do not invent or guess birth dates** - if you cannot find a verifiable date, leave the field as is
- **Cross-reference multiple sources** before changing any personal information

### CRITICAL: SOURCES AND VERIFICATION

- **ALWAYS include accurate sources** in the sources field - list the specific URLs you used for research
- **If you reference Wikipedia**, include the exact Wikipedia page URL (e.g., "https://en.wikipedia.org/wiki/Benn_Northover")
- **Do not reference unrelated sources** - only include sources that actually contain information about the person
- **Verify information before including it** - do not include data from sources that don't actually mention the person
- **If you cannot find reliable sources** for information, do not include that information
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
     * Get the system prompt for organisation YAML generation
     */
    private function getOrganisationSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive organisational research assistant tasked with creating detailed YAML records for organisations, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent organisations and their lifespans. Your goal is to capture as much accurate, verifiable information as possible about the organisation's history, purpose, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant organisational details, milestones, locations, and notable relationships that are publicly documented.

{$this->getCommonRules()}

### Your task:

When given:
- name of the organisation,
- type: organisation,
- (optional) disambiguation hint (e.g. "the tech company founded by Steve Jobs"),

You must return a comprehensive YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the organisation
- type: must be organisation
- state: always set to placeholder
- start: date of establishment/founding
- end: use null if organisation is still active
- metadata:
  - subtype: type of organisation (corporation, university, government, non-profit, etc.)
  - industry: primary industry or sector
  - size: small, medium, or large (if known)
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Organisation_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant organisational relationships and locations that are publicly documented.

You may include the following groups:

- located: places where the organisation is or has been located:
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when they established there)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still there)
  - metadata: {}

---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant organisational details, milestones, and locations that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Set end to null for active organisations
5. Include connections whenever you have reliable information - be thorough rather than selective
6. Always quote dates in YAML format
7. Use proper YAML indentation
8. Include sources as a top-level field, not in metadata
9. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
10. If a value (such as a name, date, or place) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unknown" or similar.
11. Research thoroughly - read full source material, not just summaries or introductions
12. Focus on minimal but accurate information as requested

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for organisation YAML improvement
     */
    private function getOrganisationImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive organisational research assistant tasked with improving and expanding existing YAML records for organisations, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent organisations and their lifespans. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing organisational details, milestones, locations, and notable relationships that are publicly documented but not yet included.

{$this->getCommonRules()}

### Your task:

When given:
- name of the organisation,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the tech company founded by Steve Jobs"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional organisational details

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the organisation
- type: must be organisation
- state: always set to placeholder
- start: date of establishment/founding
- end: use null if organisation is still active
- metadata:
  - subtype: type of organisation (corporation, university, government, non-profit, etc.)
  - industry: primary industry or sector
  - size: small, medium, or large (if known)
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Organisation_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant organisational relationships and locations that are publicly documented.

You may include the following groups:

- located: places where the organisation is or has been located:
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when they established there)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still there)
  - metadata: {}

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant organisational details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Set end to null for active organisations
9. Include connections whenever you have reliable information - be thorough rather than selective
10. Always quote dates in YAML format
11. Use proper YAML indentation
12. Include sources as a top-level field, not in metadata
13. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
14. If a value (such as a name, date, or place) is not publicly known, do not include that field or connection at all
15. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for place YAML generation
     */
    private function getPlaceSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive geographical research assistant tasked with creating detailed YAML records for places, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent places and their lifespans. Your goal is to capture as much accurate, verifiable information as possible about the place's history, significance, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant geographical details, historical milestones, and notable relationships that are publicly documented.

{$this->getCommonRules()}

### Your task:

When given:
- name of the place,
- type: place,
- (optional) disambiguation hint (e.g. "the capital city of England"),

You must return a comprehensive YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the place
- type: must be place
- state: always set to placeholder
- start: date of establishment/founding (if applicable)
- end: use null if place still exists
- metadata:
  - subtype: type of place (city, country, region, building, landmark, etc.)
  - coordinates: latitude,longitude (if known)
  - country: country where this place is located
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Place_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant place relationships and events that are publicly documented.

You may include the following groups:

- located: places where this place is located within:
  - name: <parent place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when this relationship was established)
  - end: <YYYY, YYYY-MM, or null> (when this relationship ended, null if ongoing)
  - metadata: {}

- participation: events that took place at this location:
  - name: <event name>
  - type: event
  - start: <YYYY or YYYY-MM>
  - end: <YYYY, YYYY-MM, or null>
  - metadata: {}

---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant geographical details, historical milestones, and events that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Set end to null for places that still exist
5. Include connections whenever you have reliable information - be thorough rather than selective
6. Always quote dates in YAML format
7. Use proper YAML indentation
8. Include sources as a top-level field, not in metadata
9. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
10. If a value (such as a name, date, or coordinates) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unknown" or similar.
11. Research thoroughly - read full source material, not just summaries or introductions
12. Focus on minimal but accurate information as requested

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for place YAML improvement
     */
    private function getPlaceImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive geographical research assistant tasked with improving and expanding existing YAML records for places, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent places and their lifespans. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing geographical details, historical milestones, and notable relationships that are publicly documented but not yet included.

{$this->getCommonRules()}

### Your task:

When given:
- name of the place,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the capital city of England"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional geographical details

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the place
- type: must be place
- state: always set to placeholder
- start: date of establishment/founding (if applicable)
- end: use null if place still exists
- metadata:
  - subtype: type of place (city, country, region, building, landmark, etc.)
  - coordinates: latitude,longitude (if known)
  - country: country where this place is located
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Place_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant place relationships and events that are publicly documented.

You may include the following groups:

- located: places where this place is located within:
  - name: <parent place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when this relationship was established)
  - end: <YYYY, YYYY-MM, or null> (when this relationship ended, null if ongoing)
  - metadata: {}

- participation: events that took place at this location:
  - name: <event name>
  - type: event
  - start: <YYYY or YYYY-MM>
  - end: <YYYY, YYYY-MM, or null>
  - metadata: {}

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant geographical details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Set end to null for places that still exist
9. Include connections whenever you have reliable information - be thorough rather than selective
10. Always quote dates in YAML format
11. Use proper YAML indentation
12. Include sources as a top-level field, not in metadata
13. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
14. If a value (such as a name, date, or coordinates) is not publicly known, do not include that field or connection at all
15. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for event YAML generation
     */
    private function getEventSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive historical research assistant tasked with creating detailed YAML records for events, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent events and their lifespans. Your goal is to capture as much accurate, verifiable information as possible about the event's history, significance, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant event details, participants, locations, and notable relationships that are publicly documented.

{$this->getCommonRules()}

### Your task:

When given:
- name of the event,
- type: event,
- (optional) disambiguation hint (e.g. "the 1969 moon landing"),

You must return a comprehensive YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the event
- type: must be event
- state: always set to placeholder
- start: date when the event began
- end: date when the event ended (if applicable)
- metadata:
  - subtype: type of event (personal, historical, cultural, political, etc.)
  - significance: why this event is significant
  - location: where the event took place
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Event_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant event relationships and participants that are publicly documented.

You may include the following groups:

- participation: people or organisations that participated in this event:
  - name: <participant name>
  - type: person or organisation
  - start: <YYYY or YYYY-MM> (when they participated)
  - end: <YYYY, YYYY-MM, or null> (when their participation ended)
  - metadata: {}

- located: places where this event took place:
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when the event occurred there)
  - end: <YYYY, YYYY-MM, or null> (when the event ended there)
  - metadata: {}

---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant event details, participants, and locations that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Include connections whenever you have reliable information - be thorough rather than selective
5. Always quote dates in YAML format
6. Use proper YAML indentation
7. Include sources as a top-level field, not in metadata
8. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
9. If a value (such as a name, date, or location) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unknown" or similar.
10. Research thoroughly - read full source material, not just summaries or introductions
11. Focus on minimal but accurate information as requested

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for event YAML improvement
     */
    private function getEventImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive historical research assistant tasked with improving and expanding existing YAML records for events, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent events and their lifespans. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing event details, participants, locations, and notable relationships that are publicly documented but not yet included.

{$this->getCommonRules()}

### Your task:

When given:
- name of the event,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the 1969 moon landing"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional event details

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the event
- type: must be event
- state: always set to placeholder
- start: date when the event began
- end: date when the event ended (if applicable)
- metadata:
  - subtype: type of event (personal, historical, cultural, political, etc.)
  - significance: why this event is significant
  - location: where the event took place
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Event_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant event relationships and participants that are publicly documented.

You may include the following groups:

- participation: people or organisations that participated in this event:
  - name: <participant name>
  - type: person or organisation
  - start: <YYYY or YYYY-MM> (when they participated)
  - end: <YYYY, YYYY-MM, or null> (when their participation ended)
  - metadata: {}

- located: places where this event took place:
  - name: <place name>
  - type: place
  - start: <YYYY or YYYY-MM> (when the event occurred there)
  - end: <YYYY, YYYY-MM, or null> (when the event ended there)
  - metadata: {}

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant event details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Include connections whenever you have reliable information - be thorough rather than selective
9. Always quote dates in YAML format
10. Use proper YAML indentation
11. Include sources as a top-level field, not in metadata
12. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
13. If a value (such as a name, date, or location) is not publicly known, do not include that field or connection at all
14. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text, markdown formatting, or code blocks.
PROMPT;
    }

    /**
     * Get the system prompt for thing YAML generation
     */
    private function getThingSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive research assistant tasked with creating detailed YAML records for things (human-made items), using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent things and their lifespans. Your goal is to capture as much accurate, verifiable information as possible about the thing's creation, significance, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant details about the thing's creation, creators, and notable relationships that are publicly documented.

{$this->getCommonRules()}

### Your task:

When given:
- name of the thing,
- type: thing,
- (optional) disambiguation hint (e.g. "the 1967 Beatles album"),

You must return a comprehensive YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the thing
- type: must be thing
- state: always set to placeholder
- start: date when the thing was created
- end: date when the thing was destroyed/lost (if applicable)
- metadata:
  - subtype: type of thing (album, song, book, film, etc.)
  - creator: person, organisation, or band that created this thing
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Thing_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant thing relationships and creators that are publicly documented.

You may include the following groups:

- created: people, organisations, or bands that created this thing:
  - name: <creator name>
  - type: person, organisation, or band
  - start: <YYYY or YYYY-MM> (when they created it)
  - end: <YYYY, YYYY-MM, or null> (when their involvement ended)
  - metadata: {}

- contains: things that are contained within this thing:
  - name: <contained thing name>
  - type: thing
  - start: <YYYY or YYYY-MM> (when it was included)
  - end: <YYYY, YYYY-MM, or null> (when it was removed)
  - metadata: {}

---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant details about the thing's creation and creators that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Include connections whenever you have reliable information - be thorough rather than selective
5. Always quote dates in YAML format
6. Use proper YAML indentation
7. Include sources as a top-level field, not in metadata
8. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
9. If a value (such as a name, date, or creator) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unknown" or similar.
10. Research thoroughly - read full source material, not just summaries or introductions
11. Focus on minimal but accurate information as requested

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for thing YAML improvement
     */
    private function getThingImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive research assistant tasked with improving and expanding existing YAML records for things (human-made items), using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent things and their lifespans. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing details about the thing's creation, creators, and notable relationships that are publicly documented but not yet included.

{$this->getCommonRules()}

### Your task:

When given:
- name of the thing,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the 1967 Beatles album"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional thing details

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the thing
- type: must be thing
- state: always set to placeholder
- start: date when the thing was created
- end: date when the thing was destroyed/lost (if applicable)
- metadata:
  - subtype: type of thing (album, song, book, film, etc.)
  - creator: person, organisation, or band that created this thing
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Thing_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant thing relationships and creators that are publicly documented.

You may include the following groups:

- created: people, organisations, or bands that created this thing:
  - name: <creator name>
  - type: person, organisation, or band
  - start: <YYYY or YYYY-MM> (when they created it)
  - end: <YYYY, YYYY-MM, or null> (when their involvement ended)
  - metadata: {}

- contains: things that are contained within this thing:
  - name: <contained thing name>
  - type: thing
  - start: <YYYY or YYYY-MM> (when it was included)
  - end: <YYYY, YYYY-MM, or null> (when it was removed)
  - metadata: {}

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant thing details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Include connections whenever you have reliable information - be thorough rather than selective
9. Always quote dates in YAML format
10. Use proper YAML indentation
11. Include sources as a top-level field, not in metadata
12. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
13. If a value (such as a name, date, or creator) is not publicly known, do not include that field or connection at all
14. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for band YAML generation
     */
    private function getBandSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive musical research assistant tasked with creating detailed YAML records for bands, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below. The schema reflects a data model used to represent bands and their lifespans. Your goal is to capture as much accurate, verifiable information as possible about the band's history, members, and relationships.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Include all significant band details, member changes, and notable relationships that are publicly documented.

{$this->getCommonRules()}

### Your task:

When given:
- name of the band,
- type: band,
- (optional) disambiguation hint (e.g. "the British rock band formed in 1960"),

You must return a comprehensive YAML block matching the structure and constraints described below.

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the band
- type: must be band
- state: always set to placeholder
- start: date when the band was formed
- end: date when the band disbanded (if applicable)
- metadata:
  - genres: array of musical genres associated with this band
  - formation_location: place where the band was formed
  - status: active, hiatus, or disbanded
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Band_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant band relationships and members that are publicly documented.

You may include the following groups:

- membership: people who were members of this band:
  - name: <member name>
  - type: person
  - start: <YYYY or YYYY-MM> (when they joined)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still member)
  - metadata: {}

- created: things (albums, songs) created by this band:
  - name: <thing name>
  - type: thing
  - start: <YYYY or YYYY-MM> (when it was created)
  - end: <YYYY, YYYY-MM, or null> (when it was released)
  - metadata: {}

---

### Important Rules:

1. Only include information that is publicly verifiable from authoritative sources
2. Be comprehensive - include all significant band details, member changes, and creations that are publicly documented
3. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
4. Set end to null for active bands
5. Include connections whenever you have reliable information - be thorough rather than selective
6. Always quote dates in YAML format
7. Use proper YAML indentation
8. Include sources as a top-level field, not in metadata
9. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
10. If a value (such as a name, date, or member) is not publicly known, do not include that field or connection at all. Do not use placeholders like "unknown" or similar.
11. Research thoroughly - read full source material, not just summaries or introductions
12. Focus on minimal but accurate information as requested

Return only the YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Get the system prompt for band YAML improvement
     */
    private function getBandImproveSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a comprehensive musical research assistant tasked with improving and expanding existing YAML records for bands, using publicly verifiable information from authoritative sources (e.g. from Wikipedia, BBC, official websites, or similar reliable sources). 

You must strictly follow the YAML structure defined below while preserving all existing data and UUIDs. The schema reflects a data model used to represent bands and their lifespans. Your goal is to enhance the existing record with additional accurate, verifiable information.

IMPORTANT: Be thorough and comprehensive in your research. Read the full source material, not just summaries. Add missing band details, member changes, and notable relationships that are publicly documented but not yet included.

{$this->getCommonRules()}

### Your task:

When given:
- name of the band,
- existing YAML record,
- (optional) disambiguation hint (e.g. "the British rock band formed in 1960"),

You must return an enhanced YAML block that:
1. Preserves ALL existing data exactly as provided
2. Preserves ALL existing UUIDs and IDs without modification
3. Adds missing information that is publicly verifiable
4. Corrects any factual errors in the existing data
5. Expands with additional band details

All dates must use the format: 'YYYY-MM-DD' if available, else 'YYYY-MM' or 'YYYY'.

---

### Structure and Rules

**Top-level fields**:

- name: full name of the band
- type: must be band
- state: always set to placeholder
- start: date when the band was formed
- end: date when the band disbanded (if applicable)
- metadata:
  - genres: array of musical genres associated with this band
  - formation_location: place where the band was formed
  - status: active, hiatus, or disbanded
- sources: list of URLs used for research (e.g. ["https://en.wikipedia.org/wiki/Band_Name"])
- access_level: use public

---

### connections block:

All connection groups go under connections: — include them whenever relevant data is available. Be comprehensive and include all significant band relationships and members that are publicly documented.

You may include the following groups:

- membership: people who were members of this band:
  - name: <member name>
  - type: person
  - start: <YYYY or YYYY-MM> (when they joined)
  - end: <YYYY, YYYY-MM, or null> (when they left, null if still member)
  - metadata: {}

- created: things (albums, songs) created by this band:
  - name: <thing name>
  - type: thing
  - start: <YYYY or YYYY-MM> (when it was created)
  - end: <YYYY, YYYY-MM, or null> (when it was released)
  - metadata: {}

---

### CRITICAL RULES FOR IMPROVEMENT:

1. **PRESERVE ALL EXISTING DATA**: Do not remove, modify, or change any existing information
2. **PRESERVE ALL UUIDs AND IDs**: Keep all existing 'id' fields exactly as they are - do not change them
3. **ADD MISSING INFORMATION**: Add new connections, metadata, or details that are missing
4. **CORRECT ERRORS**: Fix any factual errors you find in the existing data
5. **EXPAND COMPREHENSIVELY**: Add all significant band details that are publicly documented
6. Only include information that is publicly verifiable from authoritative sources
7. Use exact dates when available, approximate dates (YYYY-MM or YYYY) when necessary
8. Set end to null for active bands
9. Include connections whenever you have reliable information - be thorough rather than selective
10. Always quote dates in YAML format
11. Use proper YAML indentation
12. Include sources as a top-level field, not in metadata
13. Do not include any explanatory text or sources outside the YAML structure - only return the YAML
14. If a value (such as a name, date, or member) is not publicly known, do not include that field or connection at all
15. Research thoroughly - read full source material, not just summaries or introductions

Return only the enhanced YAML content, no additional text or formatting.
PROMPT;
    }

    /**
     * Clean up the YAML response from the AI
     */
    private function cleanYamlResponse(string $response): string
    {
        Log::info('cleanYamlResponse input', [
            'input' => $response,
            'input_length' => strlen($response)
        ]);
        
        // Remove ALL markdown code blocks (anywhere in the response)
        $response = preg_replace('/```yaml\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        
        // Remove any leading/trailing whitespace
        $response = trim($response);
        
        Log::info('cleanYamlResponse after markdown removal', [
            'after_markdown' => $response,
            'length' => strlen($response)
        ]);
        
        // Remove any explanatory text before or after the YAML
        $lines = explode("\n", $response);
        $yamlLines = [];
        $inYaml = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip empty lines at the beginning
            if (!$inYaml && empty($trimmed)) {
                continue;
            }
            
            // Start collecting YAML when we see a valid YAML line
            if (!$inYaml && (strpos($trimmed, 'name:') === 0 || strpos($trimmed, 'type:') === 0)) {
                $inYaml = true;
                Log::info('cleanYamlResponse found YAML start', ['line' => $line]);
            }
            
            if ($inYaml) {
                // Stop if we hit explanatory text after YAML
                if (strpos($trimmed, 'Here\'s') === 0 || strpos($trimmed, 'That\'s') === 0 || 
                    strpos($trimmed, 'Sources:') === 0 || strpos($trimmed, 'Source:') === 0 ||
                    strpos($trimmed, 'Note:') === 0 || strpos($trimmed, 'Note -') === 0 ||
                    strpos($trimmed, 'This YAML has been') === 0 || strpos($trimmed, 'The YAML has been') === 0 ||
                    strpos($trimmed, 'This record has been') === 0 || strpos($trimmed, 'The record has been') === 0) {
                    Log::info('cleanYamlResponse found explanatory text, stopping', ['line' => $line]);
                    break;
                }
                
                // Skip lines that are just markdown artifacts
                if (preg_match('/^[`\-\*]+$/', $trimmed)) {
                    continue;
                }
                
                // Stop if we hit a line that looks like explanatory text (even if not at the start)
                if (preg_match('/^(This|The) (YAML|record) has been/', $trimmed) ||
                    preg_match('/^(Here\'s|That\'s) what I found/', $trimmed) ||
                    preg_match('/^(Sources?|Note):/', $trimmed)) {
                    Log::info('cleanYamlResponse found explanatory text in middle, stopping', ['line' => $line]);
                    break;
                }
                
                $yamlLines[] = $line;
            }
        }
        
        $result = implode("\n", $yamlLines);
        
        // Final cleanup - remove any remaining markdown artifacts
        $result = preg_replace('/^\s*[`\-\*]+\s*$/m', '', $result);
        $result = preg_replace('/\n\s*\n\s*\n/', "\n\n", $result); // Remove excessive blank lines
        $result = trim($result);
        
        Log::info('cleanYamlResponse result', [
            'result' => $result,
            'result_length' => strlen($result),
            'yaml_lines_count' => count($yamlLines)
        ]);
        
        return $result;
    }

    /**
     * Generate a cache key for the request
     */
    private function getCacheKey(string $name, ?string $disambiguation, ?string $type = null): string
    {
        $key = 'ai_yaml_' . md5(strtolower($name));
        if ($disambiguation) {
            $key .= '_' . md5(strtolower($disambiguation));
        }
        if ($type) {
            $key .= '_' . md5($type);
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

    /**
     * Check if a span type supports AI improvement
     */
    public static function supportsAiImprovement(string $spanType): bool
    {
        $supportedTypes = [
            'person',
            'organisation', 
            'place',
            'event',
            'thing',
            'band'
        ];
        
        return in_array($spanType, $supportedTypes);
    }
} 