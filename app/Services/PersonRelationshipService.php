<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Support\Collection;

class PersonRelationshipService
{
    /**
     * Get all people categorized by their roles
     */
    public function getCategorizedPeople(Span $userSpan): array
    {
        $categories = [
            'musicians' => collect()
        ];

        // Get all people with musician roles (not just connected to user)
        $musicians = $this->getPeopleWithMusicianRole();
        $categories['musicians'] = $musicians;

        return $categories;
    }

    /**
     * Get all people with musician roles
     */
    public function getPeopleWithMusicianRole(): Collection
    {
        return Span::where('type_id', 'person')
            ->whereHas('connectionsAsSubject', function ($query) {
                $query->whereHas('type', function ($subQuery) {
                    $subQuery->where('type', 'has_role');
                })
                ->whereHas('child', function ($subQuery) {
                    $subQuery->where('name', 'Musician');
                });
            })
            ->get();
    }

    /**
     * Get people connected to a specific span
     */
    public function getConnectedPeople(Span $span): Collection
    {
        return Span::where('type_id', 'person')
            ->where(function ($query) use ($span) {
                $query->whereHas('connectionsAsSubject', function ($subQuery) use ($span) {
                    $subQuery->where('child_id', $span->id);
                })
                ->orWhereHas('connectionsAsObject', function ($subQuery) use ($span) {
                    $subQuery->where('parent_id', $span->id);
                });
            })
            ->with(['connectionsAsSubject', 'connectionsAsObject'])
            ->get();
    }

    /**
     * Check if person has musician role
     */
    public function hasMusicianRole(Span $person): bool
    {
        // Check if person has a 'has_role' connection FROM them TO a 'musician' role span
        return $person->connectionsAsSubject()
            ->whereHas('type', function ($query) {
                $query->where('type', 'has_role');
            })
            ->whereHas('child', function ($query) {
                $query->where('name', 'Musician');
            })
            ->exists();
    }

    /**
     * Get people by category for filtering
     */
    public function getPeopleByCategory(string $category, Span $userSpan): Collection
    {
        if ($category === 'musicians') {
            return $this->getPeopleWithMusicianRole();
        }
        
        return collect();
    }

    /**
     * Get all people with their categories for display
     */
    public function getPeopleWithCategories(Span $userSpan): Collection
    {
        $connectedPeople = $this->getConnectedPeople($userSpan);
        
        return $connectedPeople->map(function ($person) use ($userSpan) {
            $person->category = $this->categorizePerson($person, $userSpan);
            return $person;
        });
    }
} 