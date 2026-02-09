<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service for generating human-readable micro stories from span and connection data.
 * 
 * This service takes span or connection data and generates natural language
 * sentences that describe the temporal relationships and properties.
 * The output includes clickable links for spans, connections, and dates.
 * Uses a template-based system for flexibility and extensibility.
 */
class MicroStoryService
{
    protected $templates;

    public function __construct()
    {
        $this->templates = config('micro_story_templates');
    }

    /**
     * Generate a micro story for a span with HTML links
     */
    public function generateSpanStory(Span $span): string
    {
        $spanType = $span->type_id;
        
        if (!isset($this->templates['spans'][$spanType])) {
            return $this->generateFallbackSpanStory($span);
        }

        $templates = $this->templates['spans'][$spanType]['templates'];
        
        // Try each template in order until one works
        foreach ($templates as $templateKey => $templateConfig) {
            if ($this->evaluateCondition($templateConfig['condition'], $span)) {
                return $this->processTemplate($templateConfig, $span);
            }
        }
        
        return $this->generateFallbackSpanStory($span);
    }
    
    /**
     * Generate a micro story for a connection with HTML links
     */
    public function generateConnectionStory(Connection $connection): string
    {
        $connectionType = $connection->type_id;
        
        if (!isset($this->templates['connections'][$connectionType])) {
            return $this->generateFallbackConnectionStory($connection);
        }

        $templates = $this->templates['connections'][$connectionType]['templates'];
        
        // Try each template in order until one works
        foreach ($templates as $templateKey => $templateConfig) {
            if ($this->evaluateCondition($templateConfig['condition'], $connection)) {
                return $this->processConnectionTemplate($templateConfig, $connection);
            }
        }
        
        return $this->generateFallbackConnectionStory($connection);
    }

    /**
     * Generate a biography for a span: intro sentence (for persons) plus one micro story per connection, chronologically ordered.
     *
     * @return array{title: string, sentences: string[]}  Title and array of HTML sentence strings.
     */
    public function generateBiography(Span $span, ?Collection $connections = null): array
    {
        $connections = $connections ?? $this->loadConnectionsForSpan($span);
        $connections = $this->filterConnectionsForBiography($connections);
        $sorted = $this->sortConnectionsChronologically($connections);
        $sentences = [];

        if ($span->type_id === 'person' && $span->start_year) {
            $intro = $this->generateSpanStory($span);
            if ($intro) {
                $sentences[] = $intro;
            }
        }

        foreach ($sorted as $connection) {
            $sentences[] = $this->generateConnectionStory($connection);
        }

        return [
            'title' => 'Life in sentences',
            'sentences' => $sentences,
        ];
    }

    /**
     * Load all connections for a span (as subject or object) that have a connection span, excluding self-loops.
     */
    private function loadConnectionsForSpan(Span $span): Collection
    {
        $asSubject = $span->connectionsAsSubject()
            ->whereNotNull('connection_span_id')
            ->where('child_id', '!=', $span->id)
            ->with(['connectionSpan', 'parent', 'child', 'type'])
            ->get();

        $asObject = $span->connectionsAsObject()
            ->whereNotNull('connection_span_id')
            ->where('parent_id', '!=', $span->id)
            ->with(['connectionSpan', 'parent', 'child', 'type'])
            ->get();

        return $asSubject->concat($asObject);
    }

