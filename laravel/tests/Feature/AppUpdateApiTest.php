<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateApiTest extends TestCase
{
    public function test_latest_returns_404_when_no_release_exists(): void
    {
        Storage::fake('public');

        $response = $this->getJson('/api/app-updates/latest');

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Chưa có bản cập nhật nào được phát hành.');
    }

    public function test_latest_and_download_work_with_existing_release_metadata(): void
    {
        Storage::fake('public');

        $binary = 'dummy exe content';
        Storage::disk('public')->put('app-updates/releases/app-v1.exe', $binary);

        Storage::disk('public')->put('app-updates/latest.json', json_encode([
            'version' => '1.2.0',
            'notes' => 'Bug fixes',
            'mandatory' => true,
            'file_path' => 'app-updates/releases/app-v1.exe',
            'size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'published_at' => now()->toIso8601String(),
        ]));

        $latest = $this->getJson('/api/app-updates/latest?current_version=1.0.0');

        $latest
            ->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest.version', '1.2.0')
            ->assertJsonPath('latest.download_url', route('app-updates.download', ['filename' => 'app-v1.exe']));

        $download = $this->get('/api/app-updates/download/app-v1.exe');
        $download
            ->assertOk()
            ->assertHeader('content-type', 'application/octet-stream');
    }
}
