<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Carbon\Carbon;

class LeadershipRoleService
{
    /**
     * Get the person who held a specific leadership role at a given date
     *
     * @param string $roleName The name of the role (e.g., "Prime Minister of the United Kingdom")
     * @param int $year
     * @param int $month
     * @param int $day
     * @return Span|null The person who held the role, or null if not found
     */
    public function getRoleHolderAtDate(string $roleName, int $year, int $month, int $day): ?Span
    {
        $targetDate = Carbon::create($year, $month, $day, 12, 0, 0);

        // First, try to find a role span with this name
        $roleSpan = Span::where('type_id', 'role')
            ->where('name', $roleName)
            ->first();

        if ($roleSpan) {
            // Find has_role connections where this role is the child
            $connections = Connection::where('type_id', 'has_role')
                ->where('child_id', $roleSpan->id)
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'person');
                })
                ->with(['parent', 'connectionSpan'])
                ->get();

            // Filter connections to ensure they're active at the exact date
            foreach ($connections as $connection) {
                if ($this->isConnectionActiveAtDate($connection, $targetDate)) {
                    $person = $connection->parent;
                    // Only return if the person span is accessible
                    if ($person && $person->isAccessibleBy(auth()->user())) {
                        return $person;
                    }
                }
            }
        }

        // Fallback: search employment connections with metadata containing the role/position
        // First, filter by date range in the database query to reduce the dataset
        $employmentConnections = Connection::where('type_id', 'employment')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'person');
            })
            ->whereHas('connectionSpan', function ($query) use ($year) {
                // Basic date filtering at the database level
                // Connection span must have start_year <= target year or be null
                // and end_year >= target year or be null
                $query->where(function ($q) use ($year) {
                    $q->whereNull('start_year')
                      ->orWhere('start_year', '<=', $year);
                })
                ->where(function ($q) use ($year) {
                    $q->whereNull('end_year')
                      ->orWhere('end_year', '>=', $year);
                });
            })
            ->with(['parent', 'connectionSpan'])
            ->get();

        // Filter to find the one active at the exact date with matching role
        foreach ($employmentConnections as $connection) {
            if (!$this->isConnectionActiveAtDate($connection, $targetDate)) {
                continue;
            }

            $metadata = $connection->connectionSpan->metadata ?? [];
            $connectionRole = $metadata['role'] ?? $metadata['position'] ?? null;
            
            // Verify it matches the role name we're looking for
            if ($connectionRole === $roleName) {
                $person = $connection->parent;
                // Only return if the person span is accessible
                if ($person && $person->isAccessibleBy(auth()->user())) {
                    return $person;
                }
            }
        }

        return null;
    }

    /**
     * Get both Prime Minister and President at a given date
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return array{prime_minister: Span|null, president: Span|null}
     */
    public function getLeadershipAtDate(int $year, int $month, int $day): array
    {
        return [
            'prime_minister' => $this->getRoleHolderAtDate('Prime Minister of the United Kingdom', $year, $month, $day),
            'president' => $this->getRoleHolderAtDate('President of the United States', $year, $month, $day),
        ];
    }

    /**
     * Check if a connection is active at a specific date
     * Uses the same logic as SpanController::isConnectionOngoingAtDate
     */
    private function isConnectionActiveAtDate(Connection $connection, Carbon $targetDate): bool
    {
        $connectionSpan = $connection->connectionSpan;
        
        if (!$connectionSpan) {
            return false;
        }

        // Check if the connection has start/end dates
        $hasStartDate = $connectionSpan->start_year || $connectionSpan->start_month || $connectionSpan->start_day;
        $hasEndDate = $connectionSpan->end_year || $connectionSpan->end_month || $connectionSpan->end_day;

        if (!$hasStartDate && !$hasEndDate) {
            return false;
        }

        // Get the expanded date ranges based on precision
        $startRange = $connectionSpan->getStartDateRange();
        $endRange = $connectionSpan->getEndDateRange();

        // Check if the target date falls within the connection's date range
        if ($startRange[0] && $targetDate < $startRange[0]) {
            return false;
        }

        if ($endRange[1] && $targetDate > $endRange[1]) {
            return false;
        }

        return true;
    }
}

