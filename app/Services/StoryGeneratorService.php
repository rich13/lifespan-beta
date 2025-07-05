<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class StoryGeneratorService
{
    /**
     * Generate a story for a span
     */
    public function generateStory(Span $span): array
    {
        return match ($span->type_id) {
            'person' => $this->generatePersonStory($span),
            'band' => $this->generateBandStory($span),
            default => $this->generateGenericStory($span),
        };
    }

    /**
     * Generate a story for a person
     */
    protected function generatePersonStory(Span $person): array
    {
        $story = [];
        $gender = $person->getMeta('gender');
        $pronouns = $this->getPronouns($gender);
        $isAlive = $person->is_ongoing;
        $tense = $isAlive ? 'present' : 'past';
        $name = $person->name;

        // Birth information
        if ($person->start_year) {
            $birthLocation = $this->getBirthLocation($person);
            $birthSentence = $this->generateBirthSentence($person, $pronouns, $birthLocation, $tense);
            $story[] = $birthSentence;
        }

        // Residence information
        $residences = $this->getResidences($person);
        if ($residences->isNotEmpty()) {
            $residenceSentence = $this->generateResidenceSentence($person, $pronouns, $residences, $tense);
            $story[] = $residenceSentence;
        }

        // Longest residence
        if ($residences->isNotEmpty()) {
            $longestResidence = $this->getLongestResidence($residences);
            if ($longestResidence) {
                $story[] = ucfirst($pronouns['subject']) . "'s longest period of living in one place was in {$longestResidence['place']} (" . $longestResidence['years'] . ").";
            }
        }

        // Education information
        $education = $this->getEducation($person);
        if ($education->isNotEmpty()) {
            $educationSentence = $this->generateEducationSentence($person, $pronouns, $education, $tense);
            $story[] = $educationSentence;
        }

        // Work information
        $work = $this->getWork($person);
        if ($work->isNotEmpty()) {
            $workSentence = $this->generateWorkSentence($person, $pronouns, $work, $tense);
            $story[] = $workSentence;
            // Most recent job
            $mostRecentJob = $this->getMostRecentJob($work);
            if ($mostRecentJob) {
                $story[] = ucfirst($pronouns['subject']) . "'s most recent job was at {$mostRecentJob['organisation']}.";
            }
        }

        // Relationship information
        $relationships = $this->getRelationships($person);
        if ($relationships->isNotEmpty()) {
            $relationshipSentence = $this->generateRelationshipSentence($person, $pronouns, $relationships, $tense);
            $story[] = $relationshipSentence;
            // Current relationship
            $currentRelationship = $this->getCurrentRelationship($relationships);
            if ($currentRelationship) {
                $story[] = ucfirst($pronouns['subject']) . "'s current relationship is with {$currentRelationship['person']}.";
            }
            // Longest relationship
            $longestRelationship = $this->getLongestRelationship($relationships);
            if ($longestRelationship) {
                $story[] = ucfirst($pronouns['subject']) . "'s longest relationship was with {$longestRelationship['person']} (" . $longestRelationship['years'] . ").";
            }
        }

        // Family information
        $familyInfo = $this->generateFamilySentences($person, $pronouns, $tense);
        $story = array_merge($story, $familyInfo);

        return [
            'title' => "The Story of {$person->name}",
            'paragraphs' => $this->groupIntoSentences($story),
            'metadata' => [
                'gender' => $gender,
                'pronouns' => $pronouns,
                'is_alive' => $isAlive,
                'tense' => $tense,
            ]
        ];
    }

    /**
     * Generate a story for a band
     */
    protected function generateBandStory(Span $band): array
    {
        $story = [];
        $isActive = $band->is_ongoing;
        $tense = $isActive ? 'present' : 'past';

        // Formation information
        if ($band->start_year) {
            $formationLocation = $this->getFormationLocation($band);
            $formationSentence = $this->generateFormationSentence($band, $formationLocation, $tense);
            $story[] = $formationSentence;
        }

        // Members information
        $members = $this->getBandMembers($band);
        if ($members->isNotEmpty()) {
            $membersSentence = $this->generateMembersSentence($band, $members, $tense);
            $story[] = $membersSentence;
        }

        // Discography information
        $discography = $this->getDiscography($band);
        if ($discography->isNotEmpty()) {
            $discographySentence = $this->generateDiscographySentence($band, $discography, $tense);
            $story[] = $discographySentence;
        }

        return [
            'title' => "The Story of {$band->name}",
            'paragraphs' => $this->groupIntoSentences($story),
            'metadata' => [
                'is_active' => $isActive,
                'tense' => $tense,
            ]
        ];
    }

    /**
     * Generate a generic story for other span types
     */
    protected function generateGenericStory(Span $span): array
    {
        $story = [];
        $isOngoing = $span->is_ongoing;
        $tense = $isOngoing ? 'present' : 'past';

        // Basic information
        if ($span->start_year) {
            $startSentence = $this->generateStartSentence($span, $tense);
            $story[] = $startSentence;
        }

        if ($span->description) {
            $story[] = $span->description;
        }

        return [
            'title' => "The Story of {$span->name}",
            'paragraphs' => $this->groupIntoSentences($story),
            'metadata' => [
                'is_ongoing' => $isOngoing,
                'tense' => $tense,
            ]
        ];
    }

    /**
     * Get pronouns based on gender
     */
    protected function getPronouns(?string $gender): array
    {
        return match ($gender) {
            'male' => ['subject' => 'he', 'object' => 'him', 'possessive' => 'his', 'reflexive' => 'himself'],
            'female' => ['subject' => 'she', 'object' => 'her', 'possessive' => 'her', 'reflexive' => 'herself'],
            default => ['subject' => 'they', 'object' => 'them', 'possessive' => 'their', 'reflexive' => 'themselves'],
        };
    }

    /**
     * Get birth location for a person
     */
    protected function getBirthLocation(Span $person): ?string
    {
        // Look for birth place in connections
        $birthConnection = $person->connectionsAsObject()
            ->where('type_id', 'residence')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'place');
            })
            ->whereHas('connectionSpan', function ($query) use ($person) {
                $query->where('start_year', $person->start_year)
                      ->where('start_month', $person->start_month)
                      ->where('start_day', $person->start_day);
            })
            ->first();

        return $birthConnection?->parent?->name;
    }

    /**
     * Get residences for a person
     */
    protected function getResidences(Span $person): Collection
    {
        return $person->connectionsAsObject()
            ->where('type_id', 'residence')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'place');
            })
            ->with(['parent', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'place' => $connection->parent->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    /**
     * Get education for a person
     */
    protected function getEducation(Span $person): Collection
    {
        return $person->connectionsAsObject()
            ->where('type_id', 'education')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'organisation');
            })
            ->with(['parent', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'organisation' => $connection->parent->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    /**
     * Get work for a person
     */
    protected function getWork(Span $person): Collection
    {
        return $person->connectionsAsObject()
            ->where('type_id', 'employment')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'organisation');
            })
            ->with(['parent', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'organisation' => $connection->parent->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    /**
     * Get relationships for a person
     */
    protected function getRelationships(Span $person): Collection
    {
        return $person->connectionsAsObject()
            ->where('type_id', 'relationship')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with(['parent', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'person' => $connection->parent->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    /**
     * Get formation location for a band
     */
    protected function getFormationLocation(Span $band): ?string
    {
        return $band->getMeta('formation_location');
    }

    /**
     * Get band members
     */
    protected function getBandMembers(Span $band): Collection
    {
        return $band->connectionsAsSubject()
            ->where('type_id', 'membership')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'person' => $connection->child->name,
                    'start_date' => $connection->connectionSpan?->formatted_start_date,
                    'end_date' => $connection->connectionSpan?->formatted_end_date,
                ];
            });
    }

    /**
     * Get discography for a band
     */
    protected function getDiscography(Span $band): Collection
    {
        return $band->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing');
            })
            ->with(['child', 'connectionSpan'])
            ->get()
            ->map(function ($connection) {
                return [
                    'thing' => $connection->child->name,
                    'date' => $connection->connectionSpan?->formatted_start_date,
                ];
            });
    }

    /**
     * Generate birth sentence
     */
    protected function generateBirthSentence(Span $person, array $pronouns, ?string $location, string $tense): string
    {
        $name = $person->name;
        $birthDate = $person->formatted_start_date;
        
        if ($location) {
            return "{$name} was born in {$birthDate} in {$location}.";
        } else {
            return "{$name} was born in {$birthDate}.";
        }
    }

    /**
     * Generate residence sentence
     */
    protected function generateResidenceSentence(Span $person, array $pronouns, Collection $residences, string $tense): string
    {
        $name = $person->name;
        $places = $residences->pluck('place')->unique()->values();
        
        if ($places->count() === 1) {
            return "During {$pronouns['possessive']} life, {$pronouns['subject']} has lived in {$places->first()}.";
        } else {
            $placeList = $this->formatList($places->toArray());
            return "During {$pronouns['possessive']} life, {$pronouns['subject']} has lived in {$placeList}.";
        }
    }

    /**
     * Generate education sentence
     */
    protected function generateEducationSentence(Span $person, array $pronouns, Collection $education, string $tense): string
    {
        $name = $person->name;
        $institutions = $education->pluck('organisation')->unique()->values();
        
        if ($institutions->count() === 1) {
            return "{$pronouns['subject']} went to school at {$institutions->first()}.";
        } else {
            $institutionList = $this->formatList($institutions->toArray());
            return "{$pronouns['subject']} went to school at {$institutionList}.";
        }
    }

    /**
     * Generate work sentence
     */
    protected function generateWorkSentence(Span $person, array $pronouns, Collection $work, string $tense): string
    {
        $name = $person->name;
        $organisations = $work->pluck('organisation')->unique()->values();
        
        if ($organisations->count() === 1) {
            return "{$pronouns['subject']} has worked for {$organisations->first()}.";
        } else {
            $organisationList = $this->formatList($organisations->toArray());
            return "{$pronouns['subject']} has worked for {$organisationList}.";
        }
    }

    /**
     * Generate relationship sentence
     */
    protected function generateRelationshipSentence(Span $person, array $pronouns, Collection $relationships, string $tense): string
    {
        $name = $person->name;
        $count = $relationships->count();
        
        if ($count === 1) {
            $partner = $relationships->first()['person'];
            return "{$pronouns['subject']} has had a relationship with {$partner}.";
        } else {
            return "{$pronouns['subject']} has had relationships with {$count} people.";
        }
    }

    /**
     * Generate family sentences
     */
    protected function generateFamilySentences(Span $person, array $pronouns, string $tense): array
    {
        $sentences = [];
        
        // Parents
        $parents = $person->parents;
        if ($parents->isNotEmpty()) {
            $parentNames = $parents->pluck('name')->toArray();
            $parentList = $this->formatList($parentNames);
            $sentences[] = "{$pronouns['subject']} is the child of {$parentList}.";
        }
        
        // Children
        $children = $person->children;
        if ($children->isNotEmpty()) {
            $childCount = $children->count();
            if ($childCount === 1) {
                $childName = $children->first()->name;
                $sentences[] = "{$pronouns['subject']} has one child, {$childName}.";
            } else {
                $sentences[] = "{$pronouns['subject']} has {$childCount} children.";
            }
        }
        
        // Siblings
        $siblings = $person->siblings();
        if ($siblings->isNotEmpty()) {
            $siblingCount = $siblings->count();
            if ($siblingCount === 1) {
                $siblingName = $siblings->first()->name;
                $sentences[] = "{$pronouns['subject']} has one sibling, {$siblingName}.";
            } else {
                $sentences[] = "{$pronouns['subject']} has {$siblingCount} siblings.";
            }
        }
        
        return $sentences;
    }

    /**
     * Generate formation sentence for bands
     */
    protected function generateFormationSentence(Span $band, ?string $location, string $tense): string
    {
        $name = $band->name;
        $formationDate = $band->formatted_start_date;
        
        if ($location) {
            return "{$name} was formed in {$formationDate} in {$location}.";
        } else {
            return "{$name} was formed in {$formationDate}.";
        }
    }

    /**
     * Generate members sentence for bands
     */
    protected function generateMembersSentence(Span $band, Collection $members, string $tense): string
    {
        $name = $band->name;
        $memberCount = $members->count();
        
        return "They have had {$memberCount} members throughout their history.";
    }

    /**
     * Generate discography sentence for bands
     */
    protected function generateDiscographySentence(Span $band, Collection $discography, string $tense): string
    {
        $name = $band->name;
        $albumCount = $discography->count();
        
        if ($albumCount === 0) {
            return "They have not released any albums yet.";
        } elseif ($albumCount === 1) {
            $album = $discography->first()['thing'];
            return "They have released one album: {$album}.";
        } else {
            $latestAlbum = $discography->sortByDesc('date')->first()['thing'];
            return "They have released {$albumCount} albums, most recently {$latestAlbum}.";
        }
    }

    /**
     * Generate start sentence for generic spans
     */
    protected function generateStartSentence(Span $span, string $tense): string
    {
        $name = $span->name;
        $startDate = $span->formatted_start_date;
        $action = $this->getActionWordForSpanType($span->type_id);
        
        return "{$name} {$action} in {$startDate}.";
    }

    /**
     * Get action word for span type
     */
    protected function getActionWordForSpanType(string $type): string
    {
        return match ($type) {
            'person' => 'was born',
            'organisation' => 'was founded',
            'event' => 'began',
            'band' => 'was formed',
            default => 'started',
        };
    }

    /**
     * Format a list of items with proper grammar
     */
    protected function formatList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        } elseif (count($items) === 2) {
            return "{$items[0]} and {$items[1]}";
        } else {
            $last = array_pop($items);
            return implode(', ', $items) . ", and {$last}";
        }
    }

    /**
     * Group sentences into paragraphs
     */
    protected function groupIntoSentences(array $sentences): array
    {
        if (empty($sentences)) {
            return [];
        }

        // For now, put all sentences in one paragraph
        // In the future, we could implement more sophisticated paragraph grouping
        return [implode(' ', $sentences)];
    }

    // Add helpers for longest residence, most recent job, current and longest relationship
    protected function getLongestResidence(Collection $residences): ?array
    {
        $longest = null;
        $maxYears = 0;
        foreach ($residences as $res) {
            if ($res['start_date'] && $res['end_date']) {
                $start = Carbon::parse($res['start_date']);
                $end = Carbon::parse($res['end_date']);
                $years = $start->diffInYears($end);
                if ($years > $maxYears) {
                    $maxYears = $years;
                    $longest = $res;
                }
            }
        }
        if ($longest) {
            $longest['years'] = $maxYears . ' years';
            return $longest;
        }
        return null;
    }

    protected function getMostRecentJob(Collection $work): ?array
    {
        $mostRecent = null;
        $latest = null;
        foreach ($work as $job) {
            if ($job['end_date']) {
                $end = Carbon::parse($job['end_date']);
                if (!$latest || $end->gt($latest)) {
                    $latest = $end;
                    $mostRecent = $job;
                }
            }
        }
        return $mostRecent;
    }

    protected function getCurrentRelationship(Collection $relationships): ?array
    {
        foreach ($relationships as $rel) {
            if (!$rel['end_date']) {
                return $rel;
            }
        }
        return null;
    }

    protected function getLongestRelationship(Collection $relationships): ?array
    {
        $longest = null;
        $maxYears = 0;
        foreach ($relationships as $rel) {
            if ($rel['start_date'] && $rel['end_date']) {
                $start = Carbon::parse($rel['start_date']);
                $end = Carbon::parse($rel['end_date']);
                $years = $start->diffInYears($end);
                if ($years > $maxYears) {
                    $maxYears = $years;
                    $longest = $rel;
                }
            }
        }
        if ($longest) {
            $longest['years'] = $maxYears . ' years';
            return $longest;
        }
        return null;
    }
} 