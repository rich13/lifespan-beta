<?php

namespace Tests\Hygiene;

use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    public function test_maintenance_html_exists_and_has_expected_content(): void
    {
        $path = base_path('public/maintenance.html');
        $this->assertFileExists($path, 'public/maintenance.html must exist for maintenance mode');

        $content = file_get_contents($path);
        $this->assertStringContainsString('Lifespan', $content, 'Maintenance page should mention Lifespan');
        $this->assertStringContainsString('<!DOCTYPE html>', $content, 'Maintenance page should be valid HTML');
    }

    public function test_nginx_maintenance_config_exists_and_has_required_directives(): void
    {
        $path = base_path('docker/prod/nginx-maintenance.conf');
        $this->assertFileExists($path, 'docker/prod/nginx-maintenance.conf must exist for maintenance mode');

        $content = file_get_contents($path);
        $this->assertStringContainsString('listen 8080', $content, 'Nginx must listen on 8080 for Railway');
        $this->assertStringContainsString('location = /health', $content, 'Nginx must serve /health for Railway health checks');
        $this->assertStringContainsString('return 200', $content, 'Health endpoint must return 200');
        $this->assertStringContainsString('return 503', $content, 'Maintenance page must return 503');
        $this->assertStringContainsString('maintenance.html', $content, 'Config must reference maintenance.html');
    }
}
