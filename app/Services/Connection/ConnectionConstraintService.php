<?php

namespace App\Services\Connection;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Services\Temporal\TemporalRange;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;

class ConnectionConstraintService
{
    public function __construct(
        private readonly TemporalService $temporalService,
        private readonly PrecisionValidator $precisionValidator
    ) {}

    /**
     * Validate a connection against its temporal constraint
     */
    public function validateConstraint(
        Connection $connection,
        string $constraintType
    ): ConnectionConstraintResult {
        if (!in_array($constraintType, ['single', 'non_overlapping', 'timeless'])) {
            throw new \InvalidArgumentException("Unknown constraint type: {$constraintType}");
        }

        return match($constraintType) {
            'single' => $this->validateSingleConstraint($connection),
            'non_overlapping' => $this->validateNonOverlappingConstraint($connection),
            'timeless' => $this->validateTimelessConstraint($connection),
            default => throw new \InvalidArgumentException("Unknown constraint type: {$constraintType}")
        };
    }

    /**
     * Validate that only one connection of this type exists between these spans
     */
    private function validateSingleConstraint(
        Connection $connection
    ): ConnectionConstraintResult {
        $exists = Connection::where([
            'parent_id' => $connection->parent_id,
            'child_id' => $connection->child_id,
            'type_id' => $connection->type_id,
        ])
        ->where('id', '!=', $connection->id)
        ->exists();

        return $exists 
            ? ConnectionConstraintResult::failure('Only one connection of this type is allowed between these spans')
            : ConnectionConstraintResult::success();
    }

    /**
     * Validate that the connection doesn't overlap with existing connections
     */
    private function validateNonOverlappingConstraint(
        Connection $connection
    ): ConnectionConstraintResult {
        $existingConnections = Connection::where([
            'parent_id' => $connection->parent_id,
            'child_id' => $connection->child_id,
            'type_id' => $connection->type_id,
        ])
        ->where('id', '!=', $connection->id)
        ->get();

        if ($existingConnections->isEmpty()) {
            return ConnectionConstraintResult::success();
        }

        // Ensure connectionSpan is loaded
        if (!$connection->relationLoaded('connectionSpan')) {
            $connection->load('connectionSpan');
        }
        
        // Skip temporal validation for placeholder connections or connections with no dates
        $connectionSpan = $connection->connectionSpan;
        if (!$connectionSpan) {
            // If connection span doesn't exist, allow it (shouldn't happen, but be defensive)
            return ConnectionConstraintResult::success();
        }
        
        if ($connectionSpan->state === 'placeholder' || 
            ($connectionSpan->start_year === null && $connectionSpan->end_year === null)) {
            return ConnectionConstraintResult::success();
        }

        $newRange = TemporalRange::fromSpan($connectionSpan);

        foreach ($existingConnections as $existing) {
            // Ensure existing connectionSpan is loaded
            if (!$existing->relationLoaded('connectionSpan')) {
                $existing->load('connectionSpan');
            }
            
            $existingSpan = $existing->connectionSpan;
            if (!$existingSpan) {
                continue;
            }
            
            // Skip temporal validation for existing placeholder connections
            if ($existingSpan->state === 'placeholder' || 
                ($existingSpan->start_year === null && $existingSpan->end_year === null)) {
                continue;
            }

            $existingRange = TemporalRange::fromSpan($existingSpan);
            
            if ($this->temporalService->overlaps($newRange, $existingRange)) {
                // Check if they're just adjacent
                if ($this->temporalService->areAdjacent($connectionSpan, $existingSpan)) {
                    continue;
                }
                return ConnectionConstraintResult::failure('Connection dates overlap with an existing connection');
            }
        }

        return ConnectionConstraintResult::success();
    }

    /**
     * Validate that only one timeless connection of this type exists between these spans
     * Timeless connections don't have temporal constraints, so we only check for uniqueness
     */
    private function validateTimelessConstraint(
        Connection $connection
    ): ConnectionConstraintResult {
        $exists = Connection::where([
            'parent_id' => $connection->parent_id,
            'child_id' => $connection->child_id,
            'type_id' => $connection->type_id,
        ])
        ->where('id', '!=', $connection->id)
        ->exists();

        return $exists 
            ? ConnectionConstraintResult::failure('Only one connection of this type is allowed between these spans')
            : ConnectionConstraintResult::success();
    }
}