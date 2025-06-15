<?php

namespace App\Helpers;

class GitVersionHelper
{
    private const BETA_START_COMMIT = 'initial-commit'; // Replace this with your actual initial commit hash
    private const VERSION_PREFIX = 'Lifespan Beta';

    public static function getVersion(): string
    {
        try {
            // Get the current branch name
            $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
            
            // Get the number of commits since beta start
            $commitCount = trim(shell_exec("git rev-list --count HEAD 2>/dev/null"));
            
            if ($commitCount) {
                // Format the version number as 0.x
                $versionNumber = '0.' . $commitCount;
                return self::VERSION_PREFIX . ' ' . $versionNumber;
            }
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            \Log::error('Error getting git version: ' . $e->getMessage());
        }

        return self::VERSION_PREFIX . ' (unknown)';
    }

    /**
     * Get detailed version information for debugging
     */
    public static function getDetailedVersion(): array
    {
        try {
            return [
                'branch' => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')),
                'commit' => trim(shell_exec('git rev-parse --short HEAD 2>/dev/null')),
                'commit_count' => trim(shell_exec("git rev-list --count HEAD 2>/dev/null")),
                'last_commit_date' => trim(shell_exec("git log -1 --format=%cd 2>/dev/null")),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
} 