#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Ramsey\Uuid\Uuid;

// Configuration
$yamlFile = $argv[1] ?? null;
if (!$yamlFile) {
    echo "Usage: php import_legacy_yaml.php <yaml_file>\n";
    exit(1);
}

if (!file_exists($yamlFile)) {
    echo "YAML file not found: $yamlFile\n";
    exit(1);
}

$parentSpanId = 'ecb0bfb2-d397-401b-9ac9-8d3a16c3dec6'; // Your span ID
$yamlDir = __DIR__ . '/../legacy/data/spans';

// Database connection
$db = new PDO(
    'pgsql:host=localhost;dbname=lifespan',
    'lifespan',
    'lifespan',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get admin user ID
$stmt = $db->prepare("SELECT id FROM users WHERE email = 'richard@northover.info'");
$stmt->execute();
$adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$adminUser) {
    throw new Exception("Admin user not found");
}
$adminUserId = $adminUser['id'];
echo "Using admin user ID: " . $adminUserId . "\n";

function parseDate($dateStr) {
    if (!$dateStr) return null;
    
    // Handle year-only dates
    if (preg_match('/^\d{4}$/', $dateStr)) {
        return [
            'year' => (int)$dateStr,
            'month' => null,
            'day' => null,
            'precision' => 'year'
        ];
    }
    
    $date = new DateTime($dateStr);
    return [
        'year' => (int)$date->format('Y'),
        'month' => (int)$date->format('m'),
        'day' => (int)$date->format('d'),
        'precision' => 'day'
    ];
}

