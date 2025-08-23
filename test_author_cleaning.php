<?php

function removeNestedTemplates($text) {
    $original = $text;
    $maxIterations = 10;
    $iteration = 0;
    
    while ($iteration < $maxIterations) {
        // Find and remove the innermost templates first
        $text = preg_replace('/\{\{[^}]*\{\{[^}]*\}\}[^}]*\}\}/', '', $text);
        $text = preg_replace('/\{\{[^}]*\}\}/', '', $text);
        
        // If no changes were made, we're done
        if ($text === $original) {
            break;
        }
        
        $original = $text;
        $iteration++;
    }
    
    return $text;
}

function extractTemplateContent($text) {
    // Extract content from simple templates like {{User|John Doe}} -> John Doe
    $text = preg_replace('/\{\{User\|([^}]*)\}\}/', '$1', $text);
    $text = preg_replace('/\{\{([^|}]+)\|([^}]*)\}\}/', '$2', $text);
    
    return $text;
}

function cleanDescription($description) {
    if (empty($description)) { return ''; }
    
    // First, extract content from simple templates
    $description = extractTemplateContent($description);
    
    // Remove nested templates recursively
    $description = removeNestedTemplates($description);
    
    // Remove language tags like {{en|1=...}}
    $description = preg_replace('/\{\{[a-z]{2}\|1=(.*?)\}\}/', '$1', $description);
    $description = preg_replace('/\{\{[a-z]{2}\|(.*?)\}\}/', '$1', $description);
    $description = preg_replace('/\{\{[a-z]{2}\}\}/', '', $description);
    
    // Remove wiki links [[text]] or [[text|display]]
    $description = preg_replace('/\[\[([^|\]]*?)\]\]/', '$1', $description);
    $description = preg_replace('/\[\[([^|]*?)\|([^\]]*?)\]\]/', '$2', $description);
    
    // Remove bold and italic formatting
    $description = preg_replace('/\'\'\'(.*?)\'\'\'/', '$1', $description);
    $description = preg_replace('/\'\'(.*?)\'\'/', '$1', $description);
    
    // Remove HTML tags
    $description = strip_tags($description);
    
    // Clean up whitespace
    $description = preg_replace('/\s+/', ' ', $description);
    
    return trim($description);
}

// Test the specific case from Tony Blair image
$testAuthor = "© {{European Union|{{LangSwitch|en=en|fr=fr}}|nolink=1}}, 2010";
echo "Original: " . $testAuthor . "\n";
echo "Cleaned: " . cleanDescription($testAuthor) . "\n";

// Test other common patterns
$testCases = [
    "{{User|John Doe}}",
    "[[User:Jane Smith|Jane Smith]]",
    "'''Bold Author'''",
    "''Italic Author''",
    "{{en|1=English Author}}",
    "{{fr|French Author}}"
];

foreach ($testCases as $test) {
    echo "\nOriginal: " . $test . "\n";
    echo "Cleaned: " . cleanDescription($test) . "\n";
}
