<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * Represents a type of span with its specific configuration
 * 
 * @property string $type_id Type identifier (primary key)
 * @property string $name Human-readable name
 * @property string $description Description of the type
 * @property array $metadata Type-specific configuration and schema
 * @property \Carbon\Carbon $created_at When the type was created
 * @property \Carbon\Carbon $updated_at When the type was last updated
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Span> $spans Spans of this type
 */
class SpanType extends Model
{
    // Precision levels for dates
    public const PRECISION_YEAR = 'year';
    public const PRECISION_MONTH = 'month';
    public const PRECISION_DAY = 'day';

    // Span states
    public const STATE_PLACEHOLDER = 'placeholder';  // Date not yet known
    public const STATE_DRAFT = 'draft';             // Being worked on
    public const STATE_COMPLETE = 'complete';       // Ready for viewing

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'type_id';

    /**
     * The "type" of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'type_id',
        'name',
        'description',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Get the base validation rules that apply to all spans
     */
    public function getBaseValidationRules(): array
    {
        return [
            // Core fields
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:spans,slug',
            'state' => [
                'required',
                Rule::in([self::STATE_PLACEHOLDER, self::STATE_DRAFT, self::STATE_COMPLETE])
            ],
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'sources' => 'nullable|array',
            'sources.*' => 'url',

            // Temporal fields
            'start_year' => [
                'required_unless:state,placeholder',
                'nullable',
                'integer',
                'min:1',
                'max:9999'
            ],
            'start_month' => [
                'nullable',
                'integer',
                'between:1,12',
                Rule::requiredIf(fn() => 
                    $this->state !== self::STATE_PLACEHOLDER &&
                    in_array($this->metadata['start_precision'] ?? 'year', 
                    [self::PRECISION_MONTH, self::PRECISION_DAY])
                ),
            ],
            'start_day' => [
                'nullable',
                'integer',
                'between:1,31',
                Rule::requiredIf(fn() => 
                    $this->state !== self::STATE_PLACEHOLDER &&
                    ($this->metadata['start_precision'] ?? 'year') === self::PRECISION_DAY
                ),
            ],
            'end_year' => 'nullable|integer|min:1|max:9999',
            'end_month' => [
                'nullable',
                'integer',
                'between:1,12',
                Rule::requiredIf(fn() => 
                    $this->end_year && 
                    in_array($this->metadata['end_precision'] ?? 'year',
                    [self::PRECISION_MONTH, self::PRECISION_DAY])
                ),
            ],
            'end_day' => [
                'nullable',
                'integer',
                'between:1,31',
                Rule::requiredIf(fn() => 
                    $this->end_year && 
                    ($this->metadata['end_precision'] ?? 'year') === self::PRECISION_DAY
                ),
            ],
            'start_precision' => [
                'required',
                Rule::in([self::PRECISION_YEAR, self::PRECISION_MONTH, self::PRECISION_DAY]),
            ],
            'end_precision' => [
                'required',
                Rule::in([self::PRECISION_YEAR, self::PRECISION_MONTH, self::PRECISION_DAY]),
            ],
        ];
    }

    /**
     * Get the base metadata schema that applies to all spans
     */
    public function getBaseMetadataSchema(): array
    {
        return [
            'description' => [
                'type' => 'markdown',
                'label' => 'Description',
                'component' => 'markdown-editor',
                'help' => 'Public description of this span, supports Markdown formatting',
                'required' => false,
            ],
            'notes' => [
                'type' => 'text',
                'label' => 'Editor Notes',
                'component' => 'textarea',
                'help' => 'Private notes for editors, not shown publicly',
                'required' => false,
            ],
            'sources' => [
                'type' => 'array',
                'label' => 'Sources',
                'component' => 'source-list',
                'help' => 'URLs to source material (e.g., Wikipedia pages)',
                'required' => false,
                'item_schema' => [
                    'type' => 'url',
                    'label' => 'URL',
                    'component' => 'url-input'
                ]
            ],
            'state' => [
                'type' => 'string',
                'label' => 'State',
                'component' => 'select',
                'options' => [
                    [
                        'value' => self::STATE_PLACEHOLDER,
                        'label' => 'Placeholder (date unknown)'
                    ],
                    [
                        'value' => self::STATE_DRAFT,
                        'label' => 'Draft (work in progress)'
                    ],
                    [
                        'value' => self::STATE_COMPLETE,
                        'label' => 'Complete (ready for viewing)'
                    ]
                ],
                'required' => true,
                'default' => self::STATE_DRAFT
            ]
        ];
    }

    /**
     * Get the combined validation rules (base + type-specific)
     */
    public function getValidationRules(): array
    {
        return array_merge(
            $this->getBaseValidationRules(),
            $this->metadata['validation_rules'] ?? []
        );
    }

    /**
     * Get the combined metadata schema (base + type-specific)
     */
    public function getMetadataSchema(): array
    {
        return array_merge(
            $this->getBaseMetadataSchema(),
            $this->metadata['schema'] ?? []
        );
    }

    /**
     * Get all spans of this type
     *
     * @return HasMany<Span>
     */
    public function spans(): HasMany
    {
        return $this->hasMany(Span::class, 'type_id', 'type_id');
    }

    /**
     * Get the required metadata fields for spans of this type
     *
     * @return array<string>
     */
    public function getRequiredMetadataFields(): array
    {
        return $this->metadata['required_fields'] ?? [];
    }

    /**
     * Check if a date is valid for this type's precision requirements
     */
    public function isValidDate(array $date, string $precision = null): bool
    {
        $precision = $precision ?? ($this->metadata['default_precision'] ?? self::PRECISION_YEAR);
        
        // Basic year validation
        if (!isset($date['year']) || !is_numeric($date['year'])) {
            return false;
        }

        if ($precision === self::PRECISION_YEAR) {
            return true;
        }

        // Month validation
        if (!isset($date['month']) || !is_numeric($date['month']) || 
            $date['month'] < 1 || $date['month'] > 12) {
            return false;
        }

        if ($precision === self::PRECISION_MONTH) {
            return true;
        }

        // Day validation
        if (!isset($date['day']) || !is_numeric($date['day'])) {
            return false;
        }

        return checkdate($date['month'], $date['day'], $date['year']);
    }
} 