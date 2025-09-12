<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\OSMGeocodingService;

// Test the building name extraction
echo "Testing building name extraction:\n\n";

$osmService = new OSMGeocodingService();

// Test cases for building addresses
$testCases = [
    [
        'input' => '10 Kensington Church Walk, Kensington and Chelsea, W8, London, UK',
        'type' => 'house',
        'address' => ['house_number' => '10', 'road' => 'Kensington Church Walk'],
        'expected' => '10 Kensington Church Walk, Kensington and Chelsea'
    ],
    [
        'input' => '103 Great Portland Street, Fitzrovia, London, UK',
        'type' => 'building',
        'address' => ['house_number' => '103', 'road' => 'Great Portland Street'],
        'expected' => '103 Great Portland Street, Fitzrovia'
    ],
    [
        'input' => '221B Baker Street, Marylebone, London, UK',
        'type' => 'house',
        'address' => ['house_number' => '221B', 'road' => 'Baker Street'],
        'expected' => '221B Baker Street, Marylebone'
    ],
    [
        'input' => 'London, England, UK',
        'type' => 'city',
        'address' => [],
        'expected' => 'London'
    ],
    [
        'input' => 'Big Ben, Westminster, London, UK',
        'type' => 'monument',
        'address' => [],
        'expected' => 'Big Ben'
    ]
];

foreach ($testCases as $i => $testCase) {
    echo "Test " . ($i + 1) . ":\n";
    echo "Input: '{$testCase['input']}'\n";
    echo "Type: {$testCase['type']}\n";
    
    // Use reflection to test the private method
    $reflection = new ReflectionClass($osmService);
    $method = $reflection->getMethod('extractMeaningfulPlaceName');
    $method->setAccessible(true);
    
    $nominatimResult = [
        'type' => $testCase['type'],
        'address' => $testCase['address']
    ];
    
    $result = $method->invoke($osmService, $testCase['input'], $nominatimResult);
    
    echo "Result: '{$result}'\n";
    echo "Expected: '{$testCase['expected']}'\n";
    echo "Match: " . ($result === $testCase['expected'] ? '✅' : '❌') . "\n\n";
}

echo "All tests completed!\n";
