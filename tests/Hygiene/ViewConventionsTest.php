<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class ViewConventionsTest extends TestCase
{
    /**
     * Files that are allowed to have inline styles
     */
    private array $styleExceptions = [
        'resources/views/components/dropdown.blade.php',
        'resources/views/components/modal.blade.php',
    ];

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
    ];

    /**
     * Test that no view files contain inline styles
     */
    public function test_no_inline_styles(): void
    {
        $violations = [];
        $files = File::glob(resource_path('views/**/*.blade.php'));

        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file);
            if ($this->isExcepted($relativePath, $this->styleExceptions)) {
                continue;
            }

            $content = file_get_contents($file);
            if (preg_match('/style=(["\'])[^"\']*\1/', $content)) {
                $violations[] = $relativePath;
            }
        }

        $this->assertEmpty($violations, 'Found inline styles in: ' . implode(', ', $violations));
    }

    /**
     * Test that no view files contain inline JavaScript
     */
    public function test_no_inline_javascript(): void
    {
        $viewFiles = File::glob(resource_path('views/**/*.blade.php'));
        $violations = [];

        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/<script>/', $content)) {
                $violations[] = $file;
            }
        }

        $this->assertEmpty($violations, 'Found inline JavaScript in: ' . implode(', ', $violations));
    }

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
     * Test that we're using Bootstrap classes instead of custom styles
     */
    public function test_using_bootstrap_classes(): void
    {
        $customCssViolations = [];
        $files = File::glob(resource_path('views/**/*.blade.php'));

        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file);
            if ($this->isExcepted($relativePath, $this->styleExceptions)) {
                continue;
            }

            $content = file_get_contents($file);
            if (preg_match('/style=(["\'])[^"\']*\1/', $content)) {
                $customCssViolations[] = $relativePath;
            }
        }

        $this->assertEmpty($customCssViolations, 'Found potential custom CSS instead of Bootstrap classes in: ' . implode(', ', $customCssViolations));
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
            } else {
                if ($file === $pattern) {
                    return true;
                }
            }
        }
        return false;
    }
} 