<?php

namespace App\Services\Comparison\DTOs;

/**
 * Data Transfer Object for span comparisons.
 * 
 * This class represents a single comparison insight between two spans.
 * It encapsulates all the data needed to display a comparison point,
 * including its visualization on a timeline and any contextual information.
 */
class ComparisonDTO
{
    /**
     * Create a new comparison DTO.
     *
     * @param string $icon Bootstrap icon class for visual representation (e.g., 'bi-calendar-event')
     * @param string $text Main comparison text describing the relationship
     * @param int $year The year this comparison point occurs
     * @param string|null $subtext Additional context about the comparison (e.g., connections at the time)
     * @param int|null $duration Duration in years if this is a period comparison
     * @param string|null $type Type of comparison (e.g., 'birth', 'death', 'overlap')
     * @param array $metadata Additional metadata for future expansion
     */
    public function __construct(
        public readonly string $icon,
        public readonly string $text,
        public readonly int $year,
        public readonly ?string $subtext = null,
        public readonly ?int $duration = null,
        public readonly ?string $type = null,
        public readonly array $metadata = []
    ) {}

    /**
     * Create a DTO from an array of data.
     *
     * @param array $data Array containing comparison data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        return new self(
            icon: $data['icon'],
            text: $data['text'],
            year: $data['year'],
            subtext: $data['subtext'] ?? null,
            duration: $data['duration'] ?? null,
            type: $data['type'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'icon' => $this->icon,
            'text' => $this->text,
            'year' => $this->year,
            'subtext' => $this->subtext,
            'duration' => $this->duration,
            'type' => $this->type,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get the end year if this comparison has a duration.
     *
     * @return int|null
     */
    public function getEndYear(): ?int
    {
        return $this->duration ? $this->year + $this->duration : null;
    }
} 