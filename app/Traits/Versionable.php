<?php

namespace App\Traits;

use App\Models\SpanVersion;
use App\Models\ConnectionVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Trait that provides automatic versioning functionality for models.
 * 
 * This trait automatically creates a version snapshot whenever a model is updated.
 * It stores the complete state of the model at the time of the update.
 */
trait Versionable
{
    /**
     * Boot the trait and set up the versioning events.
     */
    protected static function bootVersionable()
    {
        static::created(function ($model) {
            // Skip versioning in testing environment unless explicitly enabled
            if (app()->environment('testing') && !config('app.enable_versioning_in_tests', true)) {
                return;
            }
            
            try {
                $model->createVersion('Initial version');
            } catch (\Exception $e) {
                // Log the error but don't break the create operation
                \Log::warning('Failed to create initial version: ' . $e->getMessage());
            }
        });

        static::updated(function ($model) {
            // Skip versioning in testing environment unless explicitly enabled
            if (app()->environment('testing') && !config('app.enable_versioning_in_tests', true)) {
                return;
            }
            
            try {
                $model->createVersion();
            } catch (\Exception $e) {
                // Log the error but don't break the update operation
                \Log::warning('Failed to create version: ' . $e->getMessage());
            }
        });
    }

    /**
     * Create a new version of this model.
     * 
     * @param string|null $changeSummary Optional summary of what changed
     * @return SpanVersion|ConnectionVersion The created version
     */
    public function createVersion(?string $changeSummary = null)
    {
        $userId = Auth::id();
        
        if (!$userId) {
            // If no authenticated user, try to get from the model
            $userId = $this->updater_id ?? $this->owner_id ?? null;
        }
        
        // For connections, try to get user ID from connection span or connected spans
        if (!$userId && $this instanceof \App\Models\Connection) {
            if ($this->connectionSpan && $this->connectionSpan->owner_id) {
                $userId = $this->connectionSpan->owner_id;
            } elseif ($this->subject && $this->subject->owner_id) {
                $userId = $this->subject->owner_id;
            } elseif ($this->object && $this->object->owner_id) {
                $userId = $this->object->owner_id;
            }
        }
        
        // In testing environment, use a fallback user ID if available
        if (!$userId && app()->environment('testing')) {
            $userId = config('app.test_user_id', null);
        }
        
        if (!$userId) {
            throw new \Exception('Cannot create version: no user ID available');
        }

        // Get the next version number
        $nextVersion = $this->getNextVersionNumber();

        // Prepare version data
        $versionData = $this->prepareVersionData();
        $versionData['version_number'] = $nextVersion;
        $versionData['changed_by'] = $userId;
        
        // Generate automatic change summary if not provided
        if (!$changeSummary) {
            $changeSummary = $this->generateChangeSummary();
        }
        
        if ($changeSummary) {
            $versionData['change_summary'] = $changeSummary;
        }

        // Create the appropriate version model
        if ($this instanceof \App\Models\Span) {
            $versionData['span_id'] = $this->id;
            return SpanVersion::create($versionData);
        } elseif ($this instanceof \App\Models\Connection) {
            $versionData['connection_id'] = $this->id;
            return ConnectionVersion::create($versionData);
        }

        throw new \Exception('Versioning not supported for this model type');
    }

    /**
     * Get the next version number for this model.
     * 
     * @return int
     */
    protected function getNextVersionNumber(): int
    {
        if ($this instanceof \App\Models\Span) {
            return SpanVersion::where('span_id', $this->id)->max('version_number') + 1;
        } elseif ($this instanceof \App\Models\Connection) {
            return ConnectionVersion::where('connection_id', $this->id)->max('version_number') + 1;
        }

        return 1; // Default to version 1 if no versions exist
    }

    /**
     * Prepare the data for versioning by extracting all relevant fields.
     * 
     * @return array
     */
    protected function prepareVersionData(): array
    {
        $data = [];
        
        if ($this instanceof \App\Models\Span) {
            $fields = [
                'name', 'slug', 'type_id', 'is_personal_span', 'parent_id', 'root_id',
                'start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day',
                'start_precision', 'end_precision', 'state', 'description', 'notes',
                'metadata', 'sources', 'permissions', 'permission_mode', 'access_level',
                'filter_type', 'filter_criteria', 'is_predefined'
            ];
        } elseif ($this instanceof \App\Models\Connection) {
            $fields = [
                'parent_id', 'child_id', 'type_id', 'connection_span_id', 'metadata'
            ];
        } else {
            throw new \Exception('Versioning not supported for this model type');
        }

        foreach ($fields as $field) {
            if (isset($this->$field)) {
                $data[$field] = $this->$field;
            }
        }

        return $data;
    }

