<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\mobile\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorageController extends Controller
{
    /**
     * Display a listing of the colors.
     */
    public function index()
    {
        try {
            $storage = Storage::all();
            return response()->json($storage, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi lấy danh sách: ' . $e], 500);
        }
    }

    /**
     * Store a newly created color in storage.
     */
    public function store(Request $request)
    {

    }

    /**
     * Update the specified color in storage.
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified color from storage.
     */
    public function destroy($id)
    {

    }
}