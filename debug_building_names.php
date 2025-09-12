<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\OSMGeocodingService;

// Test the building name extraction with debug output
echo "Debugging building name extraction:\n\n";

$osmService = new OSMGeocodingService();

// Test case
$input = '10 Kensington Church Walk, Kensington and Chelsea, W8, London, UK';
$type = 'house';
$address = ['house_number' => '10', 'road' => 'Kensington Church Walk'];

echo "Input: '{$input}'\n";
echo "Type: {$type}\n";

// Split the canonical name by commas
$parts = array_map('trim', explode(',', $input));
echo "Parts: " . json_encode($parts) . "\n";

// Check if the first part starts with a number (house number + road)
$firstPart = $parts[0];
echo "First part: '{$firstPart}'\n";
echo "Starts with number: " . (preg_match('/^\d+[A-Za-z]?\s/', $firstPart) ? 'Yes' : 'No') . "\n";

if (preg_match('/^\d+[A-Za-z]?\s/', $firstPart)) {
    echo "This is a house number with road\n";
    $placeName = $firstPart;
    echo "Using first part as is: '{$placeName}'\n";
        
    if (count($parts) >= 3) {
        echo "Looking for city part...\n";
        for ($i = 2; $i < min(5, count($parts)); $i++) {
            $part = $parts[$i];
            echo "Part {$i}: '{$part}'\n";
            echo "  Length: " . strlen($part) . "\n";
            echo "  Is postal code pattern: " . (preg_match('/^[A-Z0-9\s]+$/', $part) ? 'Yes' : 'No') . "\n";
            echo "  Is short postal code: " . (preg_match('/^[A-Z]\d+$/', $part) ? 'Yes' : 'No') . "\n";
            
            if (strlen($part) > 3 && !preg_match('/^[A-Z0-9\s]+$/', $part) && !preg_match('/^[A-Z]\d+$/', $part)) {
                echo "  -> This is a valid city part!\n";
                $placeName .= ', ' . $part;
                break;
            } else {
                echo "  -> Skipping this part\n";
            }
        }
    }
    
    echo "Final result: '{$placeName}'\n";
}

echo "\nDone!\n";
