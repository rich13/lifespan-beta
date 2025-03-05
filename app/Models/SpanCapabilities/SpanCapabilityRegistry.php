<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;
use Illuminate\Support\Collection;

/**
 * Registry for span capabilities.
 * 
 * This class manages the registration and retrieval of capabilities for different span types.
 * It acts as a central registry where capabilities are mapped to span types, allowing for:
 * - Dynamic capability loading based on span type
 * - Type-specific capability management
 * - Capability discovery and introspection
 * 
 * Usage:
 * 1. Register capabilities in SpanCapabilityServiceProvider:
 *    SpanCapabilityRegistry::register('place', GeospatialCapability::class);
 * 
 * 2. Access capabilities through the Span model:
 *    $span->getCapabilities()
 *    $span->hasCapability('geospatial')
 *    $span->getCapability('geospatial')
 * 
 * @see \App\Models\SpanCapabilities\SpanCapability
 * @see \App\Providers\SpanCapabilityServiceProvider
 */
class SpanCapabilityRegistry
{
    /**
     * The registered capabilities
     * @var array<string, Collection<string>> Map of span types to capability class names
     */
    protected static array $capabilities = [];

    /**
     * Register a capability for a span type
     * 
     * @param string $spanType The type_id of the span type
     * @param string $capabilityClass The fully qualified class name of the capability
     * @throws \InvalidArgumentException if the capability class doesn't implement SpanCapability
     */
    public static function register(string $spanType, string $capabilityClass): void
    {
        if (!is_subclass_of($capabilityClass, SpanCapability::class)) {
            throw new \InvalidArgumentException(
                "Capability class must implement SpanCapability interface"
            );
        }

        if (!isset(static::$capabilities[$spanType])) {
            static::$capabilities[$spanType] = new Collection();
        }

        static::$capabilities[$spanType]->push($capabilityClass);
    }

    /**
     * Get all capabilities for a span
     *
     * @return Collection<SpanCapability>
     */
    public static function getCapabilities(Span $span): Collection
    {
        if (!isset(static::$capabilities[$span->type_id])) {
            return new Collection();
        }

        return static::$capabilities[$span->type_id]->map(function ($capabilityClass) use ($span) {
            return new $capabilityClass($span);
        });
    }

    /**
     * Check if a span has a specific capability
     * 
     * @param string $capabilityName The name of the capability to check for
     */
    public static function hasCapability(Span $span, string $capabilityName): bool
    {
        return static::getCapabilities($span)->some(function ($capability) use ($capabilityName) {
            return $capability->getName() === $capabilityName;
        });
    }

    /**
     * Get a specific capability for a span
     * 
     * @param string $capabilityName The name of the capability to retrieve
     * @return SpanCapability|null The capability instance or null if not found
     */
    public static function getCapability(Span $span, string $capabilityName): ?SpanCapability
    {
        return static::getCapabilities($span)->first(function ($capability) use ($capabilityName) {
            return $capability->getName() === $capabilityName;
        });
    }

    /**
     * Get all registered span types that have a specific capability
     * 
     * @param string $capabilityName The name of the capability to look for
     * @return array<string> List of span type_ids
     */
    public static function getSpanTypesWithCapability(string $capabilityName): array
    {
        return collect(static::$capabilities)
            ->filter(function ($capabilities, $spanType) use ($capabilityName) {
                return $capabilities->contains(function ($capabilityClass) use ($capabilityName) {
                    return (new $capabilityClass(new Span()))->getName() === $capabilityName;
                });
            })
            ->keys()
            ->all();
    }

    /**
     * Get all capabilities registered for a span type
     * 
     * @param string $spanType The type_id of the span type
     * @return array<string> List of capability names
     */
    public static function getCapabilitiesForType(string $spanType): array
    {
        if (!isset(static::$capabilities[$spanType])) {
            return [];
        }

        return static::$capabilities[$spanType]
            ->map(function ($capabilityClass) {
                return (new $capabilityClass(new Span()))->getName();
            })
            ->all();
    }
} 