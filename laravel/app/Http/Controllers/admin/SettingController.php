<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function telegramShow()
    {
        return response()->json([
            'telegram_bot_token' => Setting::get('telegram_bot_token', ''),
            'telegram_chat_id'   => Setting::get('telegram_chat_id', ''),
            'telegram_enabled'   => Setting::get('telegram_enabled', '1'),
        ]);
    }

    public function telegramUpdate(Request $request)
    {
        $request->validate([
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id'   => 'nullable|string|max:255',
            'telegram_enabled'   => 'required|in:0,1',
        ]);

        Setting::set('telegram_bot_token', $request->input('telegram_bot_token'));
        Setting::set('telegram_chat_id', $request->input('telegram_chat_id'));
        Setting::set('telegram_enabled', $request->input('telegram_enabled'));

        return response()->json(['message' => 'Cập nhật cài đặt Telegram thành công']);
    }
}
