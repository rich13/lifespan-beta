<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Str;

class WikipediaSpanMatcherService
{
    /**
     * Parse Wikipedia text and find matching spans and dates
     */
    public function findMatchingSpans(string $text): array
    {
        $matches = [];
        
        // Extract potential entity names from the text
        $entities = $this->extractEntities($text);
        
        foreach ($entities as $entity) {
            $spans = $this->findSpansByName($entity);
            if (!empty($spans)) {
                $positions = $this->findTextPosition($text, $entity);
                foreach ($positions as $position) {
                    $matches[] = [
                        'entity' => $entity,
                        'spans' => $spans,
                        'text_position' => $position,
                        'type' => 'span'
                    ];
                }
            }
        }
        
        return $matches;
    }

    /**
     * Find years in text and create date links
     */
    public function findYears(string $text): array
    {
        $matches = [];
        
        // Look for 4-digit years (1000-2999)
        preg_match_all('/\b(1[0-9]{3}|2[0-9]{3})\b/', $text, $yearMatches);
        
        foreach ($yearMatches[0] as $year) {
            $positions = $this->findTextPosition($text, $year);
            foreach ($positions as $position) {
                $matches[] = [
                    'entity' => $year,
                    'text_position' => $position,
                    'type' => 'year',
                    'year' => (int) $year
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
        
        // Look for multi-word Title Case phrases allowing lowercase connector words
        // Example: "Monkey Gone to Heaven", "The Lord of the Rings"
        $connectorWords = '(?:of|to|and|or|the|a|an|in|on|for|with|from|by|at|as|but|nor|so|yet)';
        preg_match_all('/\b[A-Z][A-Za-z’\']+(?:\s+(?:[A-Z][A-Za-z’\']+|' . $connectorWords . '))+\b/u', $text, $multiWordMatches);
        
        // Look for single capitalized words that are at least 4 characters (e.g., "London", "Trump")
        preg_match_all('/\b[A-Z][a-z]{3,}\b/', $text, $singleWordMatches);
        
        // Look for acronyms (e.g., "BBC", "NASA", "FBI")
        preg_match_all('/\b[A-Z]{2,}\b/', $text, $acronymMatches);
        
        // Look for quoted phrases (e.g., "Nevermind", "Foo Fighters")
        preg_match_all('/"([^"]+)"/', $text, $quotedMatches);
        
        $allMatches = array_merge($multiWordMatches[0], $singleWordMatches[0], $acronymMatches[0], $quotedMatches[1] ?? []);
        
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
        
        return array_slice($entities, 0, 50); // Limit to top 50
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
        
        // Skip single-word leading articles, but allow multi-word phrases like "The Beatles"
        if (preg_match('/^(The|A|An)\s+/i', $text)) {
            // If it's a single word (no additional words), skip
            if (!str_contains($text, ' ')) {
                return false;
            }
            // Otherwise allow multi-word entities starting with an article
        }
        
        return true;
    }

    /**
     * Find spans by name (searching in name and metadata)
     */
    private function findSpansByName(string $name): array
    {
        // Normalise entity: trim, collapse whitespace, normalise quotes
        $normalise = function(string $s): string {
            $s = trim($s);
            // Replace fancy quotes/apostrophes with ASCII
            $s = str_replace(["“","”","‘","’"], ['"','"','\'','\''], $s);
            // Collapse multiple whitespace to single spaces
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        $name = $normalise($name);

        // Use exact matching to avoid finding partial matches
        $query = Span::where('name', 'ILIKE', $name)
            ->where('access_level', 'public');

        $results = $query->limit(5)->get();
        if ($results->isNotEmpty()) {
            return $results->toArray();
        }

        // If no results and the name starts with an article, also try without the leading article
        if (preg_match('/^(The|A|An)\s+(.*)$/i', $name, $m)) {
            $stripped = $m[2];
            $altResults = Span::where('name', 'ILIKE', $stripped)
                ->where('access_level', 'public')
                ->limit(5)
                ->get();
            if ($altResults->isNotEmpty()) {
                return $altResults->toArray();
            }
        }

        // Try without surrounding quotes
        if (preg_match('/^"(.+)"$/', $name, $m)) {
            $unquoted = $m[1];
            $altResults = Span::where('name', 'ILIKE', $unquoted)
                ->where('access_level', 'public')
                ->limit(5)
                ->get();
            if ($altResults->isNotEmpty()) {
                return $altResults->toArray();
            }
        }

        return [];
    }

    /**
     * Find all positions of an entity in the text
     */
    private function findTextPosition(string $text, string $entity): array
    {
        $positions = [];
        $offset = 0;
        
        while (($position = stripos($text, $entity, $offset)) !== false) {
            $positions[] = [
                'start' => $position,
                'end' => $position + strlen($entity),
                'length' => strlen($entity)
            ];
            $offset = $position + 1; // Move past this occurrence to find the next one
        }
        
        return $positions;
    }

    /**
     * Highlight matching entities and years in text with links
     */
    public function highlightMatches(string $text): string
    {
        // Find both spans and years
        $spanMatches = $this->findMatchingSpans($text);
        $yearMatches = $this->findYears($text);
        
        // Combine all matches
        $allMatches = array_merge($spanMatches, $yearMatches);
        
        // Sort matches by position (earliest first) and length (longest first to avoid partial matches)
        usort($allMatches, function($a, $b) {
            if ($a['text_position']['start'] !== $b['text_position']['start']) {
                return $a['text_position']['start'] - $b['text_position']['start'];
            }
            return $b['text_position']['length'] - $a['text_position']['length'];
        });
        
        $highlightedText = $text;
        $offset = 0;
        // Track processed ranges to avoid overlapping replacements
        $processedRanges = [];
        
        foreach ($allMatches as $match) {
            $entity = $match['entity'];
            $position = $match['text_position'];
            $type = $match['type'];
            
            // Skip if this position overlaps any processed range (avoid overlapping matches)
            $overlaps = false;
            foreach ($processedRanges as $range) {
                // Overlap if start < range_end and end > range_start
                if ($position['start'] < $range['end'] && $position['end'] > $range['start']) {
                    $overlaps = true;
                    break;
                }
            }
            if ($overlaps) {
                continue;
            }
            
            if ($type === 'span' && !empty($match['spans'])) {
                $span = $match['spans'][0]; // Use the first matching span
                $link = route('spans.show', $span['id']);
                
                $classes = 'text-decoration-none';
                // Add placeholder class if the span is in placeholder state
                if (isset($span['state']) && $span['state'] === 'placeholder') {
                    $classes .= ' text-placeholder';
                }
                
                $replacement = "<a href=\"{$link}\" class=\"{$classes}\" title=\"{$span['name']}\">{$entity}</a>";
            } elseif ($type === 'year') {
                $year = $match['year'];
                $link = route('date.explore', ['date' => $year]);
                
                $replacement = "<a href=\"{$link}\" class=\"text-decoration-none\">{$entity}</a>";
            } else {
                continue; // Skip if no valid match
            }
            
            $highlightedText = substr_replace(
                $highlightedText,
                $replacement,
                $position['start'] + $offset,
                $position['length']
            );
            
            $offset += strlen($replacement) - $position['length'];
            // Record this processed range using original text coordinates
            $processedRanges[] = [
                'start' => $position['start'],
                'end' => $position['end']
            ];
        }
        
        return $highlightedText;
    }
} 