<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use App\Actions\Users\CreateUser;
use App\Http\Resources\AuthUserResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\ResolvesStore;
use App\Notifications\TelegramNotification;
use Illuminate\Support\Facades\Notification;

class AuthController extends Controller
{
    use ResolvesStore;

    public function index(Request $request){
        // Trả về thông tin người dùng đã đăng nhập
        $user = $request->user(); // yêu cầu route có auth:sanctum
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'user' => (new AuthUserResource($user))->withToken($request->bearerToken()),
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
            'remember' => ['boolean'],
        ]);

        $remember = (bool)($validated['remember'] ?? false);
        $secret = config('app.renew_secret') ?? '';
        if ($secret === '') {
            return response()->json(['message'=>'Server chưa cấu hình renew_secret'], 500);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message'=>'Email hoặc mật khẩu không đúng'], 401);
        }
        if (!$user->is_active) {
            return response()->json(['message'=>'Tài khoản của bạn chưa được kích hoạt.'], 403);
        }

        // ✅ Chỉ kiểm tra theo token_key (license)
        if (empty($user->token_key)) {
            return response()->json(['message'=>'Mua key để sử dụng sản phẩm'], 403);
        }

        // Parse & verify license token (không cần expectEmail trong parser)
        try {
            $parsed = $this->parseTokenKey($user->token_key, $secret);
            // $parsed = ['email'=>..., 'expires'=>Carbon, 'jti'=>?]
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        // (Tuỳ chọn) So khớp email trong token với user để chặt chẽ hơn
        if (strcasecmp($parsed['email'], $user->email) !== 0) {
            return response()->json(['message'=>'Token không khớp người dùng'], 403);
        }

        // Kiểm tra hạn
        if (now()->greaterThan($parsed['expires'])) {
            return response()->json(['message'=>'Tài khoản của bạn đã hết hạn sử dụng.'], 403);
        }

        // Tạo Sanctum token
        $tokenName  = $remember ? 'auth_token_remember' : 'auth_token';
        // (Tuỳ chọn) abilities: ['app:use']
        $plainToken = $user->createToken($tokenName)->plainTextToken;

        // Lưu/đính kèm thông tin hạn cho frontend (Sanctum không tự set expiry)
        $licenseExpiresAt = $user->license_expires_at ?? $parsed['expires'];

        $storeId = $this->resolveStoreIdByUserId($user->id);
        $store = DB::table('stores')->select('id','name')->where('id', $storeId)->first();
        $store_name = $store->name ?? null;

        TelegramNotification::send("Người dùng đăng nhập:\n- {$user->name}\n- {$user->email}\n- Store: ".($store_name ?? 'N/A')."\n- IP: ".$request->ip()."\n- User-Agent: ".$request->userAgent());

        $userPayload = (new AuthUserResource($user))
        ->withToken($plainToken)
        ->withStoreName($store_name)
        ->toArray($request);

        return response()->json([
            'message'   => 'Đăng nhập thành công',
            'user'      => $userPayload,
            'license'   => [
                'email'   => $parsed['email'],
                'expires' => $licenseExpiresAt instanceof Carbon\CarbonInterface
                    ? $licenseExpiresAt->toIso8601String()
                    : Carbon::parse($licenseExpiresAt)->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user(); // route phải có auth:sanctum

        // 1) Chuẩn hoá: trim và chuyển rỗng -> null (tránh " " làm bật validate)
        $payload = $request->all();
        foreach (['currentPass','newPass','renewPass'] as $f) {
            $val = isset($payload[$f]) ? trim((string)$payload[$f]) : '';
            $payload[$f] = ($val === '') ? null : $val;
        }
        $request->replace($payload);

        // 2) Xác định có ý định đổi mật khẩu
        $changing = $request->filled('currentPass') || $request->filled('newPass') || $request->filled('renewPass');

        // 3) Build rules
        $rules = [
            'name'  => ['required','string','max:255'],
            'email' => ['required','email', Rule::unique('users','email')->ignore($user->id)],
        ];
        if ($changing) {
            $rules += [
                'currentPass' => ['required','string','min:8'],
                'newPass'     => ['required','string','min:8'],
                'renewPass'   => ['required','string','min:8','same:newPass'],
            ];
        }

        $messages = [
            'email.unique'     => 'Email đã được sử dụng.',
            'currentPass.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'newPass.required'     => 'Vui lòng nhập mật khẩu mới.',
            'renewPass.required'   => 'Vui lòng xác nhận mật khẩu mới.',
            'renewPass.same'       => 'Mật khẩu xác nhận không khớp.',
        ];

        $data = $request->validate($rules, $messages);

        // 4) Nếu đổi mật khẩu: check current đúng
        if ($changing && !Hash::check($request->input('currentPass'), $user->password)) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => ['currentPass' => ['Mật khẩu hiện tại không đúng.']],
            ], 422);
        }

        // 5) Cập nhật
        $user->name  = $data['name'];
        $user->email = $data['email'];
        if ($changing) {
            $user->password = Hash::make($request->input('newPass'));
        }
        $user->save();

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user'    => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'created_at'         => $user->created_at,
                'license_expires_at' => $user->license_expires_at,
                'roles'              => method_exists($user,'getRoleNames') ? $user->getRoleNames() : [],
            ],
        ]);
    }

    // Redeem: lưu token_key + sync license_expires_at để FE đọc
    public function redeem(Request $req)
    {
        $req->validate(['code' => 'required|string']);
        $secret = config('app.renew_secret') ?? '';
        if ($secret === '') {
            return response()->json(['message' => 'Server chưa cấu hình'], 500);
        }

        try {
            $parsed = $this->parseTokenKey($req->input('code'), $secret);
            // $parsed = ['email' => ..., 'expires' => Carbon]
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = \App\Models\User::where('email', $parsed['email'])->first();
        if (!$user) return response()->json(['message' => 'Email không tồn tại'], 404);

        // (tuỳ chọn) chặn dùng lại mã nếu bạn có jti
        // RedeemCode::firstOrCreate(['jti' => $parsed['jti']], ['used_at' => now()]);

        $user->forceFill([
            'token_key'          => $req->input('code'),
            'license_expires_at' => $parsed['expires'],
        ])->save();

        // Tạo thông báo.
        $notiId = DB::table('notifications')->insertGetId([
            'type'      => 'reminder',
            'ref_type'  => 'license_renewed',
            'title'     => 'Gia hạn thành công',
            'body'      => 'Cảm ơn bạn đã gia hạn sử dụng sản phẩm. Hạn sử dụng mới của bạn là đến ngày '.$parsed['expires']->format('d/m/Y').'.',
            'created_by'=> $user->id,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);
        DB::table('notification_recipients')->insert([
            'notification_id' => $notiId,
            'user_id'         => $user->id,
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        TelegramNotification::send("Người dùng gia hạn thành công:\n- {$user->name}\n- {$user->email}\n- Hạn mới: ".$parsed['expires']->toIso8601String()."\n- IP: ".$req->ip()."\n- User-Agent: ".$req->userAgent());

        return response()->json([
            'message'            => 'Gia hạn thành công',
            'license_expires_at' => $parsed['expires']->toIso8601String(),
        ]);
        
    }

    private function parseTokenKey(string $base64, string $secret): array
    {
        // Hỗ trợ cả base64 chuẩn và base64url
        $json = base64_decode($base64, true);
        if ($json === false) {
            // thử base64url
            $b64 = strtr($base64, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad) $b64 .= str_repeat('=', 4 - $pad);
            $json = base64_decode($b64, true);
        }
        if ($json === false) throw new \Exception('Token base64 không hợp lệ');

        $data = json_decode($json, true);
        if (!is_array($data)) throw new \Exception('Token JSON không hợp lệ');

        foreach (['email','expire_date','timestamp','hash'] as $k) {
            if (!isset($data[$k])) throw new \Exception('Token thiếu dữ liệu');
        }

        // Verify HMAC: email|expire_date|timestamp
        $raw = $data['email'].'|'.$data['expire_date'].'|'.$data['timestamp'];
        $expected = hash_hmac('sha256', $raw, $secret);

        // Một số hệ sinh hash viết hoa ⇒ normalize
        $given = strtolower((string)$data['hash']);
        if (!hash_equals($expected, $given)) {
            throw new \Exception('Chữ ký token không hợp lệ');
        }

        // Parse hạn dùng (end of day)
        $expires = \Carbon\Carbon::parse($data['expire_date'])->endOfDay();

        return [
            'email'   => $data['email'],
            'expires' => $expires,
            'jti'     => $data['jti'] ?? null,
            'payload' => $data,
        ];
    }

    public function verify(Request $request)
    {
        $request->validate(['key' => 'required|string']);
    
        $secret = config('app.renew_secret') ?? '';
        if ($secret === '') {
            return response()->json(['message' => 'Server chưa cấu hình renew_secret'], 500);
        }
    
        // 1. Parse & verify chữ ký token
        try {
            $parsed = $this->parseTokenKey($request->input('key'), $secret);
        } catch (\Exception $e) {
            return response()->json([
                'valid'   => false,
                'reason'  => 'invalid_key',
                'message' => $e->getMessage(),
            ], 422);
        }
    
        // 2. Tìm user theo email trong token
        $user = \App\Models\User::where('email', $parsed['email'])->first();
        if (!$user) {
            return response()->json([
                'valid'   => false,
                'reason'  => 'user_not_found',
                'message' => 'Không tìm thấy tài khoản.',
            ], 404);
        }
    
        // 3. Kiểm tra tài khoản có active không
        if (!$user->is_active) {
            return response()->json([
                'valid'   => false,
                'reason'  => 'inactive',
                'message' => 'Tài khoản chưa được kích hoạt.',
            ], 403);
        }
    
        // 4. So khớp token_key lưu trong DB với key gửi lên
        //    (chặn trường hợp key bị share / dùng key cũ đã bị thay)
        if ($user->token_key !== $request->input('key')) {
            return response()->json([
                'valid'   => false,
                'reason'  => 'key_mismatch',
                'message' => 'Key không khớp tài khoản. Vui lòng gia hạn lại.',
            ], 403);
        }
    
        // 5. Kiểm tra hạn
        $now     = now();
        $expires = $parsed['expires']; // Carbon end-of-day
        if ($now->greaterThan($expires)) {
            return response()->json([
                'valid'          => false,
                'reason'         => 'expired',
                'message'        => 'License đã hết hạn. Vui lòng gia hạn.',
                'license'        => [
                    'expires'       => $expires->toIso8601String(),
                    'days_remaining' => 0,
                ],
            ], 403);
        }
    
        // 6. Trả về thông tin hợp lệ
        $daysRemaining = (int) $now->diffInDays($expires);
    
        $storeId   = $this->resolveStoreIdByUserId($user->id);
        $store     = \Illuminate\Support\Facades\DB::table('stores')
                        ->select('id', 'name')
                        ->where('id', $storeId)
                        ->first();
    
        return response()->json([
            'valid'   => true,
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'store_name' => $store->name ?? null,
            ],
            'license' => [
                'expires'        => $expires->toIso8601String(),
                'days_remaining' => $daysRemaining,
            ],
        ]);
    }
}
