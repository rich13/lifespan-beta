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
        $this->assertStringContainsString('paused for maintenance', $content, 'Maintenance page should explain the pause');
        $this->assertStringContainsString('https://info.lifespan.dev', $content, 'Maintenance page should link to info.lifespan.dev');
        $this->assertStringContainsString('id="game-of-life-bg"', $content, 'Maintenance page should use the Game of Life background');
        $this->assertStringContainsString('/js/game-of-life.js', $content, 'Maintenance page should load the shared Game of Life script');
        $this->assertStringContainsString('/css/game-of-life.css', $content, 'Maintenance page should load the shared Game of Life styles');
        $this->assertFileExists(public_path('js/game-of-life.js'), 'Shared Game of Life script must exist in public/js');
        $this->assertFileExists(public_path('css/game-of-life.css'), 'Shared Game of Life styles must exist in public/css');
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
        $this->assertStringContainsString('location /js/', $content, 'Config must serve /js during maintenance so Game of Life can load');
        $this->assertStringContainsString('location /css/', $content, 'Config must serve /css during maintenance so Game of Life can load');
    }
}
