<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNotification extends Notification
{
    use Queueable;

    protected $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to(self::chatId())
            ->content($this->message)
            ->options(['parse_mode' => '']);
    }

    public static function send(string $message): void
    {
        try {
            if (!self::isEnabled()) return;

            $token = self::botToken();
            $chatId = self::chatId();
            if (!$token || !$chatId) return;

            config(['services.telegram-bot-api.token' => $token]);

            NotificationFacade::route('telegram', $chatId)
                ->notify(new self($message));
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed: ' . $e->getMessage());
        }
    }

    private static function botToken(): ?string
    {
        return Setting::get('telegram_bot_token') ?? env('TELEGRAM_BOT_TOKEN');
    }

    private static function chatId(): ?string
    {
        return Setting::get('telegram_chat_id') ?? env('TELEGRAM_CHAT_ID');
    }

    private static function isEnabled(): bool
    {
        $val = Setting::get('telegram_enabled');
        if ($val !== null) return $val === '1';
        return !empty(env('TELEGRAM_BOT_TOKEN'));
    }
}
