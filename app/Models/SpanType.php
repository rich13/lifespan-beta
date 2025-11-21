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
     * Get the base metadata schema for all spans
     */
    public function getBaseMetadataSchema(): array
    {
        return [];
    }

    /**
     * Get the combined metadata schema (base + type-specific)
     * 
     * This method now supports span arrays in the schema, allowing fields to reference
     * other spans. This enables relationships like band members, event participants, etc.
     * to be defined in the metadata schema.
     * 
     * For span array fields:
     * - type: 'array'
     * - array_item_schema.type: 'span'
     * - array_item_schema.component: 'span-input' (to be implemented)
     * - array_item_schema.span_type: optional restriction on span type
     * 
     * Example usage:
     * {
     *   "members": {
     *     "type": "array",
     *     "label": "Band Members",
     *     "component": "array-input",
     *     "array_item_schema": {
     *       "type": "span",
     *       "label": "Member",
     *       "component": "span-input",
     *       "span_type": "person"  // Optional: restrict to person spans only
     *     }
     *   }
     * }
     * 
     * TODO: Implementation requirements:
     * 1. Create span-input component that allows:
     *    - Searching existing spans
     *    - Creating new placeholder spans
     *    - Handling permissions
     * 2. Update validation to verify span existence and permissions
     * 3. Add UI for managing span arrays in the edit view
     * 4. Consider adding span preview/summary component
     * 5. Handle deletion/orphaning of placeholder spans
     */
    public function getMetadataSchema(): array
    {
        $baseSchema = $this->getBaseMetadataSchema();
        $typeSchema = $this->metadata['schema'] ?? [];

        // Convert old format to new if needed
        foreach ($typeSchema as $field => $schema) {
            if (!isset($schema['type'])) {
                $typeSchema[$field] = [
                    'type' => 'text',
                    'label' => $schema['label'] ?? $field,
                    'component' => $schema['component'] ?? 'text-input',
                    'help' => $schema['help'] ?? '',
                    'required' => $schema['required'] ?? false
                ];
            }
        }

        return array_merge($baseSchema, $typeSchema);
    }

    /**
     * Get validation rules for the metadata fields
     * 
     * Now includes support for span array validation:
     * - Validates that referenced spans exist
     * - Checks span type restrictions if specified
     * - Ensures user has permission to reference spans
     */
    public function getValidationRules(): array
    {
        $rules = [];
        $schema = $this->getMetadataSchema();

        foreach ($schema as $field => $config) {
            $fieldRules = [];

            // Add required rule if field is required
            if ($config['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Add type-specific validation rules
            switch ($config['type']) {
                case 'text':
                    $fieldRules[] = 'string';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'array':
                    $fieldRules[] = 'array';
                    if (isset($config['array_item_schema'])) {
                        // Handle span array validation
                        if ($config['array_item_schema']['type'] === 'span') {
                            // TODO: Implement custom validation rule for spans that:
                            // 1. Verifies each span ID exists
                            // 2. Checks span type restrictions
                            // 3. Validates user permissions
                            $fieldRules[] = 'array';
                            $fieldRules[] = 'exists:spans,id';
                            
                            // If span type is restricted, add validation
                            if (isset($config['array_item_schema']['span_type'])) {
                                $fieldRules[] = 'span_type:' . $config['array_item_schema']['span_type'];
                            }
                        } else {
                            // Handle other array types as before
                            switch ($config['array_item_schema']['type']) {
                                case 'url':
                                    $fieldRules[] = 'url';
                                    break;
                                case 'number':
                                    $fieldRules[] = 'numeric';
                                    break;
                            }
                        }
                    }
                    break;
                case 'select':
                    if (isset($config['options'])) {
                        // Handle both simple string arrays and object arrays with 'value' property
                        if (is_array($config['options']) && !empty($config['options'])) {
                            if (is_string($config['options'][0])) {
                                // Simple string array
                                $values = $config['options'];
                            } else {
                                // Object array with 'value' property
                                $values = array_column($config['options'], 'value');
                            }
                            $fieldRules[] = 'in:' . implode(',', $values);
                        }
                    }
                    break;
                case 'markdown':
                    $fieldRules[] = 'string';
                    break;
            }

            // Add any custom validation rules
            if (isset($this->metadata['validation_rules'][$field])) {
                $customRules = is_array($this->metadata['validation_rules'][$field])
                    ? $this->metadata['validation_rules'][$field]
                    : explode('|', $this->metadata['validation_rules'][$field]);
                $fieldRules = array_merge($fieldRules, $customRules);
            }

            $rules["metadata.$field"] = $fieldRules;
        }

        return $rules;
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

    /**
     * Check if this span type is timeless (doesn't require dates)
     */
    public function isTimeless(): bool
    {
        return $this->metadata['timeless'] ?? false;
    }

    /**
     * Get all timeless span types
     */
    public static function getTimelessTypes(): array
    {
        return static::where('metadata->timeless', true)
            ->pluck('type_id')
            ->toArray();
    }

    /**
     * Get the available subtype options for this span type
     * 
     * @return array<string>
     */
    public function getSubtypeOptions(): array
    {
        // Check if subtypes are defined in the metadata schema
        $schema = $this->getMetadataSchema();
        
        if (isset($schema['subtype']['options'])) {
            $options = $schema['subtype']['options'];
            
            // Handle both simple string arrays and object arrays with 'value' property
            if (is_array($options) && !empty($options)) {
                if (is_string($options[0])) {
                    return $options;
                } else {
                    return array_column($options, 'value');
                }
            }
        }
        
        return [];
    }
} 