    /**
     * Filter connections for biography using config (biography.connection_types_include/exclude and exclude_connection_rules).
     */
    private function filterConnectionsForBiography(Collection $connections): Collection
    {
        $include = config('biography.connection_types_include');
        $exclude = config('biography.connection_types_exclude', []);
        $rules = config('biography.exclude_connection_rules', []);

        return $connections->filter(function (Connection $connection) use ($include, $exclude, $rules): bool {
            $typeId = $connection->type_id;
            if ($typeId === null) {
                return false;
            }
            if ($include !== null && ! in_array($typeId, $include, true)) {
                return false;
            }
            if (in_array($typeId, $exclude, true)) {
                return false;
            }
            foreach ($rules as $rule) {
                if (($rule['connection_type_id'] ?? null) !== $typeId) {
                    continue;
                }
                $object = $connection->child;
                if (isset($rule['object_type_id']) && ($object === null || ($object->type_id ?? null) !== $rule['object_type_id'])) {
                    continue;
                }
                if (isset($rule['object_subtype'])) {
                    $subtype = $object?->getMeta('subtype') ?? $object?->metadata['subtype'] ?? null;
                    if ($subtype !== $rule['object_subtype']) {
                        continue;
                    }
                }
                return false;
            }
            return true;
        })->values();
    }

    /**
     * Sort connections by effective start date (earliest first); undated connections last.
     */
    private function sortConnectionsChronologically(Collection $connections): Collection
    {
        return $connections->sort(function (Connection $a, Connection $b): int {
            $da = $a->getEffectiveSortDate();
            $db = $b->getEffectiveSortDate();
            if ($da[0] !== $db[0]) {
                return $da[0] <=> $db[0];
            }
            if ($da[1] !== $db[1]) {
                return $da[1] <=> $db[1];
            }
            return $da[2] <=> $db[2];
        })->values();
    }
    
    /**
     * Process a template for a span
     */
    private function processTemplate(array $templateConfig, Span $span): string
    {
        $template = $templateConfig['template'];
        $ongoingTemplate = $templateConfig['ongoing_template'] ?? null;
        
        // Choose template based on whether span has end date
        if ($ongoingTemplate && !$span->end_year) {
            $template = $ongoingTemplate;
        }
        
        return $this->replaceTemplateVariables($template, $templateConfig['data_methods'], $span);
    }
    
    /**
     * Process a template for a connection
     */
    private function processConnectionTemplate(array $templateConfig, Connection $connection): string
    {
        $template = $templateConfig['template'];
        
        return $this->replaceConnectionTemplateVariables($template, $templateConfig['data_methods'], $connection);
    }
    
    /**
     * Replace template variables with actual data
     */
    private function replaceTemplateVariables(string $template, array $dataMethods, Span $span): string
    {
        $result = $template;
        
        foreach ($dataMethods as $variable => $method) {
            $value = $this->callDataMethod($method, $span);
            $result = str_replace("{{$variable}}", $value, $result);
        }
        
        return $result;
    }
    
    /**
     * Replace template variables for connections
     */
    private function replaceConnectionTemplateVariables(string $template, array $dataMethods, Connection $connection): string
    {
        $result = $template;
        
        foreach ($dataMethods as $variable => $method) {
            $value = $this->callConnectionDataMethod($method, $connection);
            $result = str_replace("{{$variable}}", $value, $result);
        }
        
        return $result;
    }

    /**
     * Custom data method: get phase name for 'during' connections
     */
    private function createPhaseName(Connection $connection): string
    {
        // For 'during' connections, subject is phase or object is phase; prefer the non-connection span
        $phase = null;
        if ($connection->parent && $connection->parent->type_id !== 'connection') {
            $phase = $connection->parent;
        } elseif ($connection->child && $connection->child->type_id !== 'connection') {
            $phase = $connection->child;
        }
        return $phase ? e($phase->name) : 'a phase';
    }

    /**
     * Custom data method: from a 'during' connection, infer the organisation from the linked education
     */
    private function createOrganisationFromDuring(Connection $connection): string
    {
        // Find the linked education connection span (the other end of the 'during') and then the organisation
        $educationSpan = null;
        if ($connection->parent && $connection->parent->type_id === 'connection') {
            $educationSpan = $connection->child; // likely connection span on child
        }
        if ($connection->child && $connection->child->type_id === 'connection') {
            $educationSpan = $connection->parent; // or on parent
        }

        // The educationSpan here should be the connection span for the education link (type_id=connection)
        if ($educationSpan && $educationSpan->type_id === 'connection') {
            // Find the actual education connection that uses this connection span
            $eduConn = Connection::where('connection_span_id', $educationSpan->id)->where('type_id', 'education')->first();
            if ($eduConn && $eduConn->child) {
                return '<a href="' . route('spans.show', $eduConn->child) . '" class="text-decoration-none" title="' . e($eduConn->child->name) . '">' . e($eduConn->child->name) . '</a>';
            }
        }
        return 'the organisation';
    }
    
