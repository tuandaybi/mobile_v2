<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZaloWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('ZALO WEBHOOK', $request->all());

        // Kiểm tra có tin nhắn text không
        if (isset($request['event_name']) && $request['event_name'] === 'user_send_text') {
            $userId = $request['sender']['id'];
            $text   = $request['message']['text'];

            Log::info("User: $userId - Msg: $text");

            // (tạm thời chỉ log, chưa trả lời)
        }

        return response()->json(['status' => 'ok']);
    }
}
