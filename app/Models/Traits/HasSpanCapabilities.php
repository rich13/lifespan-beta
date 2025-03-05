<?php

namespace App\Models\Traits;

use App\Models\SpanCapabilities\SpanCapabilityRegistry;
use App\Models\SpanCapabilities\SpanCapability;
use Illuminate\Support\Collection;

/**
 * Base trait for managing span capabilities.
 * 
 * This trait provides the foundation for registering and managing special capabilities
 * that different span types can have. It integrates with the SpanCapabilityRegistry
 * to provide a clean interface for working with capabilities.
 * 
 * Usage:
 * 1. Add this trait to the Span model
 * 2. Add specific capability traits (e.g. HasFamilyCapabilities) to enable features
 * 3. Register capabilities in SpanCapabilityServiceProvider
 * 4. Access capabilities through the methods provided here
 * 
 * Example:
 * ```php
 * // Check for a capability
 * if ($span->hasCapability('geospatial')) {
 *     $span->setCoordinates(lat, lng);
 * }
 * 
 * // Get all capabilities
 * foreach ($span->getCapabilities() as $capability) {
 *     echo $capability->getName();
 * }
 * ```
 * 
 * @see \App\Models\SpanCapabilities\SpanCapability
 * @see \App\Models\SpanCapabilities\SpanCapabilityRegistry
 * @see \App\Providers\SpanCapabilityServiceProvider
 */
trait HasSpanCapabilities
{
    /**
     * Get all capabilities available for this span
     * 
     * @return Collection<SpanCapability>
     */
    public function getCapabilities(): Collection
    {
        return SpanCapabilityRegistry::getCapabilities($this);
    }

    /**
     * Check if this span has a specific capability
     * 
     * @param string $capabilityName The name of the capability to check for
     */
    public function hasCapability(string $capabilityName): bool
    {
        return SpanCapabilityRegistry::hasCapability($this, $capabilityName);
    }

    /**
     * Get a specific capability instance
     * 
     * @param string $capabilityName The name of the capability to retrieve
     * @return SpanCapability|null The capability instance or null if not found
     */
    public function getCapability(string $capabilityName): ?SpanCapability
    {
        return SpanCapabilityRegistry::getCapability($this, $capabilityName);
    }

    /**
     * Get the metadata schema for all capabilities
     * 
     * @return array Combined schema from all available capabilities
     */
    public function getCapabilityMetadataSchema(): array
    {
        return $this->getCapabilities()
            ->filter(function ($capability) {
                return $capability->isAvailable();
            })
            ->map(function ($capability) {
                return $capability->getMetadataSchema();
            })
            ->reduce(function ($schema, $capabilitySchema) {
                return array_merge($schema, $capabilitySchema);
            }, []);
    }

    /**
     * Validate metadata for all capabilities
     * Called automatically before saving
     * 
     * @throws \InvalidArgumentException if validation fails
     */
    protected function validateCapabilityMetadata(): void
    {
        foreach ($this->getCapabilities() as $capability) {
            if ($capability->isAvailable()) {
                $capability->validateMetadata();
            }
        }
    }
} 