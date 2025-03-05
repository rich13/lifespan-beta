<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;

/**
 * Interface for span capabilities.
 * 
 * The capability system allows spans to have specialized functionality based on their type.
 * Each capability represents a distinct set of features that can be added to a span.
 * 
 * Capabilities are:
 * - Self-contained: Each capability handles its own logic and validation
 * - Type-specific: Capabilities are registered for specific span types
 * - Metadata-driven: Capabilities can define and validate their own metadata schema
 * 
 * To create a new capability:
 * 1. Implement this interface
 * 2. Create a corresponding trait for convenient access
 * 3. Register the capability in SpanCapabilityServiceProvider
 * 4. Add the trait to the Span model for the desired type
 * 
 * @see \App\Models\SpanCapabilities\SpanCapabilityRegistry
 * @see \App\Providers\SpanCapabilityServiceProvider
 */
interface SpanCapability
{
    /**
     * Get the unique identifier for this capability
     * This should match the name used in the registry
     */
    public function getName(): string;

    /**
     * Get the span this capability belongs to
     * Used to access the span's data and relationships
     */
    public function getSpan(): Span;

    /**
     * Get the metadata schema for this capability
     * This defines what metadata fields this capability requires/accepts
     * 
     * @return array Schema in JSON Schema format
     */
    public function getMetadataSchema(): array;

    /**
     * Validate the metadata for this capability
     * Called before saving the span to ensure data integrity
     * 
     * @throws \InvalidArgumentException if validation fails
     */
    public function validateMetadata(): void;

    /**
     * Check if this capability is available for the current span
     * This allows capabilities to determine if they should be active
     * based on the span's type or other criteria
     */
    public function isAvailable(): bool;
} 