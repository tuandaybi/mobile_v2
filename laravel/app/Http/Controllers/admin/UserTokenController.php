<?php
// App/Http/Controllers/Admin/UserTokenController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserTokenController extends Controller
{
    public function index($id)
    {
        $user = User::findOrFail($id);
        $tokens = $user->tokens()->select('id','name','created_at','last_used_at')->latest('id')->get();
        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request, $id)
    {
        $request->validate(['token_name' => ['required','string','max:60']]);
        $user = User::findOrFail($id);
        $plain = $user->createToken($request->token_name)->plainTextToken;

        // trả kèm token meta (không có plaintext)
        $token = $user->tokens()->latest('id')->first(['id','name','created_at','last_used_at']);

        return response()->json([
            'token' => $token,
            'plain_text_token' => $plain, // hiển thị 1 lần
        ]);
    }

    public function destroy($id, $tokenId)
    {
        $user = User::findOrFail($id);
        $user->tokens()->where('id', $tokenId)->delete();
        return response()->noContent();
    }
}
