<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class ViewConventionsTest extends TestCase
{
    /**
     * Files that don't need to extend layouts
     */
    private array $layoutExceptions = [
        'resources/views/layouts/*',
        'resources/views/components/*',
        'resources/views/partials/*',
        'resources/views/errors/*',
        'resources/views/vendor/*',
        'resources/views/auth/*',
        'resources/views/emails/*',
    ];

    /**
     * Test that all views extend a layout
     */
    public function test_views_extend_layout(): void
    {
        $violations = [];
        $files = File::glob(resource_path('views/**/*.blade.php'));

        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file);
            if ($this->isExcepted($relativePath, $this->layoutExceptions)) {
                continue;
            }

            $content = file_get_contents($file);
            if (!preg_match('/@extends\s*\([\'"]/', $content)) {
                $violations[] = $relativePath;
            }
        }

        $this->assertEmpty($violations, 'Views not extending layouts found in: ' . implode(', ', $violations));
    }

    /**
     * Check if a file matches any of the exception patterns
     */
    private function isExcepted(string $file, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $pattern = str_replace('*', '.*', $pattern);
                if (preg_match('#' . $pattern . '#', $file)) {
                    return true;
                }
            } elseif ($file === $pattern) {
                return true;
            }
        }

        return false;
    }
}
