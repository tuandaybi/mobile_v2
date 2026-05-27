<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get();
            return response()->json($categories, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi lấy danh sách loại chi phí'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100|unique:expense_categories,name',
            'code'       => 'nullable|string|max:50|unique:expense_categories,code',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $cat = ExpenseCategory::create($validator->validated());
            return response()->json($cat, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi tạo loại chi phí'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $cat = ExpenseCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100|unique:expense_categories,name,' . $cat->id,
            'code'       => 'nullable|string|max:50|unique:expense_categories,code,' . $cat->id,
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $cat->update($validator->validated());
            return response()->json($cat, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi cập nhật loại chi phí'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cat = ExpenseCategory::findOrFail($id);
            $count = Expense::where('category_id', $cat->id)->count();
            if ($count > 0) {
                return response()->json([
                    'message' => "Không thể xoá: còn {$count} khoản chi phí thuộc loại này",
                ], 409);
            }
            $cat->delete();
            return response()->json(['message' => 'Xoá loại chi phí thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xoá loại chi phí'], 500);
        }
    }
}
