<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $rules = [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users')],
            'password'   => ['required', 'string', 'min:8'],
            'rePassword' => ['required', 'string', 'min:8', 'same:password'],
        ];

        $validatedData = $request->validate($rules);

        $user = User::create([
            'name'     => $validatedData['name'],
            'email'    => $validatedData['email'],
            'password' => Hash::make($validatedData['rePassword']),
        ]);

        return response()->json([
            'message' => 'Tạo tài khoản thành công',
            'user'    => $user
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'currentPass' => ['nullable', 'string', 'min:8'],
            'newPass' => ['nullable', 'string', 'min:8'],
            'renewPass' => ['nullable', 'string', 'min:8', 'same:newPass'],
        ];

        if ($request->filled('newPass')) {
            $rules['currentPass'][] = 'required';
        }

        $validatedData = $request->validate($rules);

        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];

        if ($request->filled('newPass')) {
            if (!Hash::check($validatedData['currentPass'], $user->password)) {
                return response()->json([
                    'message' => 'Mật khẩu hiện tại không đúng.'
                ], 401);
            }
            if ($validatedData['currentPass'] == $validatedData['newPass']){
                return response()->json([
                    'message' => 'Mật khẩu hiện tại và mật khẩu mới giống nhau.'
                ], 401);
            }
            $user->password = Hash::make($validatedData['newPass']);
        }
        $user->save();
        return response()->json([
            'message' => 'Cập nhật thông tin thành công.',
            'user' => $user
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function renew(Request $request)
    {
        $request->validate([
            'token_key' => 'required|string'
        ]);

        // 1. Giải mã Base64
        $decoded = base64_decode($request->token_key, true);
        if ($decoded === false) {
            return response()->json(['message' => 'Token key không hợp lệ (lỗi)'], 400);
        }

        $data = json_decode($decoded, true);
        if (!is_array($data) || !isset($data['email'], $data['expire_date'], $data['timestamp'], $data['hash'])) {
            return response()->json(['message' => 'Token key không hợp lệ (thiếu dữ liệu)'], 400);
        }

        $email       = $data['email'];
        $expire_date = $data['expire_date'];
        $timestamp   = $data['timestamp'];
        $hash        = $data['hash'];

        // 2. Tìm user
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng với email này'], 404);
        }

        // 3. Tính lại hash
        $raw       = "{$email}|{$expire_date}|{$timestamp}";
        $secret    = config('app.renew_secret');
        $calc_hash = hash_hmac('sha256', $raw, $secret);

        if (!hash_equals($calc_hash, $hash)) {
            return response()->json(['message' => 'Key không hợp lệ'], 400);
        }

        // 4. Kiểm tra hạn sử dụng
        if (now()->gt(Carbon::parse($expire_date))) {
            return response()->json(['message' => 'Key đã hết hạn sử dụng'], 400);
        }

        // 5. Kiểm tra thời gian tồn tại của key (timestamp + 24h)
        if (now()->timestamp > ((int) $timestamp) + 86400) {
            return response()->json(['message' => 'Key đã quá hạn 24h kể từ khi tạo'], 400);
        }

        // 6. Lưu key vào DB
        $user->token_key = $request->token_key;
        $user->save();

        return response()->json(['message' => 'Gia hạn thành công cho '.$email. ' đến ngày: '. Carbon::parse($expire_date)->format('d/m/Y')]);
    }
}