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
     * Generate a micro story for a span with HTML links.
     *
     * @param bool $asSelf When true, phrase the sentence in second person ("You...") for the span's own biography.
     */
    public function generateSpanStory(Span $span, bool $asSelf = false): string
    {
        $spanType = $span->type_id;
        $templateSet = $this->templates['spans'][$spanType] ?? null;

        $templates = ($asSelf ? ($templateSet['self_templates'] ?? null) : null) ?? $templateSet['templates'] ?? null;

        if ($templates === null) {
            return $this->generateFallbackSpanStory($span, $asSelf);
        }

        // Try each template in order until one works
        foreach ($templates as $templateKey => $templateConfig) {
            if ($this->evaluateCondition($templateConfig['condition'], $span)) {
                return $this->processTemplate($templateConfig, $span);
            }
        }
        
        return $this->generateFallbackSpanStory($span, $asSelf);
    }
    
    /**
     * Generate a micro story for a connection with HTML links.
     *
     * @param string|null $selfId When provided and this connection involves the span with this id,
     *   the sentence is phrased in second person ("You...") from that span's perspective, reversing
     *   subject/object and predicate direction as needed.
     */
    public function generateConnectionStory(Connection $connection, ?string $selfId = null): string
    {
        $connectionType = $connection->type_id;
        
        if (!isset($this->templates['connections'][$connectionType])) {
            return $this->generateFallbackConnectionStory($connection, $selfId);
        }

        $templates = $this->templates['connections'][$connectionType]['templates'];
        
        // Try each template in order until one works
        foreach ($templates as $templateKey => $templateConfig) {
            if ($this->evaluateCondition($templateConfig['condition'], $connection)) {
                return $this->processConnectionTemplate($templateConfig, $connection, $selfId);
            }
        }
        
        return $this->generateFallbackConnectionStory($connection, $selfId);
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
            $intro = $this->generateSpanStory($span, true);
            if ($intro) {
                $sentences[] = $intro;
            }
        }

        foreach ($sorted as $connection) {
            $sentences[] = $this->generateConnectionStory($connection, $span->id);
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
    private function processConnectionTemplate(array $templateConfig, Connection $connection, ?string $selfId = null): string
    {
        $template = $templateConfig['template'];
        
        return $this->replaceConnectionTemplateVariables($template, $templateConfig['data_methods'], $connection, $selfId);
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
    private function replaceConnectionTemplateVariables(string $template, array $dataMethods, Connection $connection, ?string $selfId = null): string
    {
        $result = $template;
        
        foreach ($dataMethods as $variable => $method) {
            $value = $this->callConnectionDataMethod($method, $connection, $selfId);
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
    private function callConnectionDataMethod(string $method, Connection $connection, ?string $selfId = null): string
    {
        return match($method) {
            'createSubjectLink' => $this->createConnectionSubjectLink($connection, $selfId),
            'createSpanLink' => $this->createConnectionSubjectLink($connection, $selfId),
            'createObjectLink' => $this->createConnectionObjectLink($connection, $selfId),
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
            'createPredicateLink' => $this->createPredicateLink($connection, $selfId),
            'getPredicate' => $connection->type->forward_predicate,
            'createBeVerb' => $this->createBeVerb($connection, $selfId),
            'createHaveBeenVerb' => $this->createHaveBeenVerb($connection, $selfId),
            default => $this->callConnectionMethod($method, $connection),
        };
    }

    /**
     * Determine whether this connection is being told from the perspective of the given self span,
     * and if so, whether that span is the object (child) rather than the subject (parent) — i.e.
     * whether the sentence needs to be reversed to read naturally as "You...".
     */
    private function connectionSelfDirection(Connection $connection, ?string $selfId): ?bool
    {
        if (!$selfId) {
            return null;
        }
        if ($connection->parent_id === $selfId) {
            return false; // self is the subject; no reversal needed
        }
        if ($connection->child_id === $selfId) {
            return true; // self is the object; reverse subject/object and use the inverse predicate
        }
        return null;
    }

    /**
     * Naively conjugate a lowercase predicate phrase for second person ("You ...").
     * Only the leading auxiliary verb needs changing (is→are, was→were, has→have);
     * everything else in English past/plain tense predicates is unchanged for "you".
     */
    private function conjugatePredicateForYou(string $predicate): string
    {
        $map = ['is ' => 'are ', 'was ' => 'were ', 'has ' => 'have '];
        foreach ($map as $from => $to) {
            if (str_starts_with($predicate, $from)) {
                return $to . substr($predicate, strlen($from));
            }
        }
        return $predicate;
    }

    /**
     * Subject-slot text for a connection sentence: "You" when told from that span's perspective
     * (whether it is the underlying subject or object), otherwise a link to the actual subject span.
     */
    private function createConnectionSubjectLink(Connection $connection, ?string $selfId): string
    {
        if ($this->connectionSelfDirection($connection, $selfId) !== null) {
            return 'You';
        }
        return $this->createSpanLink($connection->parent);
    }

    /**
     * Object-slot text for a connection sentence: the "other" span when told from self's perspective
     * (which may be the underlying subject if self is the object), otherwise a link to the actual object span.
     */
    private function createConnectionObjectLink(Connection $connection, ?string $selfId): string
    {
        if ($this->connectionSelfDirection($connection, $selfId) === true) {
            return $this->createSpanLink($connection->parent);
        }
        return $this->createSpanLink($connection->child);
    }

    /**
     * "was"/"were" depending on whether this sentence is told in second person.
     */
    private function createBeVerb(Connection $connection, ?string $selfId): string
    {
        return $this->connectionSelfDirection($connection, $selfId) !== null ? 'were' : 'was';
    }

    /**
     * "has been"/"have been" depending on whether this sentence is told in second person.
     */
    private function createHaveBeenVerb(Connection $connection, ?string $selfId): string
    {
        return $this->connectionSelfDirection($connection, $selfId) !== null ? 'have been' : 'has been';
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
     * Create a clickable link for a connection predicate, or plain conjugated text
     * ("was" → "were", etc.) when the sentence is told in second person ("You...").
     */
    private function createPredicateLink(Connection $connection, ?string $selfId = null): string
    {
        $reversed = $this->connectionSelfDirection($connection, $selfId);

        if ($reversed !== null) {
            $predicate = $reversed ? $connection->type->inverse_predicate : $connection->type->forward_predicate;
            return e($this->conjugatePredicateForYou($predicate));
        }

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
    private function generateFallbackSpanStory(Span $span, bool $asSelf = false): string
    {
        $parts = [];
        $parts[] = $asSelf ? 'You' : $this->createSpanLink($span);
        
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
    private function generateFallbackConnectionStory(Connection $connection, ?string $selfId = null): string
    {
        $parts = [];
        $parts[] = $this->createConnectionSubjectLink($connection, $selfId);
        $parts[] = $this->createPredicateLink($connection, $selfId);
        $parts[] = $this->createConnectionObjectLink($connection, $selfId);
        
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