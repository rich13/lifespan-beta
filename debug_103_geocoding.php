<?php

require_once 'vendor/autoload.php';

use App\Services\OSMGeocodingService;

// Create the service
$service = new OSMGeocodingService();

// Test with "103" as input
echo "Testing geocoding for '103'...\n";

try {
    $result = $service->geocode('103');
    
    if ($result) {
        echo "Geocoding result:\n";
        echo "Canonical name: " . ($result['canonical_name'] ?? 'NOT SET') . "\n";
        echo "Display name: " . ($result['display_name'] ?? 'NOT SET') . "\n";
        echo "Place type: " . ($result['place_type'] ?? 'NOT SET') . "\n";
        echo "Coordinates: " . json_encode($result['coordinates'] ?? []) . "\n";
        echo "Hierarchy: " . json_encode($result['hierarchy'] ?? []) . "\n";
        
        // Let's also test the extractMeaningfulPlaceName method directly
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractMeaningfulPlaceName');
        $method->setAccessible(true);
        
        // Simulate what the Nominatim API would return for "103"
        $mockNominatimResult = [
            'display_name' => '103, Great Portland Street, Fitzrovia, Camden, London, Greater London, England, United Kingdom',
            'type' => 'house',
            'address' => [
                'house_number' => '103',
                'road' => 'Great Portland Street',
                'suburb' => 'Fitzrovia',
                'city' => 'London',
                'country' => 'United Kingdom'
            ]
        ];
        
        $extractedName = $method->invoke($service, $mockNominatimResult['display_name'], $mockNominatimResult);
        echo "\nDirect extractMeaningfulPlaceName test:\n";
        echo "Input: " . $mockNominatimResult['display_name'] . "\n";
        echo "Extracted: " . $extractedName . "\n";
        
    } else {
        echo "No geocoding result found for '103'\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
