<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Str;

class WikipediaSpanMatcherService
{
    /**
     * Parse Wikipedia text and find matching spans
     */
    public function findMatchingSpans(string $text): array
    {
        $matches = [];
        
        // Extract potential entity names from the text
        $entities = $this->extractEntities($text);
        
        foreach ($entities as $entity) {
            $spans = $this->findSpansByName($entity);
            if (!empty($spans)) {
                $matches[] = [
                    'entity' => $entity,
                    'spans' => $spans,
                    'text_position' => $this->findTextPosition($text, $entity)
                ];
            }
        }
        
        return $matches;
    }
    
    /**
     * Extract potential entity names from text
     */
    private function extractEntities(string $text): array
    {
        $entities = [];
        
        // Look for multi-word capitalized phrases (e.g., "Donald Trump", "World Trade Center")
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/', $text, $multiWordMatches);
        
        // Look for single capitalized words that are at least 4 characters (e.g., "London", "Trump")
        preg_match_all('/\b[A-Z][a-z]{3,}\b/', $text, $singleWordMatches);
        
        $allMatches = array_merge($multiWordMatches[0], $singleWordMatches[0]);
        
        foreach ($allMatches as $match) {
            // Filter out common words that aren't likely to be entities
            if ($this->isLikelyEntity($match)) {
                $entities[] = $match;
            }
        }
        
        // Remove duplicates and sort by length (longer names first)
        $entities = array_unique($entities);
        usort($entities, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        return array_slice($entities, 0, 10); // Limit to top 10
    }
    
    /**
     * Check if a word/phrase is likely to be an entity
     */
    private function isLikelyEntity(string $text): bool
    {
        $commonWords = [
            'The', 'This', 'That', 'These', 'Those', 'When', 'Where', 'What', 'Who', 'Why', 'How',
            'And', 'Or', 'But', 'For', 'With', 'From', 'Into', 'During', 'Including', 'Until', 'Against',
            'Among', 'Throughout', 'Describing', 'It', 'As', 'At', 'Be', 'This', 'Have', 'From', 'Or',
            'An', 'By', 'We', 'Will', 'Can', 'The', 'Or', 'Had', 'By', 'Her', 'Were', 'More', 'An', 'Will',
            'My', 'One', 'All', 'Would', 'There', 'Their', 'What', 'So', 'Up', 'Out', 'If', 'About', 'Who',
            'Get', 'Which', 'Go', 'Me', 'When', 'Make', 'Can', 'Like', 'Time', 'No', 'Just', 'Him', 'Know',
            'Take', 'People', 'Into', 'Year', 'Your', 'Good', 'Some', 'Could', 'Them', 'See', 'Other', 'Than',
            'Then', 'Now', 'Look', 'Only', 'Come', 'Its', 'Over', 'Think', 'Also', 'Back', 'After', 'Use',
            'Two', 'How', 'Our', 'Work', 'First', 'Well', 'Way', 'Even', 'New', 'Want', 'Because', 'Any',
            'These', 'Give', 'Day', 'Most', 'Us', 'Now', 'Then', 'Here', 'There', 'Where', 'Why', 'How',
            'What', 'When', 'Who', 'Which', 'Whose', 'Whom', 'That', 'This', 'These', 'Those', 'I', 'You',
            'He', 'She', 'It', 'We', 'They', 'Me', 'Him', 'Her', 'Us', 'Them', 'My', 'Your', 'His', 'Her',
            'Its', 'Our', 'Their', 'Mine', 'Yours', 'His', 'Hers', 'Ours', 'Theirs', 'Myself', 'Yourself',
            'Himself', 'Herself', 'Itself', 'Ourselves', 'Yourselves', 'Themselves', 'Am', 'Is', 'Are',
            'Was', 'Were', 'Be', 'Been', 'Being', 'Have', 'Has', 'Had', 'Do', 'Does', 'Did', 'Will',
            'Would', 'Could', 'Should', 'May', 'Might', 'Must', 'Can', 'Shall', 'Ought', 'Need', 'Dare'
        ];
        
        // Skip if it's a common word
        if (in_array($text, $commonWords)) {
            return false;
        }
        
        // Skip if it's too short (less than 2 characters)
        if (strlen($text) < 2) {
            return false;
        }
        
        // Skip if it's just numbers
        if (is_numeric($text)) {
            return false;
        }
        
        // Skip if it contains common non-entity patterns
        if (preg_match('/^(The|A|An)\s+/i', $text)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Find spans by name (searching in name and metadata)
     */
    private function findSpansByName(string $name): array
    {
        // Only match exact names or alternate names
        return Span::where(function($query) use ($name) {
                // Exact match on name
                $query->where('name', 'ILIKE', $name)
                      // Or exact match on alternate names
                      ->orWhere('metadata->alternate_names', 'ILIKE', $name);
            })
            ->limit(5)
            ->get()
            ->toArray();
    }
    
    /**
     * Find the position of an entity in the text
     */
    private function findTextPosition(string $text, string $entity): array
    {
        $position = stripos($text, $entity);
        if ($position === false) {
            return [];
        }
        
        return [
            'start' => $position,
            'end' => $position + strlen($entity),
            'length' => strlen($entity)
        ];
    }
    
    /**
     * Highlight matching entities in text with links to spans
     */
    public function highlightMatches(string $text, array $matches): string
    {
        // Sort matches by position (earliest first) and length (longest first to avoid partial matches)
        usort($matches, function($a, $b) {
            if ($a['text_position']['start'] !== $b['text_position']['start']) {
                return $a['text_position']['start'] - $b['text_position']['start'];
            }
            return $b['text_position']['length'] - $a['text_position']['length'];
        });
        
        $highlightedText = $text;
        $offset = 0;
        $processedPositions = [];
        
        foreach ($matches as $match) {
            $entity = $match['entity'];
            $position = $match['text_position'];
            $spans = $match['spans'];
            
            // Skip if this position has already been processed (to avoid overlapping matches)
            $positionKey = $position['start'] . '-' . $position['end'];
            if (in_array($positionKey, $processedPositions)) {
                continue;
            }
            
            if (!empty($spans)) {
                $span = $spans[0]; // Use the first matching span
                $link = route('spans.show', $span['id']);
                
                $replacement = "<a href=\"{$link}\" class=\"text-decoration-none\" title=\"{$span['name']}\">{$entity}</a>";
                
                $highlightedText = substr_replace(
                    $highlightedText,
                    $replacement,
                    $position['start'] + $offset,
                    $position['length']
                );
                
                $offset += strlen($replacement) - $position['length'];
                $processedPositions[] = $positionKey;
            }
        }
        
        return $highlightedText;
    }
} 