<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\UKParliamentSparqlService;

// Initialize the service
$sparqlService = new UKParliamentSparqlService();

echo "🔍 Investigating SPARQL endpoints for UK government roles...\n\n";

// Test 1: Fetch all government roles
echo "📊 Fetching all government roles from 1900-2025...\n";
$allRoles = $sparqlService->fetchMPsGovernmentRoles('1900-01-01', '2025-12-31');
echo "Found " . count($allRoles) . " government role records\n\n";

// Test 2: Filter for Prime Ministers specifically
echo "👑 Filtering for Prime Ministers...\n";
$primeMinisters = array_filter($allRoles, function($role) {
    return strpos(strtolower($role['role_label'] ?? ''), 'prime minister') !== false ||
           strpos(strtolower($role['role_label'] ?? ''), 'first lord of the treasury') !== false;
});

echo "Found " . count($primeMinisters) . " Prime Minister records\n";

// Display Prime Minister data
foreach ($primeMinisters as $pm) {
    echo "- " . $pm['person_label'] . " (" . $pm['role_label'] . ") ";
    echo $pm['start_date'] . " to " . ($pm['end_date'] ?? 'ongoing');
    if ($pm['party_label']) {
        echo " - " . $pm['party_label'];
    }
    echo "\n";
}

echo "\n";

// Test 3: Use the dedicated Prime Ministers query
echo "👑 Using dedicated Prime Ministers SPARQL query...\n";
$dedicatedPMs = $sparqlService->fetchPrimeMinisters('1900-01-01', '2025-12-31');
echo "Found " . count($dedicatedPMs) . " Prime Minister records via dedicated query\n";

foreach ($dedicatedPMs as $pm) {
    echo "- " . $pm['person_label'] . " ";
    echo $pm['start_date'] . " to " . ($pm['end_date'] ?? 'ongoing');
    if ($pm['party_label']) {
        echo " - " . $pm['party_label'];
    }
    echo "\n";
}

echo "\n";

// Test 4: Get Prime Ministers with grouped terms
echo "📋 Getting Prime Ministers with grouped terms...\n";
$pmWithTerms = $sparqlService->getPrimeMinistersWithTerms();
echo "Found " . count($pmWithTerms) . " unique Prime Ministers\n";

foreach ($pmWithTerms as $pm) {
    echo "- " . $pm['name'] . " (" . ($pm['party'] ?? 'Unknown party') . ")\n";
    foreach ($pm['terms'] as $term) {
        echo "  Term: " . $term['start_date'] . " to " . ($term['end_date'] ?? 'ongoing');
        if ($term['party']) {
            echo " (" . $term['party'] . ")";
        }
        echo "\n";
    }
    echo "\n";
}

// Test 5: Search for specific people
echo "🔍 Testing person search...\n";
$testPeople = ['Winston Churchill', 'Tony Blair', 'Keir Starmer'];

foreach ($testPeople as $person) {
    echo "Searching for: $person\n";
    $roles = $sparqlService->searchPersonRoles($person);
    echo "Found " . count($roles) . " government roles\n";
    
    foreach ($roles as $role) {
        echo "  - " . $role['role_label'] . " ";
        echo $role['start_date'] . " to " . ($role['end_date'] ?? 'ongoing');
        if ($role['party_label']) {
            echo " (" . $role['party_label'] . ")";
        }
        echo "\n";
    }
    echo "\n";
}

echo "✅ SPARQL investigation complete!\n"; 