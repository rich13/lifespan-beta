<?php

namespace App\Helpers;

class GitVersionHelper
{
    private const VERSION_PREFIX = 'Lifespan Beta';

    public static function getVersion(): string
    {
        // First try to get version from environment
        $version = config('app.version');
        
        if ($version) {
            return self::VERSION_PREFIX . ' ' . $version;
        }

        // If no version in env, try git (development only)
        if (app()->environment('local', 'development')) {
            try {
                $commitCount = trim(shell_exec("git rev-list --count HEAD 2>/dev/null"));
                if ($commitCount) {
                    return self::VERSION_PREFIX . ' 0.' . $commitCount;
                }
            } catch (\Exception $e) {
                \Log::error('Error getting git version: ' . $e->getMessage());
            }
        }

        return self::VERSION_PREFIX . ' (unknown)';
    }

    /**
     * Get detailed version information for debugging
     */
    public static function getDetailedVersion(): array
    {
        $info = [
            'environment' => app()->environment(),
            'version' => config('app.version'),
        ];

        if (app()->environment('local', 'development')) {
            try {
                $info = array_merge($info, [
                    'branch' => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')),
                    'commit' => trim(shell_exec('git rev-parse --short HEAD 2>/dev/null')),
                    'commit_count' => trim(shell_exec("git rev-list --count HEAD 2>/dev/null")),
                    'last_commit_date' => trim(shell_exec("git log -1 --format=%cd 2>/dev/null")),
                ]);
            } catch (\Exception $e) {
                $info['git_error'] = $e->getMessage();
            }
        }

        return $info;
    }
} 