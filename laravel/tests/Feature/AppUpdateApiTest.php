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

    public function test_default_channel_latest_and_download_are_scoped_per_app_slug(): void
    {
        Storage::fake('public');

        $binary = 'dummy exe content';
        Storage::disk('public')->put('app-updates/desktop-pos/app/releases/desktop-pos-app-v1.exe', $binary);
        Storage::disk('public')->put('app-updates/desktop-pos/app/latest.json', json_encode([
            'app_slug' => 'desktop-pos',
            'channel' => 'app',
            'version' => '1.2.0',
            'notes' => 'Bug fixes',
            'mandatory' => true,
            'file_path' => 'app-updates/desktop-pos/app/releases/desktop-pos-app-v1.exe',
            'size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'published_at' => now()->toIso8601String(),
        ]));

        $latest = $this->getJson('/api/app-updates/desktop-pos/latest?current_version=1.0.0');

        $latest
            ->assertOk()
            ->assertJsonPath('app_slug', 'desktop-pos')
            ->assertJsonPath('channel', 'app')
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest.version', '1.2.0')
            ->assertJsonPath(
                'latest.download_url',
                route('app-updates.dashboard', [
                    'app_slug' => 'desktop-pos',
                    'channel' => 'app',
                    'filename' => 'desktop-pos-app-v1.exe',
                ], false) . '#downloads'
            );
    }

    public function test_custom_channel_latest_is_independent_from_default_channel(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('app-updates/tiktok-bot/app/releases/tiktok-bot-app-v1.exe', 'app binary');
        Storage::disk('public')->put('app-updates/tiktok-bot/app/latest.json', json_encode([
            'app_slug' => 'tiktok-bot',
            'channel' => 'app',
            'version' => '1.0.0',
            'notes' => 'Desktop shell',
            'mandatory' => false,
            'file_path' => 'app-updates/tiktok-bot/app/releases/tiktok-bot-app-v1.exe',
            'size' => strlen('app binary'),
            'sha256' => hash('sha256', 'app binary'),
            'published_at' => now()->toIso8601String(),
        ]));

        Storage::disk('public')->put('app-updates/tiktok-bot/bot-server/releases/tiktok-bot-bot-server-v2.exe', 'server binary');
        Storage::disk('public')->put('app-updates/tiktok-bot/bot-server/latest.json', json_encode([
            'app_slug' => 'tiktok-bot',
            'channel' => 'bot-server',
            'version' => '2.0.0',
            'notes' => 'Worker process',
            'mandatory' => true,
            'file_path' => 'app-updates/tiktok-bot/bot-server/releases/tiktok-bot-bot-server-v2.exe',
            'size' => strlen('server binary'),
            'sha256' => hash('sha256', 'server binary'),
            'published_at' => now()->toIso8601String(),
        ]));

        $response = $this->getJson('/api/app-updates/tiktok-bot/bot-server/latest?current_version=1.0.0');

        $response
            ->assertOk()
            ->assertJsonPath('app_slug', 'tiktok-bot')
            ->assertJsonPath('channel', 'bot-server')
            ->assertJsonPath('latest.version', '2.0.0')
            ->assertJsonPath(
                'latest.download_url',
                route('app-updates.dashboard', [
                    'app_slug' => 'tiktok-bot',
                    'channel' => 'bot-server',
                    'filename' => 'tiktok-bot-bot-server-v2.exe',
                ], false) . '#downloads'
            );
    }

    public function test_legacy_latest_endpoint_accepts_app_slug_and_channel_query_params(): void
    {
        Storage::fake('public');

        $binary = 'dummy exe content';
        Storage::disk('public')->put('app-updates/desktop-pos/bot-server/releases/desktop-pos-bot-server-v1.exe', $binary);
        Storage::disk('public')->put('app-updates/desktop-pos/bot-server/latest.json', json_encode([
            'app_slug' => 'desktop-pos',
            'channel' => 'bot-server',
            'version' => '1.2.0',
            'notes' => 'Bot worker',
            'mandatory' => false,
            'file_path' => 'app-updates/desktop-pos/bot-server/releases/desktop-pos-bot-server-v1.exe',
            'size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'published_at' => now()->toIso8601String(),
        ]));

        $response = $this->getJson('/api/app-updates/latest?app_slug=desktop-pos&channel=bot-server&current_version=1.0.0');

        $response
            ->assertOk()
            ->assertJsonPath('app_slug', 'desktop-pos')
            ->assertJsonPath('channel', 'bot-server')
            ->assertJsonPath('latest.version', '1.2.0');
    }
}