function createSubspan($db, $parentId, $data, $createdBy) {
    echo "Creating subspan with created_by: " . $createdBy . "\n";
    $startDate = parseDate($data['start']);
    $endDate = parseDate($data['end'] ?? null);
    
    if (!$startDate) {
        throw new Exception("Start date is required");
    }
    
    // Check if a similar span already exists
    $name = $data['institution'] ?? $data['employer'] ?? $data['location'];

    // First check for an exact match (same name, type, dates)
    $stmt = $db->prepare("
        SELECT s.id 
        FROM spans s
        WHERE s.name = :name
        AND s.type = :type
        AND s.start_year = :start_year
        AND (s.end_year = :end_year OR (s.end_year IS NULL AND :end_year IS NULL))
        LIMIT 1
    ");
    
    $stmt->execute([
        'name' => $name,
        'type' => $data['type'],
        'start_year' => $startDate['year'],
        'end_year' => $endDate ? $endDate['year'] : null
    ]);
    
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "Found existing span: " . $existing['id'] . "\n";
        
        // Check if relationship already exists
        $relationshipStmt = $db->prepare("
            SELECT 1 
            FROM relationships 
            WHERE parent_id = :parent_id 
            AND child_id = :child_id 
            AND type = :type
        ");
        
        $relationshipStmt->execute([
            'parent_id' => $parentId,
            'child_id' => $existing['id'],
            'type' => $data['type'] === 'residence' ? 'at_residence' : 'at_' . $data['type']
        ]);
        
        $hasRelationship = $relationshipStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hasRelationship) {
            // Create relationship to existing span
            echo "Creating relationship to existing span\n";
            $relationshipStmt = $db->prepare("
                INSERT INTO relationships (parent_id, child_id, type)
                VALUES (:parent_id, :child_id, :type)
            ");
            
            $relationshipStmt->execute([
                'parent_id' => $parentId,
                'child_id' => $existing['id'],
                'type' => $data['type'] === 'residence' ? 'at_residence' : 'at_' . $data['type']
            ]);
        }
        
        return $existing['id'];
    }
    
    // Create the period span
    $stmt = $db->prepare("
        INSERT INTO spans (
            id, name, type,
            start_year, start_month, start_day, start_precision_level,
            end_year, end_month, end_day, end_precision_level,
            metadata, created_by
        ) VALUES (
            uuid_generate_v4(),
            :name, :type,
            :start_year, :start_month, :start_day, :start_precision_level,
            :end_year, :end_month, :end_day, :end_precision_level,
            :metadata::jsonb, :created_by
        )
        RETURNING id
    ");
    
    $stmt->execute([
        'name' => $name,
        'type' => $data['type'],
        'start_year' => $startDate['year'],
        'start_month' => $startDate['month'],
        'start_day' => $startDate['day'],
        'start_precision_level' => $startDate['precision'],
        'end_year' => $endDate ? $endDate['year'] : null,
        'end_month' => $endDate ? $endDate['month'] : null,
        'end_day' => $endDate ? $endDate['day'] : null,
        'end_precision_level' => $endDate ? $endDate['precision'] : null,
        'metadata' => json_encode(array_diff_key($data, array_flip(['start', 'end', 'institution', 'employer', 'location', 'type']))),
        'created_by' => $createdBy
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        throw new Exception("Failed to create span");
    }
    $spanId = $result['id'];
    
    // Create the relationship linking the person to this period
    $relationshipStmt = $db->prepare("
        INSERT INTO relationships (parent_id, child_id, type)
        VALUES (:parent_id, :child_id, :type)
    ");
    
    $relationshipStmt->execute([
        'parent_id' => $parentId,
        'child_id' => $spanId,
        'type' => $data['type'] === 'residence' ? 'at_residence' : 'at_' . $data['type']  // Use 'at_residence' for residence connections
    ]);
    
    return $spanId;
}

// Main import logic
try {
    $db->beginTransaction();
    
    // Read YAML file
    $data = Yaml::parseFile($yamlFile);
    
    // Get the span ID from the YAML
    $parentSpanId = $data['id'];
    if (!$parentSpanId) {
        throw new Exception("No ID found in YAML file");
    }

    // Helper function to get or create a placeholder person span
    function getOrCreatePersonSpan($db, $name, $adminUserId, $metadata = []) {
        $stmt = $db->prepare("
            SELECT id FROM spans 
            WHERE name = ? AND type = 'person'
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$person) {
            // Create a placeholder person span
            $personId = Uuid::uuid4()->toString();
            $db->prepare("
                INSERT INTO spans (id, name, type, metadata, created_by)
                VALUES (?, ?, 'person', ?::jsonb, ?)
            ")->execute([$personId, $name, json_encode($metadata), $adminUserId]);
            return $personId;
        }
        
        return $person['id'];
    }

    // Process family members first to get their IDs
    $familyIds = [
        'parents' => [],
        'children' => []
    ];

    if (isset($data['family'])) {
        if (isset($data['family']['mother'])) {
            $familyIds['parents'][] = getOrCreatePersonSpan($db, $data['family']['mother'], $adminUserId, [
                'placeholder' => true,
                'gender' => 'female'
            ]);
        }
        if (isset($data['family']['father'])) {
            $familyIds['parents'][] = getOrCreatePersonSpan($db, $data['family']['father'], $adminUserId, [
                'placeholder' => true,
                'gender' => 'male'
            ]);
        }
        if (!empty($data['family']['children'])) {
            foreach ($data['family']['children'] as $childName) {
                $familyIds['children'][] = getOrCreatePersonSpan($db, $childName, $adminUserId, [
                    'placeholder' => true
                ]);
            }
        }
    }

    // Update the metadata to include family member UUIDs
    $metadata = array_diff_key($data, array_flip(['id', 'name', 'type', 'start', 'end']));
    $metadata['family'] = $familyIds;

    // First create or update the main person span
    $personStmt = $db->prepare("
        INSERT INTO spans (
            id, name, type,
            start_year, start_month, start_day, start_precision_level,
            end_year, end_month, end_day, end_precision_level,
            metadata, created_by
        ) VALUES (
            :id, :name, :type,
            :start_year, :start_month, :start_day, :start_precision_level,
            :end_year, :end_month, :end_day, :end_precision_level,
            :metadata::jsonb, :created_by
        )
        ON CONFLICT (id) DO UPDATE SET
            name = EXCLUDED.name,
            type = EXCLUDED.type,
            start_year = EXCLUDED.start_year,
            start_month = EXCLUDED.start_month,
            start_day = EXCLUDED.start_day,
            start_precision_level = EXCLUDED.start_precision_level,
            end_year = EXCLUDED.end_year,
            end_month = EXCLUDED.end_month,
            end_day = EXCLUDED.end_day,
            end_precision_level = EXCLUDED.end_precision_level,
            metadata = EXCLUDED.metadata
    ");
    
    $startDate = parseDate($data['start']);
    $endDate = parseDate($data['end'] ?? null);
    
    $personStmt->execute([
        'id' => $parentSpanId,
        'name' => $data['name'],
        'type' => $data['type'],
        'start_year' => $startDate['year'],
        'start_month' => $startDate['month'],
        'start_day' => $startDate['day'],
        'start_precision_level' => $startDate['precision'],
        'end_year' => $endDate ? $endDate['year'] : null,
        'end_month' => $endDate ? $endDate['month'] : null,
        'end_day' => $endDate ? $endDate['day'] : null,
        'end_precision_level' => $endDate ? $endDate['precision'] : null,
        'metadata' => json_encode($metadata),
        'created_by' => $adminUserId
    ]);
    
    // Import education spans
    if (isset($data['education'])) {
        foreach ($data['education'] as $edu) {
            $edu['type'] = 'education';
            createSubspan($db, $parentSpanId, $edu, $adminUserId);
        }
    }
    
    // Import work spans
    if (isset($data['work'])) {
        foreach ($data['work'] as $work) {
            $work['type'] = 'work';
            createSubspan($db, $parentSpanId, $work, $adminUserId);
        }
    }
    
    // Import place spans
    if (isset($data['places'])) {
        foreach ($data['places'] as $place) {
            $place['type'] = 'residence';  // Map 'places' to 'residence' type
            createSubspan($db, $parentSpanId, $place, $adminUserId);
        }
    }
    
    // Import relationships
    if (isset($data['relationships'])) {
        foreach ($data['relationships'] as $rel) {
            // First create a span for the related person if they don't exist
            $relatedPersonStmt = $db->prepare("
                SELECT id FROM spans 
                WHERE name = ? AND type = 'person'
                LIMIT 1
            ");
            $relatedPersonStmt->execute([$rel['person']]);
            $relatedPerson = $relatedPersonStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$relatedPerson) {
                // Create a placeholder person span
                $relatedPersonId = Uuid::uuid4()->toString();
                $db->prepare("
                    INSERT INTO spans (id, name, type, metadata, state, created_by)
                    VALUES (?, ?, 'person', ?::jsonb, 'placeholder', ?)
                ")->execute([
                    $relatedPersonId, 
                    $rel['person'], 
                    json_encode(['placeholder' => true]),
                    $adminUserId
                ]);
                
                // Add user access for the placeholder span
                $db->prepare("
                    INSERT INTO user_spans (user_id, span_id)
                    VALUES (?, ?)
                ")->execute([$adminUserId, $relatedPersonId]);
            } else {
                $relatedPersonId = $relatedPerson['id'];
            }
            
            // Check if a relationship span already exists between these two people
            $relationshipName = $rel['relationshipType'] . ' of ' . $data['name'] . ' and ' . $rel['person'];
            $existingRelationshipStmt = $db->prepare("
                SELECT s.id 
                FROM spans s
                JOIN relationships r1 ON r1.child_id = s.id
                JOIN relationships r2 ON r2.child_id = s.id
                WHERE s.name = ?
                AND s.type = 'relationship'
                AND r1.parent_id = ?
                AND r2.parent_id = ?
                LIMIT 1
            ");
            $existingRelationshipStmt->execute([$relationshipName, $parentSpanId, $relatedPersonId]);
            $existingRelationship = $existingRelationshipStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRelationship) {
                // Skip creating a new relationship span since one already exists
                continue;
            }
            
            // Create a relationship span
            $relationshipSpanId = Uuid::uuid4()->toString();
            $metadata = [
                'relationship_type' => $rel['relationshipType']
            ];
            
            $startDate = parseDate($rel['start'] ?? null);
            $endDate = parseDate($rel['end'] ?? null);
            
            $db->prepare("
                INSERT INTO spans (
                    id, name, type,
                    start_year, start_month, start_day, start_precision_level,
                    end_year, end_month, end_day, end_precision_level,
                    metadata, created_by
                ) VALUES (
                    ?, ?, 'relationship',
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?::jsonb, ?
                )
            ")->execute([
                $relationshipSpanId,
                $relationshipName,
                $startDate ? $startDate['year'] : null,
                $startDate ? $startDate['month'] : null,
                $startDate ? $startDate['day'] : null,
                $startDate ? $startDate['precision'] : null,
                $endDate ? $endDate['year'] : null,
                $endDate ? $endDate['month'] : null,
                $endDate ? $endDate['day'] : null,
                $endDate ? $endDate['precision'] : null,
                json_encode($metadata),
                $adminUserId
            ]);
            
            // Create symmetric relationships linking both people to the relationship span
            $db->prepare("
                INSERT INTO relationships (parent_id, child_id, type)
                VALUES (?, ?, 'at_relationship'), (?, ?, 'at_relationship')
            ")->execute([
                $parentSpanId, $relationshipSpanId,
                $relatedPersonId, $relationshipSpanId
            ]);
        }
    }
    
    $db->commit();
    echo "Import completed successfully\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 