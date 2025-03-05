<?php

namespace App\Providers;

use App\Models\SpanCapabilities\GeospatialCapability;
use App\Models\SpanCapabilities\FamilyCapability;
use App\Models\SpanCapabilities\SpanCapabilityRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering span capabilities.
 * 
 * This provider maps capabilities to span types, defining which types
 * have access to which specialized functionality.
 * 
 * To add a new capability:
 * 1. Create the capability class implementing SpanCapability
 * 2. Create a trait for convenient access to the capability
 * 3. Register the capability here for the appropriate span types
 * 4. Add the trait to the Span model
 */
class SpanCapabilityServiceProvider extends ServiceProvider
{
    /**
     * Capability mappings
     * Maps span types to their available capabilities
     * 
     * @var array<string, array<string>>
     */
    protected array $capabilities = [
        // Place spans have geospatial features
        'place' => [
            GeospatialCapability::class,
        ],
        
        // Person spans have family relationship features
        'person' => [
            FamilyCapability::class,
        ],

        // Add more mappings as capabilities are created
        // Example:
        // 'band' => [
        //     MusicianCapability::class,
        //     PerformanceCapability::class,
        // ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * Registers all capabilities with the registry.
     */
    public function boot(): void
    {
        foreach ($this->capabilities as $spanType => $capabilities) {
            foreach ($capabilities as $capabilityClass) {
                SpanCapabilityRegistry::register($spanType, $capabilityClass);
            }
        }
    }
} 