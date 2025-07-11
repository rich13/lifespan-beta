<?php

namespace App\Helpers;

class GitVersionHelper
{
    private const VERSION_PREFIX = 'Lifespan Beta';
    private const VERSION_NUMBER = '0.472'; // Update this when deploying

    public static function getVersion(): string
    {
        return self::VERSION_PREFIX . ' ' . self::VERSION_NUMBER;
    }

    /**
     * Get detailed version information for debugging
     */
    public static function getDetailedVersion(): array
    {
        $info = [
            'version' => self::VERSION_NUMBER,
            'environment' => app()->environment(),
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