    /**
     * Get all versions of this model.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function versions()
    {
        if ($this instanceof \App\Models\Span) {
            return $this->hasMany(SpanVersion::class)->orderBy('version_number', 'desc');
        } elseif ($this instanceof \App\Models\Connection) {
            return $this->hasMany(ConnectionVersion::class)->orderBy('version_number', 'desc');
        }

        throw new \Exception('Versioning not supported for this model type');
    }

    /**
     * Get the latest version of this model.
     * 
     * @return SpanVersion|ConnectionVersion|null
     */
    public function getLatestVersion()
    {
        return $this->versions()->first();
    }

    /**
     * Get a specific version of this model.
     * 
     * @param int $versionNumber
     * @return SpanVersion|ConnectionVersion|null
     */
    public function getVersion(int $versionNumber)
    {
        return $this->versions()->where('version_number', $versionNumber)->first();
    }

    /**
     * Revert this model to a specific version.
     * 
     * @param int $versionNumber
     * @param string|null $changeSummary
     * @return bool
     */
    public function revertToVersion(int $versionNumber, ?string $changeSummary = null): bool
    {
        $version = $this->getVersion($versionNumber);
        
        if (!$version) {
            return false;
        }

        // Create a new version to record the revert action
        $this->createVersion($changeSummary ?? "Reverted to version {$versionNumber}");

        // Update the model with the version data
        $versionData = $version->toArray();
        
        // Remove version-specific fields
        unset($versionData['id'], $versionData['span_id'], $versionData['connection_id'], 
              $versionData['version_number'], $versionData['changed_by'], 
              $versionData['change_summary'], $versionData['created_at'], $versionData['updated_at']);

        $this->update($versionData);

        return true;
    }

    /**
     * Generate an automatic change summary by comparing with the previous version.
     * 
     * @return string|null
     */
    protected function generateChangeSummary(): ?string
    {
        $previousVersion = $this->getLatestVersion();
        
        if (!$previousVersion) {
            return 'Initial version';
        }

        // For the second version, we need to compare the current model state with the previous version
        // We'll create a temporary version object from the current model state to compare
        $currentVersionData = $this->prepareVersionData();
        
        if ($this instanceof \App\Models\Span) {
            // Create a temporary SpanVersion object for comparison
            $tempVersion = new \App\Models\SpanVersion($currentVersionData);
            $changes = $tempVersion->getDiffFrom($previousVersion);
        } elseif ($this instanceof \App\Models\Connection) {
            // Create a temporary ConnectionVersion object for comparison
            $tempVersion = new \App\Models\ConnectionVersion($currentVersionData);
            $changes = $tempVersion->getDiffFrom($previousVersion);
        } else {
            $changes = [];
        }
        
        if (empty($changes)) {
            return null;
        }

        $summaryParts = [];
        
        if ($this instanceof \App\Models\Span) {
            if (isset($changes['name'])) {
                $summaryParts[] = 'Name changed';
            }
            if (isset($changes['description'])) {
                $summaryParts[] = 'Description updated';
            }
            if (isset($changes['notes'])) {
                $summaryParts[] = 'Notes updated';
            }
            if (isset($changes['start_year']) || isset($changes['start_month']) || isset($changes['start_day'])) {
                $summaryParts[] = 'Start date changed';
            }
            if (isset($changes['end_year']) || isset($changes['end_month']) || isset($changes['end_day'])) {
                $summaryParts[] = 'End date changed';
            }
            if (isset($changes['state'])) {
                $summaryParts[] = 'State changed';
            }
            if (isset($changes['access_level'])) {
                $summaryParts[] = 'Access level changed';
            }
            if (isset($changes['type_id'])) {
                $summaryParts[] = 'Type changed';
            }
            if (isset($changes['metadata'])) {
                $summaryParts[] = 'Metadata updated';
            }
        } elseif ($this instanceof \App\Models\Connection) {
            if (isset($changes['type_id'])) {
                $summaryParts[] = 'Connection type changed';
            }
            if (isset($changes['metadata'])) {
                $summaryParts[] = 'Metadata updated';
            }
        }

        if (empty($summaryParts)) {
            return 'Various fields updated';
        }

        return implode(', ', $summaryParts);
    }

    /**
     * Get a diff between this model and a specific version.
     * 
     * @param int $versionNumber
     * @return array
     */
    public function getDiffFromVersion(int $versionNumber): array
    {
        $version = $this->getVersion($versionNumber);
        
        if (!$version) {
            return [];
        }

        if ($this instanceof \App\Models\Span) {
            return $version->getDiffFrom($this);
        } elseif ($this instanceof \App\Models\Connection) {
            return $version->getDiffFrom($this);
        }

        return [];
    }
} 