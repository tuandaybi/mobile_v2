<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ColorController extends Controller
{
    /**
     * Display a listing of the colors.
     */
    public function index()
    {
        try {
            $colors = Color::all();
            return response()->json($colors, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi lấy danh sách màu:' + $e], 500);
        }
    }

    /**
     * Store a newly created color in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'en_name' => 'required|string|max:255',
            'vi_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $color = Color::create($request->all());
            return response()->json($color, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi tạo màu'], 500);
        }
    }

    /**
     * Update the specified color in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'en_name' => 'required|string|max:255',
            'vi_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $color = Color::findOrFail($id);
            $color->update($request->all());
            return response()->json($color, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi cập nhật màu'], 500);
        }
    }

    /**
     * Remove the specified color from storage.
     */
    public function destroy($id)
    {
        try {
            $color = Color::findOrFail($id);
            $color->delete();
            return response()->json(['message' => 'Xóa màu thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xóa màu'], 500);
        }
    }
}