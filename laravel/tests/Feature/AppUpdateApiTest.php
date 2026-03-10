<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateApiTest extends TestCase
{
    public function test_latest_returns_404_when_no_release_exists_for_requested_app(): void
    {
        Storage::fake('public');

        $response = $this->getJson('/api/app-updates/desktop-pos/latest');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Chua co ban cap nhat nao duoc phat hanh.');
    }

    public function test_latest_and_download_are_scoped_per_app_slug(): void
    {
        Storage::fake('public');

        $binary = 'dummy exe content';
        Storage::disk('public')->put('app-updates/desktop-pos/releases/desktop-pos-v1.exe', $binary);
        Storage::disk('public')->put('app-updates/desktop-crm/releases/desktop-crm-v2.exe', 'other binary');

        Storage::disk('public')->put('app-updates/desktop-pos/latest.json', json_encode([
            'app_slug' => 'desktop-pos',
            'version' => '1.2.0',
            'notes' => 'Bug fixes',
            'mandatory' => true,
            'file_path' => 'app-updates/desktop-pos/releases/desktop-pos-v1.exe',
            'size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'published_at' => now()->toIso8601String(),
        ]));

        Storage::disk('public')->put('app-updates/desktop-crm/latest.json', json_encode([
            'app_slug' => 'desktop-crm',
            'version' => '9.9.9',
            'notes' => 'Different app',
            'mandatory' => false,
            'file_path' => 'app-updates/desktop-crm/releases/desktop-crm-v2.exe',
            'size' => strlen('other binary'),
            'sha256' => hash('sha256', 'other binary'),
            'published_at' => now()->toIso8601String(),
        ]));

        $latest = $this->getJson('/api/app-updates/desktop-pos/latest?current_version=1.0.0');

        $latest
            ->assertOk()
            ->assertJsonPath('app_slug', 'desktop-pos')
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest.version', '1.2.0')
            ->assertJsonPath(
                'latest.download_url',
                route('app-updates.download', ['appSlug' => 'desktop-pos', 'filename' => 'desktop-pos-v1.exe'])
            );

        $download = $this->get('/api/app-updates/desktop-pos/download/desktop-pos-v1.exe');

        $download
            ->assertOk()
            ->assertHeader('content-type', 'application/octet-stream');
    }

    public function test_legacy_latest_endpoint_accepts_app_slug_query_param(): void
    {
        Storage::fake('public');

        $binary = 'dummy exe content';
        Storage::disk('public')->put('app-updates/desktop-pos/releases/desktop-pos-v1.exe', $binary);
        Storage::disk('public')->put('app-updates/desktop-pos/latest.json', json_encode([
            'app_slug' => 'desktop-pos',
            'version' => '1.2.0',
            'notes' => 'Bug fixes',
            'mandatory' => false,
            'file_path' => 'app-updates/desktop-pos/releases/desktop-pos-v1.exe',
            'size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'published_at' => now()->toIso8601String(),
        ]));

        $response = $this->getJson('/api/app-updates/latest?app_slug=desktop-pos&current_version=1.0.0');

        $response
            ->assertOk()
            ->assertJsonPath('app_slug', 'desktop-pos')
            ->assertJsonPath('latest.version', '1.2.0');
    }
}