    /**
     * Call a data method for spans
     */
    private function callDataMethod(string $method, Span $span): string
    {
        return match($method) {
            'createSpanLink' => $this->createSpanLink($span),
            'createDateLink' => $this->createDateLink($span->start_year, $span->start_month, $span->start_day),
            'getOccupation' => $this->getOccupation($span),
            'createCreatorLink' => $this->createCreatorLink($span),
            default => $this->callSpanMethod($method, $span),
        };
    }
    
    /**
     * Call a data method for connections
     */
    private function callConnectionDataMethod(string $method, Connection $connection): string
    {
        return match($method) {
            'createSubjectLink' => $this->createSpanLink($connection->parent),
            'createSpanLink' => $this->createSpanLink($connection->parent),
            'createObjectLink' => $this->createSpanLink($connection->child),
            'createDateLink' => $this->createDateLink(
                $connection->connectionSpan?->start_year,
                $connection->connectionSpan?->start_month,
                $connection->connectionSpan?->start_day
            ),
            'createEndDateLink' => $this->createDateLink(
                $connection->connectionSpan?->end_year,
                $connection->connectionSpan?->end_month,
                $connection->connectionSpan?->end_day
            ),
            'createPredicateLink' => $this->createPredicateLink($connection),
            'getPredicate' => $connection->type->forward_predicate,
            default => $this->callConnectionMethod($method, $connection),
        };
    }
    
    /**
     * Call a span method dynamically
     */
    private function callSpanMethod(string $method, Span $span): string
    {
        if (method_exists($this, $method)) {
            return $this->$method($span);
        }
        
        return '';
    }
    
    /**
     * Call a connection method dynamically
     */
    private function callConnectionMethod(string $method, Connection $connection): string
    {
        if (method_exists($this, $method)) {
            return $this->$method($connection);
        }
        
        return '';
    }
    
    /**
     * Evaluate a condition for a span or connection
     */
    private function evaluateCondition(string $condition, Span|Connection $model): bool
    {
        if ($model instanceof Span) {
            return match($condition) {
                'hasStartYear' => $model->start_year !== null,
                'hasOccupation' => !empty($model->metadata['occupation']),
                'hasCreator' => !empty($model->metadata['creator']),
                default => false,
            };
        } elseif ($model instanceof Connection) {
            return match($condition) {
                'hasStartYear' => $model->connectionSpan && $model->connectionSpan->start_year !== null,
                'hasStartAndEndYear' => $model->connectionSpan && 
                    $model->connectionSpan->start_year !== null && 
                    $model->connectionSpan->end_year !== null &&
                    $model->connectionSpan->start_year !== $model->connectionSpan->end_year,
                'hasStartYearOnly' => $model->connectionSpan && 
                    $model->connectionSpan->start_year !== null && 
                    ($model->connectionSpan->end_year === null || 
                     $model->connectionSpan->start_year === $model->connectionSpan->end_year),
                'hasNoDates' => !$model->connectionSpan || 
                    ($model->connectionSpan->start_year === null && $model->connectionSpan->end_year === null),
                default => false,
            };
        }
        
        return false;
    }
    
    /**
     * Create a clickable link for a span
     */
    private function createSpanLink(Span $span): string
    {
        return sprintf(
            '<a href="%s">%s</a>',
            route('spans.show', $span),
            e($span->name)
        );
    }
    
