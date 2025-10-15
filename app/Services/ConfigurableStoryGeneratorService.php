<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ConfigurableStoryGeneratorService
{
    protected $templates;
    protected $currentUser;
    protected $contextDate;

    public function __construct()
    {
        $this->templates = config('story_templates');
        $this->currentUser = auth()->user();
    }

    /**
     * Generate a story for a connection span (e.g., 'during' phase connection span)
     */
    private function generateConnectionSpanStory(Span $connectionSpan): ?array
    {
        // Find the connection that uses this span as its connection_span_id
        $connection = Connection::where('connection_span_id', $connectionSpan->id)->first();
        if (!$connection) {
            return null;
        }

        // Build connection story sentence exclusively here (no microstory)
        $sentence = '';
        if ($connection->type_id === 'during') {
            // Determine which end is the phase span (non-connection) and which end is the education connection span (type=connection)
            $phaseSpan = null;
            $educationConnSpan = null;
            if ($connection->parent && $connection->parent->type_id === 'connection') {
                $educationConnSpan = $connection->parent;
                $phaseSpan = $connection->child && $connection->child->type_id !== 'connection' ? $connection->child : $phaseSpan;
            }
            if ($connection->child && $connection->child->type_id === 'connection') {
                $educationConnSpan = $connection->child;
                $phaseSpan = $connection->parent && $connection->parent->type_id !== 'connection' ? $connection->parent : $phaseSpan;
            }

            // Resolve the person and organisation via the education connection that uses the education connection span
            $educationConn = $educationConnSpan
                ? Connection::where('type_id', 'education')
                    ->where('connection_span_id', $educationConnSpan->id)
                    ->with(['parent','child'])
                    ->first()
                : null;

            $person = $educationConn?->parent;       // subject
            $organisation = $educationConn?->child;  // object

            // Prefer dates from the current connection span; fallback to phase span dates
            $startHtml = $this->formatDateLink($connectionSpan->start_year, $connectionSpan->start_month, $connectionSpan->start_day);
            $endHtml = $this->formatDateLink($connectionSpan->end_year, $connectionSpan->end_month, $connectionSpan->end_day);
            if (!$startHtml && $phaseSpan) {
                $startHtml = $this->formatDateLink($phaseSpan->start_year, $phaseSpan->start_month, $phaseSpan->start_day);
            }
            if (!$endHtml && $phaseSpan) {
                $endHtml = $this->formatDateLink($phaseSpan->end_year, $phaseSpan->end_month, $phaseSpan->end_day);
            }

            $subjectHtml = $person
                ? '<a href="' . route('spans.show', $person) . '" class="text-decoration-none" title="' . e($person->name) . '">' . e($person->name) . '</a>'
                : 'They';
            $phaseHtml = $phaseSpan
                ? '<a href="' . route('spans.show', $phaseSpan) . '" class="text-decoration-none" title="' . e($phaseSpan->name) . '">' . e($phaseSpan->name) . '</a>'
                : 'a phase';
            $orgHtml = $organisation
                ? '<a href="' . route('spans.show', $organisation) . '" class="text-decoration-none" title="' . e($organisation->name) . '">' . e($organisation->name) . '</a>'
                : 'an organisation';

            $sentence = "$subjectHtml was in $phaseHtml at $orgHtml";
            if ($startHtml && $endHtml) {
                $sentence .= " between $startHtml and $endHtml.";
            } elseif ($startHtml) {
                $sentence .= " from $startHtml.";
            } else {
                $sentence .= ".";
            }
        } else {
            // Generic connection sentence if needed later
            $sentence = e($connectionSpan->name);
        }

        return [
            'title' => $connectionSpan->name,
            'paragraphs' => [$sentence],
            'metadata' => [],
        ];
    }

    /**
     * Condition: hasEducationPhases
     */
    public function hasEducationPhases(Span $span): bool
    {
        if ($span->type_id !== 'person') {
            return false;
        }
        // Person → education connections
        $educationConnections = $span->connectionsAsSubject()
            ->where('type_id', 'education')
            ->with('connectionSpan')
            ->get();
        foreach ($educationConnections as $edu) {
            $connSpan = $edu->connectionSpan;
            if (!$connSpan) continue;
            // Look for during connections that reference this connection span
            $phaseLinks = Connection::where('type_id', 'during')
                ->where(function($q) use ($connSpan) {
                    $q->where('child_id', $connSpan->id)
                      ->orWhere('parent_id', $connSpan->id);
                })
                ->exists();
            if ($phaseLinks) return true;
        }
        return false;
    }

    // person_at_date support: hasEducationPhaseAtDate
    public function hasEducationPhaseAtDate(Span $span): bool
    {
        return (bool) $this->getEducationPhaseAtDate($span);
    }

    // person_at_date support: getEducationPhaseAtDate -> returns linked phase name (neutral) or null
    public function getEducationPhaseAtDate(Span $span): ?string
    {
        if (!$this->contextDate) return null;
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) return null;

        // Find active education connection (most recent) as in getEducationAtDate
        $educationConnection = $span->connectionsAsSubject()
            ->where('connections.type_id', 'education')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();

        // If not found as subject, try as object (some data may be inverse)
        if (!$educationConnection) {
            $educationConnection = $span->connectionsAsObject()
                ->where('connections.type_id', 'education')
                ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                    $query->where(function ($q) use ($contextDate) {
                        $q->whereNull('start_year')
                          ->orWhere(function ($q2) use ($contextDate) {
                              $q2->where('start_year', '<=', $contextDate->format('Y'))
                                 ->where(function ($q3) use ($contextDate) {
                                     $q3->whereNull('end_year')
                                        ->orWhere('end_year', '>=', $contextDate->format('Y'));
                                 });
                          });
                    });
                })
                ->with(['parent', 'connectionSpan'])
                ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
                ->orderBy('connection_spans.start_year', 'desc')
                ->orderBy('connection_spans.start_month', 'desc')
                ->orderBy('connection_spans.start_day', 'desc')
                ->select('connections.*')
                ->first();
        }

        $educationSpan = $educationConnection?->connectionSpan;
        if (!$educationSpan) return null;

        // Find a phase 'during' connection active on the context date linking to this education-connection span
        $phaseDuring = Connection::where('type_id', 'during')
            ->where(function($q) use ($educationSpan){
                $q->where('child_id', $educationSpan->id)->orWhere('parent_id', $educationSpan->id);
            })
            ->with(['parent','child','connectionSpan'])
            ->get()
            ->first(function($c) use ($contextDate) {
                // The 'during' connection has its own connection span with the date range
                $span = $c->connectionSpan;
                if (!$span) return false;
                
                // Compare by full date if available, falling back to year
                $y = (int)$contextDate->format('Y');
                $m = (int)$contextDate->format('m');
                $d = (int)$contextDate->format('d');
                $startOk = true; $endOk = true;
                if ($span->start_year) {
                    if ($span->start_month && $span->start_day) {
                        $startOk = [$y,$m,$d] >= [$span->start_year, $span->start_month, $span->start_day];
                    } else {
                        $startOk = $y >= $span->start_year;
                    }
                }
                if ($span->end_year) {
                    if ($span->end_month && $span->end_day) {
                        $endOk = [$y,$m,$d] <= [$span->end_year, $span->end_month, $span->end_day];
                    } else {
                        $endOk = $y <= $span->end_year;
                    }
                }
                return $startOk && $endOk;
            });

        if (!$phaseDuring) return null;
        $phaseSpan = ($phaseDuring->parent && $phaseDuring->parent->type_id !== 'connection') ? $phaseDuring->parent : $phaseDuring->child;
        return $phaseSpan ? $this->makeSpanLink($phaseSpan->name, $phaseSpan) : null;
    }

    /**
     * Data: getEducationPhasesSentence
     * Example: "Richard was in Class 7 at St Saviours between September 1980 and July 1981."
     */
    public function getEducationPhasesSentence(Span $span): string
    {
        if ($span->type_id !== 'person') return '';
        $sentences = [];
        // Gather person → education connections
        $educationConnections = $span->connectionsAsSubject()
            ->where('type_id', 'education')
            ->with(['connectionSpan', 'child'])
            ->get();

        foreach ($educationConnections as $edu) {
            $connSpan = $edu->connectionSpan; // the dated connection span
            $org = $edu->child;              // organisation
            if (!$connSpan || !$org) continue;

            // Find during connections from phase → connSpan
            $phases = Connection::where('type_id', 'during')
                ->where(function($q) use ($connSpan){
                    $q->where('child_id', $connSpan->id)->orWhere('parent_id', $connSpan->id);
                })
                ->with(['parent','child'])
                ->get()
                ->sortBy(function($c){
                    $p = $c->getEffectiveSortDate();
                    return sprintf('%08d-%02d-%02d', $p[0] ?? 99999999, $p[1] ?? 99, $p[2] ?? 99);
                });

            foreach ($phases as $i => $link) {
                // Phase span is the non-connection end
                $phaseSpan = ($link->parent && $link->parent->type_id !== 'connection') ? $link->parent : $link->child;
                if (!$phaseSpan) continue;
                $phaseName = $phaseSpan->name;

                // Build dates using helper methods from story generator if available
                $startDate = $this->formatDate($phaseSpan->start_year, $phaseSpan->start_month, $phaseSpan->start_day);
                $endDate = $this->formatDate($phaseSpan->end_year, $phaseSpan->end_month, $phaseSpan->end_day, true);

                $subject = e($span->name);
                $organisationLink = '<a href="' . route('spans.show', $org) . '" class="text-decoration-none" title="' . e($org->name) . '">' . e($org->name) . '</a>';
                $sentence = "$subject was in " . e($phaseName) . " at $organisationLink";
                if ($startDate && $endDate) {
                    $sentence .= " between $startDate and $endDate.";
                } elseif ($startDate) {
                    $sentence .= " from $startDate.";
                } else {
                    $sentence .= ".";
                }
                $sentences[] = $sentence;
            }
        }

        return implode(' ', $sentences);
    }

    private function formatDate($y, $m, $d, bool $end = false): ?string
    {
        if (!$y) return null;
        // Month/day optional; fall back to month names if present
        if ($m && $d) {
            return Carbon::createFromDate($y, $m, $d)->format('F j, Y');
        }
        if ($m) {
            return Carbon::createFromDate($y, $m, 1)->format('F Y');
        }
        return (string)$y;
    }

    private function formatDateLink($y, $m, $d): ?string
    {
        if (!$y) return null;
        $display = $this->formatDate($y, $m, $d);
        // Build date route like /date/YYYY[-MM[-DD]] if available
        $parts = [$y];
        if ($m) $parts[] = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        if ($d) $parts[] = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        $dateStr = implode('-', $parts);
        return '<a href="' . route('date.explore', ['date' => $dateStr]) . '" class="text-decoration-none">' . e($display) . '</a>';
    }

    /**
     * Generate a story for a span using configuration templates
     */
    public function generateStory(Span $span): array
    {
        // Handle connection spans explicitly (e.g., during phase connections)
        if ($span->type_id === 'connection') {
            $connectionStory = $this->generateConnectionSpanStory($span);
            if ($connectionStory) {
                return $connectionStory;
            }
        }

        $spanType = $span->type_id;
        $spanSubtype = $span->metadata['subtype'] ?? null;
        $debug = [];
        
        // First try to find templates for the specific subtype
        $templateKey = $spanSubtype ? $spanType . '_' . $spanSubtype : $spanType;
        $template = $this->templates[$templateKey] ?? null;
        
        // If no subtype-specific template found, fall back to type-based template
        if (!$template) {
            $template = $this->templates[$spanType] ?? null;
        }
        
        if (!$template) {
            $debug['error'] = "No templates found for span type: {$spanType}" . ($spanSubtype ? " subtype: {$spanSubtype}" : "");
            $debug['used_fallback'] = true;
            $fallbackSentence = $this->generateFallbackSentence($span);
            return [
                'title' => "The Story of {$span->name}",
                'paragraphs' => $this->groupIntoSentences([$fallbackSentence]),
                'metadata' => $this->generateMetadata($span),
                'debug' => $debug,
            ];
        }

        $debug['templates_found'] = count($template['sentences']);
        $debug['template_key'] = $templateKey;
        
        // Check if we have a story template
        if (!isset($template['story_template'])) {
            $debug['error'] = "No story template found for span type: {$spanType}" . ($spanSubtype ? " subtype: {$spanSubtype}" : "");
            $debug['used_fallback'] = true;
            $fallbackSentence = $this->generateFallbackSentence($span);
            return [
                'title' => "The Story of {$span->name}",
                'paragraphs' => $this->groupIntoSentences([$fallbackSentence]),
                'metadata' => $this->generateMetadata($span),
                'debug' => $debug,
            ];
        }

        $storyTemplate = $template['story_template'];
        $debug['story_template'] = $storyTemplate;
        
        // Extract sentence keys from the story template
        preg_match_all('/\{([^}]+)\}/', $storyTemplate, $matches);
        $sentenceKeys = $matches[1] ?? [];
        $debug['sentence_keys'] = $sentenceKeys;
        
        $generatedSentences = [];
        $storyText = $storyTemplate;
        
        foreach ($sentenceKeys as $sentenceKey) {
            if (!isset($template['sentences'][$sentenceKey])) {
                $debug['sentences'][$sentenceKey] = [
                    'included' => false,
                    'reason' => 'Sentence key not found in template'
                ];
                // Remove the placeholder from the story text
                $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                continue;
            }
            
            $sentenceConfig = $template['sentences'][$sentenceKey];
            $sentenceDebug = [
                'key' => $sentenceKey,
                'template' => $sentenceConfig['template'] ?? 'No template',
                'condition' => $sentenceConfig['condition'] ?? 'No condition',
            ];
            
            // Check condition
            $conditionPassed = $this->shouldIncludeSentence($span, $sentenceConfig);
            $sentenceDebug['condition_passed'] = $conditionPassed;
            
            if ($conditionPassed) {
                // Get data for sentence
                $data = $this->getSentenceData($span, $sentenceConfig['data_methods']);
                $sentenceDebug['data'] = $data;
                
                // Check if we have required data
                $hasRequiredData = $this->hasRequiredData($data, $sentenceConfig);
                $sentenceDebug['has_required_data'] = $hasRequiredData;
                
                if ($hasRequiredData) {
                    $selectedTemplate = $this->selectTemplate($sentenceConfig, $data, $span);
                    $sentenceDebug['selected_template'] = $selectedTemplate;
                    
                    $sentence = $this->replacePlaceholders($selectedTemplate, $data);
                    $sentenceDebug['final_sentence'] = $sentence;
                    
                    if ($sentence) {
                        $generatedSentences[$sentenceKey] = $sentence;
                        $sentenceDebug['included'] = true;
                    } else {
                        $sentenceDebug['included'] = false;
                        $sentenceDebug['reason'] = 'Sentence generation failed';
                        // Remove the placeholder if sentence generation failed
                        $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                    }
                } else {
                    $sentenceDebug['included'] = false;
                    $sentenceDebug['reason'] = 'Missing required data';
                    $sentenceDebug['missing_data'] = array_filter($data, function($value) {
                        return $value === null || $value === '';
                    });
                    // Remove the placeholder if we don't have required data
                    $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                }
            } else {
                $sentenceDebug['included'] = false;
                $sentenceDebug['reason'] = 'Condition failed';
                // Remove the placeholder if condition failed
                $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
            }
            
            $debug['sentences'][$sentenceKey] = $sentenceDebug;
        }
        
        // Replace placeholders in the story template with generated sentences
        foreach ($generatedSentences as $key => $sentence) {
            $storyText = str_replace("{{$key}}", $sentence, $storyText);
        }
        
        // Remove any remaining placeholders (for sentences that didn't generate)
        $storyText = preg_replace('/\{[^}]+\}/', '', $storyText);
        
        // Clean up the text (remove extra spaces, etc.)
        $storyText = $this->cleanupTemplateText($storyText);
        
        // Add spaces between sentence placeholders that were replaced
        $storyText = preg_replace('/\.([A-Z])/', '. $1', $storyText);
        
        // Split into sentences and filter out empty ones
        $sentences = array_filter(array_map('trim', explode('.', $storyText)), function($sentence) {
            return !empty($sentence);
        });

        // Capitalize the first letter of each sentence and add periods back
        $sentences = array_map(function($sentence) {
            $sentence = ltrim($sentence);
            return mb_strtoupper(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1) . '.';
        }, $sentences);
        
        $debug['total_sentences_generated'] = count($sentences);
        $debug['final_story_text'] = $storyText;

        // If no sentences were generated, use the fallback message
        if (empty($sentences)) {
            $fallbackSentence = $this->generateFallbackSentence($span);
            $sentences = [$fallbackSentence];
            $debug['used_fallback'] = true;
        }

        return [
            'title' => "The Story of {$span->name}",
            'paragraphs' => $this->groupIntoSentences($sentences),
            'metadata' => $this->generateMetadata($span),
            'debug' => $debug,
        ];
    }

    /**
     * Generate a story for a span at a specific date
     */
    public function generateStoryAtDate(Span $span, string $date): array
    {
        // Set the context date for this story generation
        $this->contextDate = $date;
        
        $spanType = $span->type_id;
        $debug = [];
        
        // For at-date stories, use a special template key
        $templateKey = $spanType . '_at_date';
        $template = $this->templates[$templateKey] ?? null;
        
        // If no at-date template found, fall back to regular story generation
        if (!$template) {
            $this->contextDate = null; // Reset context
            return $this->generateStory($span);
        }

        $debug['templates_found'] = count($template['sentences']);
        $debug['template_key'] = $templateKey;
        $debug['context_date'] = $date;
        
        // Check if we have a story template
        if (!isset($template['story_template'])) {
            $debug['error'] = "No story template found for at-date span type: {$spanType}";
            $debug['used_fallback'] = true;
            $this->contextDate = null; // Reset context
            return $this->generateStory($span);
        }

        $storyTemplate = $template['story_template'];
        $debug['story_template'] = $storyTemplate;
        
        // Extract sentence keys from the story template
        preg_match_all('/\{([^}]+)\}/', $storyTemplate, $matches);
        $sentenceKeys = $matches[1] ?? [];
        $debug['sentence_keys'] = $sentenceKeys;
        
        $generatedSentences = [];
        $storyText = $storyTemplate;
        
        foreach ($sentenceKeys as $sentenceKey) {
            if (!isset($template['sentences'][$sentenceKey])) {
                $debug['sentences'][$sentenceKey] = [
                    'included' => false,
                    'reason' => 'Sentence key not found in template'
                ];
                // Remove the placeholder from the story text
                $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                continue;
            }
            
            $sentenceConfig = $template['sentences'][$sentenceKey];
            $sentenceDebug = [
                'key' => $sentenceKey,
                'template' => $sentenceConfig['template'] ?? 'No template',
                'condition' => $sentenceConfig['condition'] ?? 'No condition',
            ];
            
            // Check condition
            $conditionPassed = $this->shouldIncludeSentence($span, $sentenceConfig);
            $sentenceDebug['condition_passed'] = $conditionPassed;
            
            if ($conditionPassed) {
                // Get data for sentence
                $data = $this->getSentenceData($span, $sentenceConfig['data_methods']);
                $sentenceDebug['data'] = $data;
                
                // Check if we have required data
                $hasRequiredData = $this->hasRequiredData($data, $sentenceConfig);
                $sentenceDebug['has_required_data'] = $hasRequiredData;
                
                if ($hasRequiredData) {
                    $selectedTemplate = $this->selectTemplate($sentenceConfig, $data, $span);
                    $sentenceDebug['selected_template'] = $selectedTemplate;
                    
                    $sentence = $this->replacePlaceholders($selectedTemplate, $data);
                    $sentenceDebug['final_sentence'] = $sentence;
                    
                    if ($sentence) {
                        $generatedSentences[$sentenceKey] = $sentence;
                        $storyText = str_replace("{{$sentenceKey}}", $sentence, $storyText);
                    } else {
                        // Remove the placeholder if sentence generation failed
                        $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                    }
                } else {
                    // Try fallback template if main template doesn't have required data
                    if (isset($sentenceConfig['fallback_template'])) {
                        $selectedTemplate = $this->selectTemplate($sentenceConfig, $data, $span);
                        $sentenceDebug['selected_template'] = $selectedTemplate;
                        
                        $sentence = $this->replacePlaceholders($selectedTemplate, $data);
                        $sentenceDebug['final_sentence'] = $sentence;
                        
                        if ($sentence) {
                            $generatedSentences[$sentenceKey] = $sentence;
                            $storyText = str_replace("{{$sentenceKey}}", $sentence, $storyText);
                        } else {
                            // Remove the placeholder if fallback sentence generation failed
                            $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                        }
                    } else {
                        // Remove the placeholder if we don't have required data and no fallback
                        $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
                    }
                }
            } else {
                // Remove the placeholder if condition failed
                $storyText = str_replace("{{$sentenceKey}}", '', $storyText);
            }
            
            $debug['sentences'][$sentenceKey] = $sentenceDebug;
        }
        
        // Split into sentences and filter out empty ones
        $sentences = array_filter(array_map('trim', explode('.', $storyText)), function($sentence) {
            return !empty($sentence);
        });

        // Capitalize the first letter of each sentence and add periods back
        $sentences = array_map(function($sentence) {
            $sentence = ltrim($sentence);
            return mb_strtoupper(mb_substr($sentence, 0, 1)) . mb_substr($sentence, 1) . '.';
        }, $sentences);
        
        $debug['total_sentences_generated'] = count($sentences);
        $debug['final_story_text'] = $storyText;

        // If no sentences were generated, use the fallback message
        if (empty($sentences)) {
            $fallbackSentence = $this->generateFallbackSentence($span);
            $sentences = [$fallbackSentence];
            $debug['used_fallback'] = true;
        }

        // Reset context
        $this->contextDate = null;

        return [
            'title' => "The Story of {$span->name} on {$date}",
            'paragraphs' => $this->groupIntoSentences($sentences),
            'metadata' => $this->generateMetadata($span),
            'debug' => $debug,
        ];
    }

    /**
     * Check if a sentence should be included based on its condition
     */
    protected function shouldIncludeSentence(Span $span, array $sentenceConfig): bool
    {
        $condition = $sentenceConfig['condition'] ?? null;
        
        if (!$condition) {
            return true;
        }

        return $this->callConditionMethod($span, $condition);
    }

    /**
     * Generate a single sentence from template
     */
    protected function generateSentence(Span $span, array $sentenceConfig): ?string
    {
        $data = $this->getSentenceData($span, $sentenceConfig['data_methods']);
        
        // Check if we have all required data
        if (!$this->hasRequiredData($data, $sentenceConfig)) {
            return null;
        }

        $template = $this->selectTemplate($sentenceConfig, $data, $span);
        
        return $this->replacePlaceholders($template, $data);
    }

    /**
     * Get data for sentence generation
     */
    protected function getSentenceData(Span $span, array $dataMethods): array
    {
        $data = [];
        
        foreach ($dataMethods as $key => $method) {
            $data[$key] = $this->callDataMethod($span, $method);
        }
        
        return $data;
    }

    /**
     * Check if we have required data for the sentence
     */
    protected function hasRequiredData(array $data, array $sentenceConfig): bool
    {
        // Get the template to understand what placeholders are expected
        $template = $sentenceConfig['template'] ?? '';
        
        // Extract placeholders from the template
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        $requiredPlaceholders = $matches[1] ?? [];
        
        // Check if we have the essential data for this sentence
        $hasEssentialData = true;
        
        foreach ($requiredPlaceholders as $placeholder) {
            $value = $data[$placeholder] ?? null;
            
            // Handle debug arrays (extract the actual value)
            if (is_array($value) && isset($value['value'])) {
                $value = $value['value'];
            }
            
            // Some placeholders are optional (like birth_location, formation_location)
            $optionalPlaceholders = ['birth_location', 'formation_location', 'duration', 'count'];
            
            if (!in_array($placeholder, $optionalPlaceholders) && ($value === null || $value === '')) {
                $hasEssentialData = false;
                break;
            }
        }
        
        return $hasEssentialData;
    }

    /**
     * Select the appropriate template based on data
     */
    protected function selectTemplate(array $sentenceConfig, array $data, Span $span): string
    {
        // Check for single/empty templates first
        if (isset($sentenceConfig['empty_template']) && $this->isEmptyData($data)) {
            return $sentenceConfig['empty_template'];
        }
        
        if (isset($sentenceConfig['single_template']) && $this->isSingleData($data)) {
            return $sentenceConfig['single_template'];
        }
        
        // Check for deceased template (for age sentences when person is dead)
        if (isset($sentenceConfig['deceased_template']) && $this->shouldUseDeceasedTemplate($data, $span)) {
            return $sentenceConfig['deceased_template'];
        }
        
        // Check for fallback template
        if (isset($sentenceConfig['fallback_template']) && $this->shouldUseFallback($data)) {
            return $sentenceConfig['fallback_template'];
        }
        
        // Check if we have missing data and try to find a template that works
        $missingData = $this->getMissingData($data);
        if (!empty($missingData) && isset($sentenceConfig['fallback_template'])) {
            return $sentenceConfig['fallback_template'];
        }
        
        return $sentenceConfig['template'];
    }

    /**
     * Replace placeholders in template with actual data
     */
    protected function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            // Handle debug arrays (extract the actual value)
            if (is_array($value) && isset($value['value'])) {
                $value = $value['value'];
            }
            // Convert value to string, handling null and other types
            $stringValue = match (true) {
                is_null($value) => '',
                is_string($value) => trim($value), // Trim to prevent spaces from being inserted
                is_int($value) => (string) $value,
                is_float($value) => (string) $value,
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => json_encode($value), // Handle arrays by converting to JSON
                default => (string) $value,
            };
            $template = str_replace("{{$key}}", $stringValue, $template);
        }
        // Clean up any awkward text that might result from missing data
        $template = $this->cleanupTemplateText($template);
        return $template;
    }

    /**
     * Clean up awkward text patterns that result from missing data
     *
     * Temporarily disabled for debugging: returns text unchanged.
     */
    protected function cleanupTemplateText(string $text): string
    {
        // Remove all whitespace (spaces, tabs, newlines) from within href attribute values
        $text = preg_replace_callback('/href="([^"]*)"/', function ($matches) {
            $cleanUrl = preg_replace('/\s+/', '', $matches[1]);
            return 'href="' . $cleanUrl . '"';
        }, $text);
        return $text;
    }

    /**
     * Get pronoun for a span based on gender and type
     */
    protected function getPronoun(Span $span, string $type): string
    {
        $gender = $span->getMeta('gender');
        
        return match ($gender) {
            'male' => match ($type) {
                'subject' => 'he',
                'object' => 'him',
                'possessive' => 'his',
                'reflexive' => 'himself',
                default => 'he',
            },
            'female' => match ($type) {
                'subject' => 'she',
                'object' => 'her',
                'possessive' => 'her',
                'reflexive' => 'herself',
                default => 'she',
            },
            default => match ($type) {
                'subject' => 'they',
                'object' => 'them',
                'possessive' => 'their',
                'reflexive' => 'themselves',
                default => 'they',
            },
        };
    }

    /**
     * Call a condition method on the span
     */
    protected function callConditionMethod(Span $span, string $condition): bool
    {
        return match ($condition) {
            'hasStartYear' => $span->start_year !== null,
            'hasResidences' => $this->getResidences($span)->isNotEmpty(),
            'hasEducation' => $this->getEducation($span)->isNotEmpty(),
            'hasWork' => $this->getWork($span)->isNotEmpty(),
            'hasRelationships' => $this->getRelationships($span)->isNotEmpty(),
            'hasCurrentRelationship' => $this->getCurrentRelationshipData($span, 'person') !== null,
            'hasParents' => $span->parents->isNotEmpty(),
            'hasChildren' => $span->children->isNotEmpty(),
            'hasSiblings' => $span->siblings()->count() > 0,
            'hasBandMemberships' => $this->getBandMemberships($span)->isNotEmpty(),
            'hasMembers' => $this->getBandMembers($span)->isNotEmpty(),
            'hasDiscography' => $this->getDiscography($span)->isNotEmpty(),
            'hasRoles' => $this->getPastRoles($span)->isNotEmpty(),
            'hasCurrentRole' => $this->getCurrentRole($span) !== null,
            'hasCreator' => $this->getCreator($span) !== null,
            'hasTracks' => $this->getTracks($span)->isNotEmpty(),
            'hasAlbum' => $this->getAlbum($span) !== null,
            'hasArtists' => ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'track')
                ? ($this->getArtists($span)->isNotEmpty() || ($this->getAlbum($span) !== null && $this->getArtists($span)->isNotEmpty()))
                : $this->getArtists($span)->isNotEmpty(),
            'hasDuration' => $this->getDuration($span) !== null,
            'hasTrackArtist' => $this->hasTrackArtist($span),
            'hasTrackReleaseDate' => $this->hasTrackReleaseDate($span),
            'hasTrackAlbum' => $this->hasTrackAlbum($span),
            'hasFeaturedSpan' => $this->hasFeaturedSpan($span),
            'hasPhotoDate' => $this->hasPhotoDate($span),
            'hasFeaturedSpanAgeAtPhotoDate' => $this->hasFeaturedSpanAgeAtPhotoDate($span),
            'hasPlaqueFeatures' => $this->hasPlaqueFeatures($span),
            'hasPlaqueLocation' => $this->hasPlaqueLocation($span),
            'hasAgeAtDate' => $this->hasAgeAtDate($span),
            'hasCurrentActivitiesAtDate' => $this->hasCurrentActivitiesAtDate($span),
            'hasRecentEventsAtDate' => $this->hasRecentEventsAtDate($span),
            'hasUpcomingEventsAtDate' => $this->hasUpcomingEventsAtDate($span),
            'hasResidenceAtDate' => $this->hasResidenceAtDate($span),
            'hasEmploymentAtDate' => $this->hasEmploymentAtDate($span),
            'hasEducationAtDate' => $this->hasEducationAtDate($span),
            'hasEducationPhaseAtDate' => $this->hasEducationPhaseAtDate($span),
            'hasRelationshipAtDate' => $this->hasRelationshipAtDate($span),
            default => false,
        };
    }

    /**
     * Call a data method on the span
     */
    protected function callDataMethod(Span $span, string $method)
    {
        $result = match ($method) {
            'getName' => $this->makeSpanLink($span->name, $span),
            'getFormattedStartDate' => $span->formatted_start_date,
            'getFormattedEndDate' => $span->formatted_end_date,
            'getHumanReadableBirthDate' => $this->getHumanReadableBirthDate($span),
            'getBirthPreposition' => $this->getBirthPreposition($span),
            'getHumanReadableFormationDate' => $this->getHumanReadableFormationDate($span),
            'getPronoun' => $this->getPronoun($span, 'subject'),
            'getPronounCapitalized' => ucfirst($this->getPronoun($span, 'subject')),
            'getPossessivePronoun' => $this->getPronoun($span, 'possessive'),
            'getPossessivePronounCapitalized' => ucfirst($this->getPronoun($span, 'possessive')),
            'getObjectPronoun' => $this->getPronoun($span, 'object'),
            'getReflexivePronoun' => $this->getPronoun($span, 'reflexive'),
            'getBirthLocation' => $this->getBirthLocation($span),
            'getFormationLocation' => $this->getFormationLocation($span),
            'getResidencePlaces' => $this->getResidencePlaces($span),
            'getLongestResidencePlace' => $this->getLongestResidenceData($span, 'place'),
            'getLongestResidenceDuration' => $this->getLongestResidenceData($span, 'duration'),
            'getEducationInstitutions' => $this->getEducationInstitutions($span),
            'getWorkOrganisations' => $this->getWorkOrganisations($span),
            'getMostRecentJobOrganisation' => $this->getMostRecentJobOrganisation($span),
            'getRelationshipCount' => $this->getRelationships($span)->count(),
            'getFirstRelationshipPartner' => ($firstRel = $this->getRelationships($span)->first()) ? $this->makeSpanLink($firstRel['person'], $firstRel['person_span'] ?? null) : null,
            'getCurrentRelationshipPartner' => ($currentRel = $this->getCurrentRelationshipData($span, 'person')) ? $this->makeSpanLink($currentRel['name'] ?? $currentRel, $currentRel['span'] ?? null) : null,
            'getLongestRelationshipPartner' => ($longestRel = $this->getLongestRelationshipData($span, 'person')) ? $this->makeSpanLink($longestRel['name'] ?? $longestRel, $longestRel['span'] ?? null) : null,
            'getLongestRelationshipDuration' => ($longestRel = $this->getLongestRelationshipData($span, 'person')) ? ($longestRel['duration'] ?? null) : null,
            'getParentNames' => $this->getParentNames($span),
            'getChildCount' => $span->children->count(),
            'getFirstChildName' => ($child = $span->children->first()) ? $this->makeSpanLink($child->name, $child) : null,
            'getChildNames' => $this->getChildNames($span),
            'getSiblingCount' => $span->siblings()->count(),
            'getFirstSiblingName' => ($sibling = $span->siblings()->first()) ? $this->makeSpanLink($sibling->name, $sibling) : null,
            'getSiblingNames' => $this->getSiblingNames($span),
            'getBandMembershipNames' => $this->getBandMembershipNames($span),
            'getFirstBandMembershipName' => ($firstBand = $this->getBandMemberships($span)->first()) ? $this->makeSpanLink($firstBand['band'], $firstBand['band_span']) : null,
            'getObjectIsVerb' => $this->getObjectIsVerb($span),
            'getMemberCount' => $this->getBandMembers($span)->count(),
            'getBandMemberNames' => $this->getBandMemberNames($span),
            'getRoleNames' => $this->getRoleNames($span),
            'getFirstRoleName' => ($firstRole = $this->getPastRoles($span)->first()) ? $this->makeSpanLink($firstRole['role'], $firstRole['role_span']) : null,
            'getCurrentRole' => $this->getCurrentRole($span),
            'getTenseVerb' => $this->getTenseVerb($span),
            'getIsVerb' => $this->getIsVerb($span),
            'getHasVerb' => $this->getHasVerb($span),
            'getHaveVerb' => $this->getHaveVerb($span),
            'getWasVerb' => $this->getWasVerb($span),
            'getHadVerb' => $this->getHadVerb($span),
            'getAlbumCount' => $this->getDiscography($span)->count(),
            'getLatestAlbum' => ($latestAlbum = $this->getDiscography($span)->sortByDesc('date')->first()) ? $this->makeSpanLink($latestAlbum['thing'], $latestAlbum['thing_span'] ?? null) : null,
            'getFirstAlbum' => ($firstAlbum = $this->getDiscography($span)->first()) ? $this->makeSpanLink($firstAlbum['thing'], $firstAlbum['thing_span'] ?? null) : null,
            'getHumanReadableReleaseDate' => $this->getHumanReadableReleaseDate($span),
            'getCreator' => $this->getCreator($span),
            'getTrackCount' => $this->getTracks($span)->count(),
            'getAlbum' => $this->getAlbum($span),
            'getArtistNames' => $this->getArtistNames($span),
            'getFirstArtistName' => $this->getFirstArtistName($span),
            'getDuration' => $this->getDuration($span),
            'getAge' => $this->getAge($span),
            'getTrackArtist' => $this->getTrackArtist($span),
            'getTrackReleaseDate' => $this->getTrackReleaseDate($span),
            'getTrackAlbum' => $this->getTrackAlbum($span),
            'getFeaturedSpanName' => $this->getFeaturedSpanName($span),
            'getPhotoDate' => $this->getPhotoDate($span),
            'getPhotoDatePreposition' => $this->getPhotoDatePreposition($span),
            'getFeaturedSpanAgeAtPhotoDate' => $this->getFeaturedSpanAgeAtPhotoDate($span),
            'getPlaqueFeatures' => $this->getPlaqueFeatures($span),
            'getPlaqueLocation' => $this->getPlaqueLocation($span),
            'getAtDateDisplay' => $this->getAtDateDisplay($span),
            'getAgeAtDate' => $this->getAgeAtDate($span),
            'getCurrentActivitiesAtDate' => $this->getCurrentActivitiesAtDate($span),
            'getRecentEventsAtDate' => $this->getRecentEventsAtDate($span),
            'getUpcomingEventsAtDate' => $this->getUpcomingEventsAtDate($span),
            'getResidenceAtDate' => $this->getResidenceAtDate($span),
            'getEmploymentRoleAtDate' => $this->getEmploymentRoleAtDate($span),
            'getEmploymentOrganisationAtDate' => $this->getEmploymentOrganisationAtDate($span),
            'getEducationAtDate' => $this->getEducationAtDate($span),
            'getEducationPhaseAtDate' => $this->getEducationPhaseAtDate($span),
            'getRelationshipAtDate' => $this->getRelationshipAtDate($span),
            default => null,
        };

        // Add debug info for birth_location method (only in development)
        // Temporarily disabled to prevent memory issues with people who have many connections
        // if ($method === 'getBirthLocation' && app()->environment('local', 'development')) {
        //     $debug = $this->getBirthLocationDebug($span);
        //     if ($debug && $result !== null) {
        //         // Only add debug info if we have a non-null result
        //         $result = [
        //             'value' => $result,
        //             'debug' => $debug
        //         ];
        //     }
        // }

        return $result;
    }

    protected function makeSpanLink($name, $span = null, $date = null)
    {
        if ($span && $span instanceof \App\Models\Span) {
            // Use time-travel route if date is provided
            if ($date) {
                $link = route('spans.at-date', ['span' => $span, 'date' => $date]);
            } else {
                $link = route('spans.show', $span);
            }
            
            $classes = 'text-decoration-none';
            
            // Add placeholder class if the span is in placeholder state
            if ($span->state === 'placeholder') {
                $classes .= ' text-placeholder';
            }
            
            return '<a href="' . $link . '" class="' . $classes . '">' . e($name) . '</a>';
        }
        return e($name);
    }

    /**
     * Get human-readable birth date
     */
    protected function getHumanReadableBirthDate(Span $span): string
    {
        return $this->formatHumanReadableDate($span->start_year, $span->start_month, $span->start_day);
    }

    /**
     * Get birth preposition (on vs in)
     */
    protected function getBirthPreposition(Span $span): string
    {
        return $this->getDatePreposition($span->start_year, $span->start_month, $span->start_day);
    }

    /**
     * Get human-readable formation date
     */
    protected function getHumanReadableFormationDate(Span $span): string
    {
        return $this->formatHumanReadableDate($span->start_year, $span->start_month, $span->start_day);
    }

    /**
     * Format a date in human-readable format
     */
    protected function formatHumanReadableDate(?int $year, ?int $month, ?int $day): string
    {
        if (!$year) {
            return '';
        }

        // If we have day, month, and year, format as "13 February, 1976"
        if ($day && $month) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1));
            $dateText = $day . ' ' . $monthName . ', ' . $year;
            return $this->makeDateLink($dateText, $year, $month, $day);
        }

        // If we have month and year, format as "February 1976"
        if ($month) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1));
            $dateText = $monthName . ' ' . $year;
            return $this->makeDateLink($dateText, $year, $month, null);
        }

        // If we only have year, return just the year
        $dateText = (string) $year;
        return $this->makeDateLink($dateText, $year, null, null);
    }

    /**
     * Create a link to a date page
     */
    protected function makeDateLink(string $dateText, ?int $year, ?int $month, ?int $day): string
    {
        if (!$year) {
            return $dateText;
        }

        // Generate the date URL based on precision
        if ($day && $month) {
            // Full date: YYYY-MM-DD
            $dateUrl = sprintf('%04d-%02d-%02d', $year, $month, $day);
        } elseif ($month) {
            // Month and year: YYYY-MM
            $dateUrl = sprintf('%04d-%02d', $year, $month);
        } else {
            // Year only: YYYY
            $dateUrl = (string) $year;
        }

        $url = route('date.explore', ['date' => $dateUrl]);
        
        // Debug: Log the generated URL
        if (app()->environment('local', 'development')) {
            \Log::info('Generated date URL', ['url' => $url, 'dateUrl' => $dateUrl]);
        }
        
        return '<a href="' . $url . '" class="text-decoration-none">' . e($dateText) . '</a>';
    }

    /**
     * Get the appropriate preposition for a date
     */
    protected function getDatePreposition(?int $year, ?int $month, ?int $day): string
    {
        if (!$year) {
            return 'born in';
        }

        // If we have day, month, and year, use "born on"
        if ($day && $month) {
            return 'born on';
        }

        // If we have month and year, or just year, use "born in"
        return 'born in';
    }

    /**
     * Get the appropriate preposition for a date based on precision
     * Returns "on" for full dates (day + month + year) and "in" for partial dates (year only or month + year)
     */
    protected function getGenericDatePreposition(?int $year, ?int $month, ?int $day): string
    {
        if (!$year) {
            return 'in';
        }

        // Only use "on" if we have day, month, AND year (full date)
        if ($day && $month) {
            return 'on';
        }

        // Use "in" for year only, month+year, or any other partial date
        return 'in';
    }

    /**
     * Get birth location for a person
     */
    protected function getBirthLocation(Span $person): ?string
    {
        if (!$person->start_year) {
            return null;
        }

        $residenceConnections = $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'residence')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'place');
            })
            ->with(['child', 'connectionSpan'])
            ->get();

        // Find the best matching residence for birth location
        $bestMatch = null;
        $bestScore = 0;

        foreach ($residenceConnections as $connection) {
            // Only consider connections where the child is a place
            if ($connection->child->type_id !== 'place') {
                continue;
            }
            $connectionSpan = $connection->connectionSpan;
            if (!$connectionSpan) {
                continue;
            }

            $score = $this->calculateBirthLocationScore(
                $person->start_year, $person->start_month, $person->start_day,
                $connectionSpan->start_year, $connectionSpan->start_month, $connectionSpan->start_day,
                $connectionSpan->end_year, $connectionSpan->end_month, $connectionSpan->end_day
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $connection;
            }
        }

        // Only return a match if we have a reasonable score (at least year match)
        if ($bestScore >= 1 && $bestMatch && $bestMatch->child->type_id === 'place') {
            return $this->makeSpanLink($bestMatch->child->name, $bestMatch->child);
        }
        
        return null;
    }

    /**
     * Get debug information for birth location (separate method to avoid recursion)
     */
    protected function getBirthLocationDebug(Span $person): ?array
    {
        if (!$person->start_year) {
            return ['error' => 'No birth year'];
        }

        $residenceConnections = $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'residence')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'place');
            })
            ->with(['child', 'connectionSpan'])
            ->limit(20) // Limit to prevent memory issues with people who have many residences
            ->get();

        $debug = [
            'birth_year' => $person->start_year,
            'birth_month' => $person->start_month,
            'birth_day' => $person->start_day,
            'residence_connections_count' => $residenceConnections->count(),
            'residence_details' => []
        ];

        // Find the best matching residence for birth location
        $bestMatch = null;
        $bestScore = 0;

        foreach ($residenceConnections as $connection) {
            // Only consider connections where the child is a place
            if ($connection->child->type_id !== 'place') {
                continue;
            }
            $connectionSpan = $connection->connectionSpan;
            if (!$connectionSpan) {
                $debug['residence_details'][] = [
                    'place' => $connection->child->name,
                    'no_connection_span' => true
                ];
                continue;
            }

            $score = $this->calculateBirthLocationScore(
                $person->start_year, $person->start_month, $person->start_day,
                $connectionSpan->start_year, $connectionSpan->start_month, $connectionSpan->start_day,
                $connectionSpan->end_year, $connectionSpan->end_month, $connectionSpan->end_day
            );

            $debug['residence_details'][] = [
                'place' => $connection->child->name,
                'start_year' => $connectionSpan->start_year,
                'start_month' => $connectionSpan->start_month,
                'start_day' => $connectionSpan->start_day,
                'end_year' => $connectionSpan->end_year,
                'end_month' => $connectionSpan->end_month,
                'end_day' => $connectionSpan->end_day,
                'score' => $score
            ];

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $connection;
            }
        }

        $debug['best_score'] = $bestScore;
        $debug['best_match_place'] = $bestMatch?->child?->type_id === 'place' ? $bestMatch?->child?->name : null;

        // Store debug info in a way we can access it
        if (app()->environment('local', 'development')) {
            \Log::info('Birth location debug', $debug);
        }

        // Return the debug array, not the actual birth location
        return $debug;
    }

    /**
     * Calculate a score for how well a residence connection matches the birth date
     * Higher scores indicate better matches
     */
    protected function calculateBirthLocationScore(
        ?int $birthYear, ?int $birthMonth, ?int $birthDay,
        ?int $residenceStartYear, ?int $residenceStartMonth, ?int $residenceStartDay,
        ?int $residenceEndYear, ?int $residenceEndMonth, ?int $residenceEndDay
    ): int {
        $score = 0;

        // Year match is most important
        if ($birthYear && $residenceStartYear && $birthYear == $residenceStartYear) {
            $score += 10;
        }

        // Check if birth date falls within residence period
        if ($birthYear && $residenceStartYear && $residenceEndYear) {
            if ($birthYear >= $residenceStartYear && $birthYear <= $residenceEndYear) {
                $score += 5;
            }
        }

        // Month match (if we have both)
        if ($birthMonth && $residenceStartMonth && $birthMonth == $residenceStartMonth) {
            $score += 3;
        }

        // Day match (if we have both)
        if ($birthDay && $residenceStartDay && $birthDay == $residenceStartDay) {
            $score += 2;
        }

        // Bonus for exact date match
        if ($birthYear == $residenceStartYear && 
            $birthMonth == $residenceStartMonth && 
            $birthDay == $residenceStartDay) {
            $score += 5;
        }

        // Bonus for ongoing residence that started before birth
        if ($birthYear && $residenceStartYear && $birthYear >= $residenceStartYear && !$residenceEndYear) {
            $score += 3;
        }

        return $score;
    }

    protected function getResidences(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'residence')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'place');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'place' => $connection->child->name,
                    'place_span' => $connection->child,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    protected function getResidencePlaces(Span $person): string
    {
        $residences = $this->getResidences($person);
        $placeLinks = $residences->map(function ($residence) {
            $placeName = $residence['place'];
            $placeSpan = $residence['place_span'];
            
            if ($placeSpan) {
                return $this->makeSpanLink($placeName, $placeSpan);
            }
            
            return e($placeName);
        })->unique()->values()->toArray();
        return $this->formatList($placeLinks);
    }

    protected function getLongestResidenceData(Span $person, string $field): ?string
    {
        $residences = $this->getResidences($person);
        $longest = null;
        $maxYears = 0;
        
        foreach ($residences as $res) {
            if ($res['start_date'] && $res['end_date']) {
                $start = Carbon::parse($res['start_date']);
                $end = Carbon::parse($res['end_date']);
                $years = $start->diffInYears($end);
                if ($years > $maxYears) {
                    $maxYears = $years;
                    $longest = $res;
                }
            }
        }
        
        if (!$longest) {
            return null;
        }
        
        return match ($field) {
            'place' => $this->makeSpanLink($longest['place'], $longest['place_span']),
            'duration' => $maxYears . ' years',
            default => null,
        };
    }

    protected function getEducation(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'education')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'organisation');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'organisation' => $connection->child->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    protected function getEducationInstitutions(Span $person): string
    {
        $institutions = $this->getEducation($person);
        $links = $institutions->map(function ($edu) use ($person) {
            $org = $person->connectionsAsSubjectWithAccess($this->currentUser)
                ->where('type_id', 'education')
                ->whereHas('child', function ($query) {
                    $query->where('type_id', 'organisation');
                })
                ->with('child')
                ->get()
                ->firstWhere('child.name', $edu['organisation'])?->child;
            if ($org) {
                return $this->makeSpanLink($edu['organisation'], $org);
            }
            return e($edu['organisation']);
        })->toArray();
        return $this->formatList($links);
    }

    protected function getWork(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'employment')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'organisation');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'organisation' => $connection->child->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    protected function getWorkOrganisations(Span $person): string
    {
        $organisations = $this->getWork($person);
        $links = $organisations->map(function ($work) use ($person) {
            $org = $person->connectionsAsSubjectWithAccess($this->currentUser)
                ->where('type_id', 'employment')
                ->whereHas('child', function ($query) {
                    $query->where('type_id', 'organisation');
                })
                ->with('child')
                ->get()
                ->firstWhere('child.name', $work['organisation'])?->child;
            if ($org) {
                return $this->makeSpanLink($work['organisation'], $org);
            }
            return e($work['organisation']);
        })->toArray();
        return $this->formatList($links);
    }

    protected function getMostRecentJobData(Span $person, string $field): ?string
    {
        $work = $this->getWork($person);
        $mostRecent = null;
        $latest = null;
        
        foreach ($work as $job) {
            // Prioritize ongoing jobs (no end_date) over past jobs
            if (!$job['end_date']) {
                // This is an ongoing job - it's automatically the most recent
                $mostRecent = $job;
                break;
            } else {
                // This is a past job - check if it's the latest past job
                $end = Carbon::parse($job['end_date']);
                if (!$latest || $end->gt($latest)) {
                    $latest = $end;
                    $mostRecent = $job;
                }
            }
        }
        
        return $mostRecent[$field] ?? null;
    }

    protected function getMostRecentJobOrganisation(Span $person): ?string
    {
        $work = $this->getWork($person);
        $mostRecent = null;
        $latest = null;
        
        foreach ($work as $job) {
            // Prioritize ongoing jobs (no end_date) over past jobs
            if (!$job['end_date']) {
                // This is an ongoing job - it's automatically the most recent
                $mostRecent = $job;
                break;
            } else {
                // This is a past job - check if it's the latest past job
                $end = Carbon::parse($job['end_date']);
                if (!$latest || $end->gt($latest)) {
                    $latest = $end;
                    $mostRecent = $job;
                }
            }
        }
        
        if (!$mostRecent) {
            return null;
        }
        
        // Find the organisation span to create a link
        $org = $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'employment')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'organisation');
            })
            ->with('child')
            ->get()
            ->firstWhere('child.name', $mostRecent['organisation'])?->child;
            
        if ($org) {
            return $this->makeSpanLink($mostRecent['organisation'], $org);
        }
        
        return e($mostRecent['organisation']);
    }

    protected function getRelationships(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'relationship')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                $connectionSpan = $connection->connectionSpan;
                return [
                    'person' => $connection->child->name,
                    'person_span' => $connection->child,
                    'start_date' => $connectionSpan?->formatted_start_date,
                    'end_date' => $connectionSpan?->formatted_end_date,
                    'start_year' => $connectionSpan?->start_year,
                    'start_month' => $connectionSpan?->start_month,
                    'start_day' => $connectionSpan?->start_day,
                    'start_precision' => $connectionSpan?->start_precision,
                    'end_year' => $connectionSpan?->end_year,
                    'end_month' => $connectionSpan?->end_month,
                    'end_day' => $connectionSpan?->end_day,
                    'end_precision' => $connectionSpan?->end_precision,
                ];
            });
    }

    protected function getCurrentRelationshipData(Span $person, string $field): ?array
    {
        $relationships = $this->getRelationships($person);
        foreach ($relationships as $rel) {
            if (!$rel['end_date']) {
                return [
                    'name' => $rel['person'],
                    'span' => $rel['person_span'],
                ];
            }
        }
        return null;
    }

    protected function getLongestRelationshipData(Span $person, string $field): ?array
    {
        $relationships = $this->getRelationships($person);
        $longest = null;
        $maxDuration = 0;
        foreach ($relationships as $rel) {
            if ($rel['start_year'] && $rel['end_year']) {
                $duration = $this->calculateDurationInYears(
                    $rel['start_year'], $rel['start_month'], $rel['start_day'], $rel['start_precision'],
                    $rel['end_year'], $rel['end_month'], $rel['end_day'], $rel['end_precision']
                );
                if ($duration > $maxDuration) {
                    $maxDuration = $duration;
                    $longest = $rel;
                }
            }
        }
        if (!$longest) {
            return null;
        }
        return [
            'name' => $longest['person'],
            'span' => $longest['person_span'],
            'duration' => $this->formatDuration($maxDuration),
        ];
    }

    /**
     * Calculate duration in years between two dates, accounting for precision
     */
    protected function calculateDurationInYears(
        int $startYear, ?int $startMonth, ?int $startDay, string $startPrecision,
        int $endYear, ?int $endMonth, ?int $endDay, string $endPrecision
    ): float {
        // For year precision, use the year difference
        if ($startPrecision === 'year' && $endPrecision === 'year') {
            return $endYear - $startYear;
        }
        
        // For month precision, calculate based on months
        if ($startPrecision === 'month' && $endPrecision === 'month') {
            $startMonth = $startMonth ?? 1;
            $endMonth = $endMonth ?? 1;
            $monthsDiff = ($endYear - $startYear) * 12 + ($endMonth - $startMonth);
            return $monthsDiff / 12.0;
        }
        
        // For day precision, calculate exact days and convert to years
        if ($startPrecision === 'day' && $endPrecision === 'day') {
            $startDate = Carbon::createFromDate($startYear, $startMonth ?? 1, $startDay ?? 1);
            $endDate = Carbon::createFromDate($endYear, $endMonth ?? 1, $endDay ?? 1);
            return $startDate->floatDiffInYears($endDate);
        }
        
        // Mixed precision: use the least precise method
        if ($startPrecision === 'year' || $endPrecision === 'year') {
            return $endYear - $startYear;
        }
        
        // Default to year difference for safety
        return $endYear - $startYear;
    }

    /**
     * Format duration in a human-readable way
     */
    protected function formatDuration(float $years): string
    {
        if ($years < 1) {
            $months = round($years * 12);
            return $months . ' month' . ($months !== 1 ? 's' : '');
        } elseif ($years < 2) {
            return round($years, 1) . ' year';
        } else {
            return round($years) . ' years';
        }
    }

    protected function getParentNames(Span $person): string
    {
        $parentSpans = $person->parents;
        $parentLinks = $parentSpans->map(function ($parent) {
            return $this->makeSpanLink($parent->name, $parent);
        })->toArray();
        return $this->formatList($parentLinks);
    }

    protected function getChildNames(Span $person): string
    {
        $childSpans = $person->children;
        $childLinks = $childSpans->map(function ($child) {
            return $this->makeSpanLink($child->name, $child);
        })->toArray();
        return $this->formatList($childLinks);
    }

    protected function getSiblingNames(Span $person): string
    {
        $siblingSpans = $person->siblings();
        $siblingLinks = $siblingSpans->map(function ($sibling) {
            return $this->makeSpanLink($sibling->name, $sibling);
        })->toArray();
        return $this->formatList($siblingLinks);
    }

    protected function getBandMembers(Span $band): Collection
    {
        return $band->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'membership')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with(['parent', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'person' => $connection->parent->name,
                    'person_span' => $connection->parent,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    protected function getBandMemberNames(Span $band): string
    {
        $members = $this->getBandMembers($band);
        $memberLinks = $members->pluck('person_span')->map(function ($span) {
            return $this->makeSpanLink($span->name, $span);
        })->toArray();
        return $this->formatList($memberLinks);
    }

    protected function getBandMemberships(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'membership')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'band');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'band' => $connection->child->name,
                    'band_span' => $connection->child,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    protected function getBandMembershipNames(Span $person): string
    {
        $bands = $this->getBandMemberships($person);
        $bandLinks = $bands->pluck('band_span')->map(function ($span) {
            return $this->makeSpanLink($span->name, $span);
        })->toArray();
        return $this->formatList($bandLinks);
    }

    protected function getTenseVerb(Span $span): string
    {
        return $span->is_ongoing ? 'are' : 'were';
    }

    protected function getIsVerb(Span $span): string
    {
        $pronoun = $this->getPronoun($span, 'subject');
        $tense = $span->is_ongoing ? 'present' : 'past';
        
        // Handle plural "they" case
        if ($pronoun === 'they') {
            return $tense === 'present' ? 'are' : 'were';
        }
        
        // Handle singular cases
        return $tense === 'present' ? 'is' : 'was';
    }

    protected function getHasVerb(Span $span): string
    {
        return $span->is_ongoing ? 'has' : 'had';
    }

    protected function getHaveVerb(Span $span): string
    {
        return $span->is_ongoing ? 'have' : 'had';
    }

    protected function getWasVerb(Span $span): string
    {
        return $span->is_ongoing ? 'is' : 'was';
    }

    protected function getHadVerb(Span $span): string
    {
        return $span->is_ongoing ? 'has' : 'had';
    }

    protected function getObjectIsVerb(Span $person): string
    {
        // Get the first band membership to determine the verb tense
        $firstBand = $this->getBandMemberships($person)->first();
        if ($firstBand && $firstBand['band_span']) {
            // Use the band's ongoing status to determine tense
            return $firstBand['band_span']->is_ongoing ? 'is' : 'was';
        }
        
        // Fallback to person's status if no band found
        return $person->is_ongoing ? 'is' : 'was';
    }

    protected function getDiscography(Span $band): Collection
    {
        return $band->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'created')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'thing' => $connection->child->name,
                    'thing_span' => $connection->child,
                    'date' => $connection->connectionSpan?->formatted_start_date,
                ];
            });
    }

    /**
     * Helper methods for template selection
     */
    protected function isEmptyData(array $data): bool
    {
        return isset($data['album_count']) && $data['album_count'] === 0;
    }

    protected function isSingleData(array $data): bool
    {
        return isset($data['count']) && $data['count'] === 1;
    }

    protected function getMissingData(array $data): array
    {
        $missing = [];
        
        foreach ($data as $key => $value) {
            // Handle debug arrays (extract the actual value)
            if (is_array($value) && isset($value['value'])) {
                $value = $value['value'];
            }
            
            // Check if the value is null, empty string, empty array, or contains debug info
            if ($value === null || $value === '' || 
                (is_array($value) && empty($value)) ||
                (is_array($value) && isset($value['debug']))) {
                $missing[] = $key;
            }
        }
        
        return $missing;
    }

    protected function shouldUseFallback(array $data): bool
    {
        // Check for specific known cases
        if (isset($data['birth_location'])) {
            $birthLocation = $data['birth_location'];
            
            // Handle debug arrays (extract the actual value)
            if (is_array($birthLocation) && isset($birthLocation['value'])) {
                $birthLocation = $birthLocation['value'];
            }
            
            // If birth location is null, empty, or contains debug info, use fallback
            if ($birthLocation === null || $birthLocation === '' || 
                (is_array($birthLocation) && isset($birthLocation['debug']))) {
                return true;
            }
        }
        
        // Check for any missing data that would make the template awkward
        $missingData = $this->getMissingData($data);
        return !empty($missingData);
    }

    /**
     * Check if we should use the deceased template for age sentences
     */
    protected function shouldUseDeceasedTemplate(array $data, Span $span): bool
    {
        // For age sentences, we should use the deceased template if the person is dead
        // We can determine this by checking if the span has an end date and is not ongoing
        return $span->type_id === 'person' && 
               $span->end_year !== null && 
               !$span->is_ongoing;
    }

    /**
     * Format a list of items with proper grammar
     */
    protected function formatList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        } elseif (count($items) === 2) {
            return $items[0] . ' and ' . $items[1];
        } else {
            $last = array_pop($items);
            return implode(', ', $items) . ', and ' . $last;
        }
    }

    /**
     * Group sentences into paragraphs
     */
    protected function groupIntoSentences(array $sentences): array
    {
        if (empty($sentences)) {
            return [];
        }

        return [implode(' ', $sentences)];
    }

    /**
     * Generate metadata for the story
     */
    protected function generateMetadata(Span $span): array
    {
        $gender = $span->getMeta('gender');
        $isAlive = $span->is_ongoing;
        $tense = $isAlive ? 'present' : 'past';

        return [
            'gender' => $gender,
            'is_alive' => $isAlive,
            'tense' => $tense,
        ];
    }

    /**
     * Generate a generic story for unsupported span types
     */
    protected function generateGenericStory(Span $span): array
    {
        $story = [];
        $isOngoing = $span->is_ongoing;
        $tense = $isOngoing ? 'present' : 'past';

        if ($span->start_year) {
            $action = match ($span->type_id) {
                'person' => 'was born',
                'organisation' => 'was founded',
                'event' => 'began',
                'band' => 'was formed',
                default => 'started',
            };
            $story[] = "{$span->name} {$action} in {$span->formatted_start_date}.";
        }

        if ($span->description) {
            $story[] = $span->description;
        }

        return [
            'title' => "The Story of {$span->name}",
            'paragraphs' => $this->groupIntoSentences($story),
            'metadata' => [
                'is_ongoing' => $isOngoing,
                'tense' => $tense,
            ]
        ];
    }

    protected function getFormationLocation(Span $span): ?string
    {
        $locationName = $span->getMeta('formation_location');
        if (!$locationName) {
            return null;
        }
        
        // Try to find a place span with this name
        $placeSpan = \App\Models\Span::where('name', $locationName)
            ->where('type_id', 'place')
            ->first();
            
        if ($placeSpan) {
            return $this->makeSpanLink($locationName, $placeSpan);
        }
        
        return e($locationName);
    }

    /**
     * Generate a fallback sentence when no other information is available
     */
    protected function generateFallbackSentence(Span $span): string
    {
        $name = $this->makeSpanLink($span->name, $span);
        $spanType = $this->getHumanReadableSpanType($span->type_id);
        $tense = $span->is_ongoing ? 'is' : 'was';
        $article = $this->getArticleForSpanType($span->type_id);
        
        // Include subtype if available
        $subtype = $span->subtype;
        $subtypeText = $subtype ? "{$subtype} " : "";
        
        // Use the correct article based on subtype if available, otherwise use span type article
        $article = $subtype ? $this->getArticleForSubtype($subtype) : $article;
        
        // Check for start and end dates
        $hasStartDate = $span->start_year !== null;
        $hasEndDate = $span->end_year !== null;
        
        if ($hasStartDate && $hasEndDate) {
            // Both start and end dates
            $startDate = $this->formatHumanReadableDate($span->start_year, $span->start_month, $span->start_day);
            $endDate = $this->formatHumanReadableDate($span->end_year, $span->end_month, $span->end_day);
            $startPreposition = $this->getGenericDatePreposition($span->start_year, $span->start_month, $span->start_day);
            $endPreposition = $this->getGenericDatePreposition($span->end_year, $span->end_month, $span->end_day);
            return "{$name} {$tense} {$article} {$subtypeText}{$spanType}. It started {$startPreposition} {$startDate} and ended {$endPreposition} {$endDate}. That's all for now.";
        } elseif ($hasStartDate) {
            // Only start date
            $startDate = $this->formatHumanReadableDate($span->start_year, $span->start_month, $span->start_day);
            $startPreposition = $this->getGenericDatePreposition($span->start_year, $span->start_month, $span->start_day);
            return "{$name} {$tense} {$article} {$subtypeText}{$spanType}. It started {$startPreposition} {$startDate}. That's all for now.";
        } elseif ($hasEndDate) {
            // Only end date
            $endDate = $this->formatHumanReadableDate($span->end_year, $span->end_month, $span->end_day);
            $endPreposition = $this->getGenericDatePreposition($span->end_year, $span->end_month, $span->end_day);
            return "{$name} {$tense} {$article} {$subtypeText}{$spanType}. It ended {$endPreposition} {$endDate}. That's all for now.";
        } else {
            // No dates
            return "{$name} {$tense} {$article} {$subtypeText}{$spanType}. That's all for now.";
        }
    }

    /**
     * Get the correct article (a/an) for a span type
     */
    protected function getArticleForSpanType(string $typeId): string
    {
        return match ($typeId) {
            'organisation', 'event' => 'an',
            default => 'a',
        };
    }

    /**
     * Get the correct article (a/an) for a subtype
     */
    protected function getArticleForSubtype(string $subtype): string
    {
        // Check if the subtype begins with a vowel sound
        $firstChar = strtolower(substr($subtype, 0, 1));
        return in_array($firstChar, ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
    }

    /**
     * Get human-readable span type name
     */
    protected function getHumanReadableSpanType(string $typeId): string
    {
        return match ($typeId) {
            'person' => 'person',
            'place' => 'place',
            'organisation' => 'organisation',
            'event' => 'event',
            'band' => 'band',
            'thing' => 'thing',
            'role' => 'role',
            'set' => 'set',
            default => $typeId,
        };
    }

    protected function getRoles(Span $person): Collection
    {
        return $person->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'role');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                $roleSpan = $connection->child;
                $connectionSpan = $connection->connectionSpan;
                
                // Get organisation info if available
                $organisation = null;
                if ($roleSpan && $roleSpan->getMeta('organisation')) {
                    $organisationId = $roleSpan->getMeta('organisation');
                    $organisation = \App\Models\Span::find($organisationId);
                }
                
                return [
                    'role' => $roleSpan->name,
                    'role_span' => $roleSpan,
                    'organisation' => $organisation ? $organisation->name : null,
                    'organisation_span' => $organisation,
                    'start_date' => $connectionSpan?->formatted_start_date,
                    'end_date' => $connectionSpan?->formatted_end_date,
                    'is_ongoing' => $connectionSpan?->is_ongoing ?? false,
                ];
            });
    }

    protected function getPastRoles(Span $person): Collection
    {
        return $this->getRoles($person)
            ->where('is_ongoing', false);
    }

    protected function getRoleNames(Span $person): string
    {
        $roles = $this->getPastRoles($person);
        
        if ($roles->isEmpty()) {
            return '';
        }
        
        $roleNames = $roles->map(function ($role) {
            $roleName = $this->makeSpanLink($role['role'], $role['role_span']);
            
            // Add organisation if available
            if ($role['organisation']) {
                $roleName .= ' at ' . $this->makeSpanLink($role['organisation'], $role['organisation_span']);
            }
            
            return $roleName;
        });
        
        return $this->formatList($roleNames->toArray());
    }

    protected function getCurrentRole(Span $person): ?string
    {
        $currentRole = $this->getRoles($person)
            ->where('is_ongoing', true)
            ->first();
        
        if (!$currentRole) {
            return null;
        }
        
        $roleName = $this->makeSpanLink($currentRole['role'], $currentRole['role_span']);
        
        // Add organisation if available
        if ($currentRole['organisation']) {
            $roleName .= ' at ' . $this->makeSpanLink($currentRole['organisation'], $currentRole['organisation_span']);
        }
        
        return $roleName;
    }

    /**
     * Get the current age of a person, rounded to the nearest 0.25 years with Unicode fractions
     */
    protected function getAge(Span $span): ?string
    {
        if (!$span->start_year) {
            return null;
        }

        $startYear = $span->start_year;
        // Use end_year for deceased people, current year for living
        if ($span->end_year !== null && !$span->is_ongoing) {
            $endYear = $span->end_year;
        } else {
            $endYear = date('Y');
        }
        $age = $endYear - $startYear;

        if ($age === 0) {
            return 'less than a year';
        } elseif ($age === 1) {
            return '1';
        } else {
            return (string) $age;
        }
    }

    /**
     * Get human-readable release date for albums/things
     */
    protected function getHumanReadableReleaseDate(Span $span): string
    {
        return $this->formatHumanReadableDate($span->start_year, $span->start_month, $span->start_day);
    }

    /**
     * Get creator for albums/things
     */
    protected function getCreator(Span $span): ?string
    {
        $creatorConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'created')
            ->whereHas('parent', function ($query) {
                $query->whereIn('type_id', ['person', 'band']);
            })
            ->with(['parent'])
            ->first();

        if ($creatorConnection && $creatorConnection->parent) {
            return $this->makeSpanLink($creatorConnection->parent->name, $creatorConnection->parent);
        }

        return null;
    }

    /**
     * Get tracks for albums/things
     */
    protected function getTracks(Span $span): Collection
    {
        return $span->connectionsAsSubjectWithAccess($this->currentUser)
            ->where('type_id', 'contains')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['child'])
            ->get()
            ->map(function ($connection) {
                return [
                    'track' => $connection->child->name,
                    'track_span' => $connection->child,
                ];
            });
    }

    /**
     * Get album for tracks
     */
    protected function getAlbum(Span $span): ?string
    {
        $albumConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'contains')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['parent'])
            ->first();

        if ($albumConnection && $albumConnection->parent) {
            return $this->makeSpanLink($albumConnection->parent->name, $albumConnection->parent);
        }

        return null;
    }

    /**
     * Get artists for tracks
     */
    protected function getArtists(Span $span): Collection
    {
        $artists = collect();
        
        // First, try to get artists directly from the track
        $directArtists = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'created')
            ->whereHas('parent', function ($query) {
                $query->whereIn('type_id', ['person', 'band']);
            })
            ->with(['parent'])
            ->get()
            ->map(function ($connection) {
                return [
                    'artist' => $connection->parent->name,
                    'artist_span' => $connection->parent,
                ];
            });
        
        $artists = $artists->merge($directArtists);
        
        // If no direct artists found, try to get artists from the album
        if ($artists->isEmpty()) {
            $albumConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
                ->where('type_id', 'contains')
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'thing');
                })
                ->with(['parent'])
                ->first();
            
            if ($albumConnection && $albumConnection->parent) {
                $albumArtists = $albumConnection->parent->connectionsAsObjectWithAccess($this->currentUser)
                    ->where('type_id', 'created')
                    ->whereHas('parent', function ($query) {
                        $query->whereIn('type_id', ['person', 'band']);
                    })
                    ->with(['parent'])
                    ->get()
                    ->map(function ($connection) {
                        return [
                            'artist' => $connection->parent->name,
                            'artist_span' => $connection->parent,
                        ];
                    });
                
                $artists = $artists->merge($albumArtists);
            }
        }
        
        return $artists;
    }

    /**
     * Get artist names for tracks
     */
    protected function getArtistNames(Span $span): string
    {
        $artists = $this->getArtists($span);
        
        if ($artists->isEmpty()) {
            return '';
        }

        $artistNames = $artists->map(function ($artist) {
            return $this->makeSpanLink($artist['artist'], $artist['artist_span']);
        });

        return $this->formatList($artistNames->toArray());
    }

    /**
     * Get first artist name for tracks
     */
    protected function getFirstArtistName(Span $span): ?string
    {
        $firstArtist = $this->getArtists($span)->first();
        
        if ($firstArtist) {
            return $this->makeSpanLink($firstArtist['artist'], $firstArtist['artist_span']);
        }

        return null;
    }

    /**
     * Get duration for tracks
     */
    protected function getDuration(Span $span): ?string
    {
        $duration = $span->getMeta('duration');
        
        if (!$duration) {
            return null;
        }

        // Format duration (assuming it's stored in seconds or as a formatted string)
        if (is_numeric($duration)) {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            return "{$minutes}:{$seconds}";
        }

        return $duration;
    }

    /**
     * Get the artist for a track (fallback to album's artist)
     */
    protected function getTrackArtist(Span $span): ?string
    {
        // Try direct artist (person or band via 'created')
        $directArtist = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'created')
            ->whereHas('parent', function ($query) {
                $query->whereIn('type_id', ['person', 'band']);
            })
            ->with(['parent'])
            ->first();
        if ($directArtist && $directArtist->parent) {
            return $this->makeSpanLink($directArtist->parent->name, $directArtist->parent);
        }
        // Fallback: get album, then its artist
        $albumConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'contains')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['parent'])
            ->first();
        if ($albumConnection && $albumConnection->parent) {
            $album = $albumConnection->parent;
            $albumArtist = $album->connectionsAsObjectWithAccess($this->currentUser)
                ->where('type_id', 'created')
                ->whereHas('parent', function ($query) {
                    $query->whereIn('type_id', ['person', 'band']);
                })
                ->with(['parent'])
                ->first();
            if ($albumArtist && $albumArtist->parent) {
                return $this->makeSpanLink($albumArtist->parent->name, $albumArtist->parent);
            }
        }
        return null;
    }

    /**
     * Get the release date for a track (fallback to album's release date)
     */
    protected function getTrackReleaseDate(Span $span): ?string
    {
        // Try track's own date
        $date = $this->getHumanReadableReleaseDate($span);
        if ($date) {
            return $date;
        }
        // Fallback: get album, then its date
        $albumConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'contains')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['parent'])
            ->first();
        if ($albumConnection && $albumConnection->parent) {
            return $this->getHumanReadableReleaseDate($albumConnection->parent);
        }
        return null;
    }

    /**
     * Get the album for a track
     */
    protected function getTrackAlbum(Span $span): ?string
    {
        $albumConnection = $span->connectionsAsObjectWithAccess($this->currentUser)
            ->where('type_id', 'contains')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['parent'])
            ->first();
        if ($albumConnection && $albumConnection->parent) {
            return $this->makeSpanLink($albumConnection->parent->name, $albumConnection->parent);
        }
        return null;
    }

    // --- Conditions for track story sentences ---
    protected function hasTrackArtist(Span $span): bool
    {
        return $this->getTrackArtist($span) !== null;
    }
    protected function hasTrackReleaseDate(Span $span): bool
    {
        return $this->getTrackReleaseDate($span) !== null;
    }
    protected function hasTrackAlbum(Span $span): bool
    {
        return $this->getTrackAlbum($span) !== null;
    }

    // Photo-specific methods
    protected function getFeaturedSpanName(Span $photo): ?string
    {
        // Look for "features" connections to find what this photo is of
        $featuresConnections = $photo->connectionsAsSubject()
            ->where('type_id', 'features')
            ->whereHas('child')
            ->with(['child'])
            ->get();
        
        if ($featuresConnections->isEmpty()) {
            return null;
        }
        
        $featuredSpans = $featuresConnections->map(function ($connection) {
            return $this->makeSpanLink($connection->child->name, $connection->child);
        });
        
        // Join multiple spans with commas and "and" for the last one
        if ($featuredSpans->count() === 1) {
            return $featuredSpans->first();
        } elseif ($featuredSpans->count() === 2) {
            return $featuredSpans->join(' and ');
        } else {
            $lastSpan = $featuredSpans->pop();
            return $featuredSpans->join(', ') . ' and ' . $lastSpan;
        }
    }

    protected function getPhotoDate(Span $photo): ?string
    {
        // Use the photo's start date if available
        if ($photo->start_year) {
            return $this->formatHumanReadableDate($photo->start_year, $photo->start_month, $photo->start_day);
        }
        
        // Fallback to metadata date_taken if available
        if (isset($photo->metadata['date_taken'])) {
            return $photo->metadata['date_taken'];
        }
        
        return null;
    }

    protected function getPhotoDatePreposition(Span $photo): string
    {
        return $this->getGenericDatePreposition($photo->start_year, $photo->start_month, $photo->start_day);
    }

    protected function formatPhotoDateForUrl(Span $photo): string
    {
        // Format the photo date for use in URL (YYYY-MM-DD format)
        if ($photo->start_year && $photo->start_month && $photo->start_day) {
            return sprintf('%04d-%02d-%02d', $photo->start_year, $photo->start_month, $photo->start_day);
        } elseif ($photo->start_year && $photo->start_month) {
            return sprintf('%04d-%02d-01', $photo->start_year, $photo->start_month);
        } elseif ($photo->start_year) {
            return sprintf('%04d-01-01', $photo->start_year);
        }
        
        return '';
    }

    protected function createDateFromSpan(Span $span): ?\DateTime
    {
        // Create a DateTime object from span date information
        if ($span->start_year && $span->start_month && $span->start_day) {
            return new \DateTime(sprintf('%04d-%02d-%02d', $span->start_year, $span->start_month, $span->start_day));
        } elseif ($span->start_year && $span->start_month) {
            return new \DateTime(sprintf('%04d-%02d-%02d', $span->start_year, $span->start_month, 1));
        } elseif ($span->start_year) {
            return new \DateTime(sprintf('%04d-%02d-%02d', $span->start_year, 1, 1));
        }
        
        return null;
    }

    protected function getFeaturedSpanAgeAtPhotoDate(Span $photo): ?string
    {
        // Get all featured people
        $featuresConnections = $photo->connectionsAsSubject()
            ->where('type_id', 'features')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with(['child'])
            ->get();
        
        if ($featuresConnections->isEmpty()) {
            return null;
        }
        
        $ageDescriptions = [];
        
        foreach ($featuresConnections as $connection) {
            $person = $connection->child;
            
            // Calculate age at photo date using proper date arithmetic
            if ($person->start_year && $photo->start_year) {
                // Create proper dates for calculation
                $photoDate = $this->createDateFromSpan($photo);
                $birthDate = $this->createDateFromSpan($person);
                
                if ($photoDate && $birthDate) {
                    $ageInterval = $birthDate->diff($photoDate);
                    $age = $ageInterval->y; // Years only, which automatically rounds down
                    
                    // Only include age if it's reasonable (between 0 and 150)
                    if ($age >= 0 && $age <= 150) {
                        // Create a link to the person's span at the photo date for the entire age sentence
                        $photoDateUrl = $this->formatPhotoDateForUrl($photo);
                        $ageText = $person->name . ' was ' . $age . ' years old';
                        $ageLink = $this->makeSpanLink($ageText, $person, $photoDateUrl);
                        $ageDescriptions[] = $ageLink;
                    }
                }
            }
        }
        
        if (empty($ageDescriptions)) {
            return null;
        }
        
        // Join multiple age descriptions with commas and "and" for the last one
        if (count($ageDescriptions) === 1) {
            return $ageDescriptions[0];
        } elseif (count($ageDescriptions) === 2) {
            return implode(' and ', $ageDescriptions);
        } else {
            $lastAge = array_pop($ageDescriptions);
            return implode(', ', $ageDescriptions) . ' and ' . $lastAge;
        }
    }

    // Photo condition methods
    protected function hasFeaturedSpan(Span $photo): bool
    {
        return $this->getFeaturedSpanName($photo) !== null;
    }

    protected function hasPhotoDate(Span $photo): bool
    {
        return $this->getPhotoDate($photo) !== null;
    }

    protected function hasFeaturedSpanAgeAtPhotoDate(Span $photo): bool
    {
        return $this->getFeaturedSpanAgeAtPhotoDate($photo) !== null;
    }

    // Plaque-specific methods
    protected function getPlaqueFeatures(Span $plaque): ?string
    {
        // Look for "features" connections to find what/who this plaque features
        // Like photos: Plaque (parent/subject) features Person (child/object)
        $featuresConnections = $plaque->connectionsAsSubject()
            ->where('type_id', 'features')
            ->whereHas('child')
            ->with(['child'])
            ->get();
        
        if ($featuresConnections->isEmpty()) {
            return null;
        }
        
        $featuredSpans = $featuresConnections->map(function ($connection) {
            return $this->makeSpanLink($connection->child->name, $connection->child);
        });
        
        // Join multiple spans with commas and "and" for the last one
        if ($featuredSpans->count() === 1) {
            return $featuredSpans->first();
        } elseif ($featuredSpans->count() === 2) {
            return $featuredSpans->join(' and ');
        } else {
            $lastSpan = $featuredSpans->pop();
            return $featuredSpans->join(', ') . ' and ' . $lastSpan;
        }
    }

    protected function getPlaqueLocation(Span $plaque): ?string
    {
        // Look for "located" connections to find where this plaque is
        $locationConnection = \App\Models\Connection::where('type_id', 'located')
            ->where('parent_id', $plaque->id)
            ->whereHas('child')
            ->with(['child'])
            ->first();
        
        if (!$locationConnection) {
            return null;
        }
        
        return $this->makeSpanLink($locationConnection->child->name, $locationConnection->child);
    }

    // Plaque condition methods
    protected function hasPlaqueFeatures(Span $plaque): bool
    {
        return $this->getPlaqueFeatures($plaque) !== null;
    }

    protected function hasPlaqueLocation(Span $plaque): bool
    {
        return $this->getPlaqueLocation($plaque) !== null;
    }

    // At-date specific methods
    protected function getAtDateDisplay(Span $span): string
    {
        if (!$this->contextDate) {
            return '';
        }
        
        // Parse the context date and format it nicely
        $dateParts = explode('-', $this->contextDate);
        if (count($dateParts) === 3) {
            $year = (int) $dateParts[0];
            $month = (int) $dateParts[1];
            $day = (int) $dateParts[2];
            
            $date = new \DateTime("{$year}-{$month}-{$day}");
            return $date->format('j F Y'); // e.g., "25 June 2006"
        }
        
        return $this->contextDate;
    }

    protected function getAgeAtDate(Span $span): ?int
    {
        if (!$this->contextDate || !$span->start_year) {
            return null;
        }
        
        // Create proper dates for calculation
        $contextDate = $this->createDateFromContextDate();
        $birthDate = $this->createDateFromSpan($span);
        
        if ($contextDate && $birthDate) {
            $ageInterval = $birthDate->diff($contextDate);
            $age = $ageInterval->y; // Years only, which automatically rounds down
            
            // Only return age if it's reasonable (between 0 and 150)
            if ($age >= 0 && $age <= 150) {
                return $age;
            }
        }
        
        return null;
    }

    protected function getCurrentActivitiesAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        // Get ongoing connections at this date
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        $activities = [];
        
        // Check employment connections
        $employmentConnections = $span->connectionsAsSubject()
            ->where('type_id', 'employment')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->get();
        
        foreach ($employmentConnections as $connection) {
            $activities[] = 'working at ' . $connection->child->name;
        }
        
        // Check education connections
        $educationConnections = $span->connectionsAsSubject()
            ->where('type_id', 'education')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->get();
        
        foreach ($educationConnections as $connection) {
            $activities[] = 'studying at ' . $connection->child->name;
        }
        
        if (empty($activities)) {
            return null;
        }
        
        // Format the activities list
        if (count($activities) === 1) {
            return $activities[0];
        } elseif (count($activities) === 2) {
            return implode(' and ', $activities);
        } else {
            $lastActivity = array_pop($activities);
            return implode(', ', $activities) . ' and ' . $lastActivity;
        }
    }

    protected function getResidenceAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        // Get the most recent residence that was active at this date
        $residenceConnection = $span->connectionsAsSubject()
            ->where('connections.type_id', 'residence')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        $residenceSpan = $residenceConnection?->child;
        return $residenceSpan ? $this->makeSpanLink($residenceSpan->name, $residenceSpan) : null;
    }

    protected function getEmploymentRoleAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        // Get the most recent role that was active at this date
        $roleConnection = $span->connectionsAsSubject()
            ->where('connections.type_id', 'has_role')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        $roleSpan = $roleConnection?->child;
        return $roleSpan ? $this->makeSpanLink($roleSpan->name, $roleSpan) : null;
    }

    protected function getEmploymentOrganisationAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        // Get the most recent role that was active at this date
        $roleConnection = $span->connectionsAsSubject()
            ->where('connections.type_id', 'has_role')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        if (!$roleConnection) {
            return null;
        }
        
        // Look for at_organisation connections on the connection span itself
        $organisationConnection = $roleConnection->connectionSpan->connectionsAsSubject()
            ->where('connections.type_id', 'at_organisation')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        $organisationSpan = $organisationConnection?->child;
        return $organisationSpan ? $this->makeSpanLink($organisationSpan->name, $organisationSpan) : null;
    }

    protected function getRelationshipAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        // Check relationships where this person is the subject (parent)
        $relationshipAsSubject = $span->connectionsAsSubject()
            ->where('connections.type_id', 'relationship')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        // Check relationships where this person is the object (child)
        $relationshipAsChild = $span->connectionsAsObject()
            ->where('connections.type_id', 'relationship')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['parent', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        // Return the most recent relationship (either direction)
        $relationshipConnection = null;
        $relationshipSpan = null;
        
        if ($relationshipAsSubject && $relationshipAsChild) {
            // Compare dates to get the most recent
            $subjectDate = $relationshipAsSubject->connectionSpan->start_year ?? 0;
            $childDate = $relationshipAsChild->connectionSpan->start_year ?? 0;
            
            if ($subjectDate >= $childDate) {
                $relationshipConnection = $relationshipAsSubject;
                $relationshipSpan = $relationshipAsSubject->child;
            } else {
                $relationshipConnection = $relationshipAsChild;
                $relationshipSpan = $relationshipAsChild->parent;
            }
        } elseif ($relationshipAsSubject) {
            $relationshipConnection = $relationshipAsSubject;
            $relationshipSpan = $relationshipAsSubject->child;
        } elseif ($relationshipAsChild) {
            $relationshipConnection = $relationshipAsChild;
            $relationshipSpan = $relationshipAsChild->parent;
        }
        
        return $relationshipSpan ? $this->makeSpanLink($relationshipSpan->name, $relationshipSpan) : null;
    }

    protected function getEducationAtDate(Span $span): ?string
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $contextDate = $this->createDateFromContextDate();
        if (!$contextDate) {
            return null;
        }
        
        // Get the most recent education connection that was active at this date
        $educationConnection = $span->connectionsAsSubject()
            ->where('connections.type_id', 'education')
            ->whereHas('connectionSpan', function ($query) use ($contextDate) {
                $query->where(function ($q) use ($contextDate) {
                    $q->whereNull('start_year')
                      ->orWhere(function ($q2) use ($contextDate) {
                          $q2->where('start_year', '<=', $contextDate->format('Y'))
                             ->where(function ($q3) use ($contextDate) {
                                 $q3->whereNull('end_year')
                                    ->orWhere('end_year', '>=', $contextDate->format('Y'));
                             });
                      });
                });
            })
            ->with(['child', 'connectionSpan'])
            ->join('spans as connection_spans', 'connections.connection_span_id', '=', 'connection_spans.id')
            ->orderBy('connection_spans.start_year', 'desc')
            ->orderBy('connection_spans.start_month', 'desc')
            ->orderBy('connection_spans.start_day', 'desc')
            ->select('connections.*')
            ->first();
        
        $educationSpan = $educationConnection?->child;
        return $educationSpan ? $this->makeSpanLink($educationSpan->name, $educationSpan) : null;
    }

    protected function getRecentEventsAtDate(Span $span): ?string
    {
        // This would require more complex logic to find recent events
        // For now, return null to keep it simple
        return null;
    }

    protected function getUpcomingEventsAtDate(Span $span): ?string
    {
        // This would require more complex logic to find upcoming events
        // For now, return null to keep it simple
        return null;
    }

    protected function createDateFromContextDate(): ?\DateTime
    {
        if (!$this->contextDate) {
            return null;
        }
        
        $dateParts = explode('-', $this->contextDate);
        if (count($dateParts) === 3) {
            $year = (int) $dateParts[0];
            $month = (int) $dateParts[1];
            $day = (int) $dateParts[2];
            return new \DateTime("{$year}-{$month}-{$day}");
        }
        
        return null;
    }

    // At-date condition methods
    protected function hasAgeAtDate(Span $span): bool
    {
        return $this->getAgeAtDate($span) !== null;
    }

    protected function hasCurrentActivitiesAtDate(Span $span): bool
    {
        return $this->getCurrentActivitiesAtDate($span) !== null;
    }

    protected function hasRecentEventsAtDate(Span $span): bool
    {
        return $this->getRecentEventsAtDate($span) !== null;
    }

    protected function hasUpcomingEventsAtDate(Span $span): bool
    {
        return $this->getUpcomingEventsAtDate($span) !== null;
    }

    protected function hasResidenceAtDate(Span $span): bool
    {
        return $this->getResidenceAtDate($span) !== null;
    }

    protected function hasEmploymentAtDate(Span $span): bool
    {
        return $this->getEmploymentRoleAtDate($span) !== null;
    }

    protected function hasRelationshipAtDate(Span $span): bool
    {
        return $this->getRelationshipAtDate($span) !== null;
    }

    protected function hasEducationAtDate(Span $span): bool
    {
        return $this->getEducationAtDate($span) !== null;
    }
} 