    /**
     * Create a clickable link for a date
     */
    private function createDateLink(?int $year, ?int $month = null, ?int $day = null): string
    {
        if (!$year) {
            return '<span class="text-muted">unknown date</span>';
        }
        
        // Build the date string for display
        $displayDate = $this->formatDate($year, $month, $day);
        
        // Build the date link
        $dateLink = $this->buildDateLink($year, $month, $day);
        
        return sprintf(
            '<a href="%s">%s</a>',
            route('date.explore', ['date' => $dateLink]),
            e($displayDate)
        );
    }
    
    /**
     * Get occupation for a person
     */
    private function getOccupation(Span $span): string
    {
        return e($span->metadata['occupation'] ?? '');
    }
    
    /**
     * Create a link for the creator of a thing
     */
    private function createCreatorLink(Span $span): string
    {
        if (empty($span->metadata['creator'])) {
            return '';
        }
        
        $creator = Span::find($span->metadata['creator']);
        if (!$creator) {
            return '';
        }
        
        return $this->createSpanLink($creator);
    }
    
    /**
     * Create a clickable link for a connection predicate
     */
    private function createPredicateLink(Connection $connection): string
    {
        return sprintf(
            '<a href="%s">%s</a>',
            route('spans.connections', [
                'subject' => $connection->parent, 
                'predicate' => str_replace(' ', '-', $connection->type->forward_predicate)
            ]),
            e($connection->type->forward_predicate)
        );
    }
    
    /**
     * Format a date based on precision
     */
    private function formatDate(?int $year, ?int $month = null, ?int $day = null): string
    {
        if (!$year) {
            return 'unknown date';
        }
        
        if ($day && $month) {
            return date('j F Y', mktime(0, 0, 0, $month, $day, $year));
        } elseif ($month) {
            return date('F Y', mktime(0, 0, 0, $month, 1, $year));
        } else {
            return (string) $year;
        }
    }
    
    /**
     * Build a date link string
     */
    private function buildDateLink(?int $year, ?int $month = null, ?int $day = null): string
    {
        if (!$year) {
            return '';
        }
        
        if ($day && $month) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        } elseif ($month) {
            return sprintf('%04d-%02d', $year, $month);
        } else {
            return (string) $year;
        }
    }
    
    /**
     * Generate a fallback story for spans
     */
    private function generateFallbackSpanStory(Span $span): string
    {
        $parts = [];
        $parts[] = $this->createSpanLink($span);
        
        if ($span->start_year || $span->end_year) {
            if ($span->end_year) {
                $parts[] = 'existed between';
                $parts[] = $this->createDateLink($span->start_year, $span->start_month, $span->start_day);
                $parts[] = 'and';
                $parts[] = $this->createDateLink($span->end_year, $span->end_month, $span->end_day);
            } else {
                $parts[] = 'started';
                $parts[] = $this->createDateLink($span->start_year, $span->start_month, $span->start_day);
            }
        }
        
        return implode(' ', $parts);
    }
    
    /**
     * Generate a fallback story for connections
     */
    private function generateFallbackConnectionStory(Connection $connection): string
    {
        $parts = [];
        $parts[] = $this->createSpanLink($connection->parent);
        $parts[] = $this->createPredicateLink($connection);
        $parts[] = $this->createSpanLink($connection->child);
        
        if ($connection->connectionSpan && $connection->connectionSpan->start_year) {
            if ($connection->connectionSpan->end_year && $connection->connectionSpan->end_year !== $connection->connectionSpan->start_year) {
                $parts[] = 'between';
                $parts[] = $this->createDateLink(
                    $connection->connectionSpan->start_year,
                    $connection->connectionSpan->start_month,
                    $connection->connectionSpan->start_day
                );
                $parts[] = 'and';
                $parts[] = $this->createDateLink(
                    $connection->connectionSpan->end_year,
                    $connection->connectionSpan->end_month,
                    $connection->connectionSpan->end_day
                );
            } else {
                $parts[] = 'from';
                $parts[] = $this->createDateLink(
                    $connection->connectionSpan->start_year,
                    $connection->connectionSpan->start_month,
                    $connection->connectionSpan->start_day
                );
            }
        }
        
        return implode(' ', $parts);
    }
} 